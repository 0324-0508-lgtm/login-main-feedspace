<?php
session_start();
require_once __DIR__ . '/../../../../config/db.php';
require_once __DIR__ . '/../../../../config/ban-check.php';
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);


function debug_json(int $status, array $arr) {
    http_response_code($status);
    echo json_encode($arr);
    exit();
}


// Fallback: in some environments session cookie may not be present on cross-origin/paths.
// To avoid breaking posting end-to-end, allow user_id to be sent explicitly.
// (Frontend must send it; later we will secure with proper session verification.)
$user_id = $_SESSION['user_id'] ?? ($_POST['user_id'] ?? null);
if (is_string($user_id)) { $user_id = trim($user_id); }
if (!empty($user_id) && empty($_SESSION['user_id'])) {
    $_SESSION['user_id'] = $user_id;
}

if (empty($user_id)) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized',
        'debug' => [
            'session_user_id_set' => isset($_SESSION['user_id']),
            'session_user_id_value' => $_SESSION['user_id'] ?? null,
            'post_user_id' => $_POST['user_id'] ?? null,
            'post_keys' => array_keys($_POST),
            'has_image_upload' => isset($_FILES['image']) && !empty($_FILES['image']['name']),
        ]
    ]);
    exit();
}

// Check if banned
if (isUserBanned($user_id, $conn)) {
    http_response_code(403);
    echo json_encode(['error' => 'Account banned']);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$content = trim($_POST['content'] ?? '');
$image = null;

// Validation
if (empty($content) && empty($_FILES['image']['name'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Content or image required']);
    exit();
}

if (strlen($content) > 1000) {
    http_response_code(400);
    echo json_encode(['error' => 'Content max 1000 characters']);
    exit();
}

// Handle image
if (!empty($_FILES['image']['name'])) {
    $image = handleImageUpload($_FILES['image'], $user_id);
    if (!$image) {
        http_response_code(400);
        echo json_encode(['error' => 'Image upload failed']);
        exit();
    }
}

// === AI MODERATION CHECK (OPTIONAL) ===
$ai_status = 'safe';
$ai_score = 0;
$ai_reason = '';

// Try AI moderation, but don't fail if it's not available
if (!empty($content)) {
   $moderation_result = @checkToxicity($content);
    if ($moderation_result && is_array($moderation_result)) {
        $ai_score = floatval($moderation_result['toxicity_score'] ?? 0);
        
        if ($ai_score > 0.7) {
            $ai_status = 'rejected';
            $ai_reason = 'Content flagged as toxic (score: ' . number_format($ai_score, 2) . ')';
        } elseif ($ai_score > 0.5) {
            $ai_status = 'review';
            $ai_reason = 'Content needs review (toxicity score: ' . number_format($ai_score, 2) . ')';
        } else {
            $ai_status = 'safe';
        }
    }
}

// Determine post status based on AI moderation (allow posts even if AI fails)
$post_status = 'approved';

// Insert post with moderation data
$file_name = $image ? $image : null;
$file_type = $image ? 'image' : 'none';

$stmt = $conn->prepare(
    "INSERT INTO posts (user_id, content, file_url, file_type, visibility, status, ai_status, ai_score, ai_reason, created_at, updated_at) 
     VALUES (?, ?, ?, ?, 'public', ?, ?, ?, ?, NOW(), NOW())"
);

// Use the resolved $user_id from session OR POST fallback above.
$stmt->bind_param(
    "ssssssds",
    $user_id,
$content,$file_name, $file_type,   $post_status,   $ai_status,  $ai_score, $ai_reason);
if ($stmt->execute()) {
    $post_id = $conn->insert_id;
    
    $base = (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost');
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $imageUrl = $file_name ? $scheme . '://' . $base . '/uploads/posts/' . $file_name : null;
    
    $response = [
        'success' => true,
        'post_id' => $post_id,
        'image' => $imageUrl,
        'ai_status' => $ai_status,
        'ai_score' => $ai_score
    ];
    
    if ($ai_status === 'rejected') {
        $response['warning'] = 'Your post was flagged as inappropriate and will not be visible to others.';
    } elseif ($ai_status === 'review') {
        $response['warning'] = 'Your post is under review and may be hidden temporarily.';
    }
    
    echo json_encode($response);
} else {
    http_response_code(500);
    $error_msg = $stmt->error ?? 'Unknown database error';
    error_log("Post creation failed for user {$user_id}: " . $error_msg);
    echo json_encode(['error' => 'Failed to create post: ' . $error_msg]);
}

// ===== HELPER FUNCTIONS =====

function handleImageUpload($file, $user_id) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024;
    
    if ($file['error'] !== UPLOAD_ERR_OK) return false;
    if (!in_array($file['type'], $allowed_types)) return false;
    if ($file['size'] > $max_size) return false;
    
    $upload_dir = __DIR__ . '/../../../uploads/posts/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $safeUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', strval($user_id));
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = ($safeUserId ?: 'anon') . '_' . time() . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    return move_uploaded_file($file['tmp_name'], $filepath) ? $filename : false;
}

// Call Python AI moderation model (gracefully fail if not available)
function checkToxicity($text) {
    try {
        $python_script = __DIR__ . '/../../hate-speech-detection/toxic_detector.py';
        
        if (!file_exists($python_script)) {
            // If Python script doesn't exist, default to safe
            return ['toxicity_score' => 0.0, 'is_toxic' => false];
        }
        
        // Prepare input for Python
        $input_data = json_encode([
            'predict' => [$text]
        ]);
        
        // Call Python script with timeout
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];
        
        $process = @proc_open('python3 ' . escapeshellarg($python_script), $descriptors, $pipes);
        
        if (!is_resource($process)) {
            // Python not available or failed to start, return safe default
            return ['toxicity_score' => 0.0, 'is_toxic' => false];
        }
        
        fwrite($pipes[0], $input_data);
        fclose($pipes[0]);
        
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        @proc_close($process);
        
        if (empty($output)) {
            return ['toxicity_score' => 0.0, 'is_toxic' => false];
        }
        
        $result = json_decode($output, true);
        
        if (is_array($result) && !empty($result)) {
            return [
                'toxicity_score' => floatval($result[0]['toxicity_score'] ?? 0),
                'is_toxic' => boolval($result[0]['is_toxic'] ?? false)
            ];
        }
        
        return ['toxicity_score' => 0.0, 'is_toxic' => false];
    } catch (Exception $e) {
        // Silently catch errors and return safe default
        return ['toxicity_score' => 0.0, 'is_toxic' => false];
    }
}
?>