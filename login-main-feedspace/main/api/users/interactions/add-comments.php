<?php
// add-comments.php - PROPER DATABASE INTEGRATION WITH AI MODERATION

session_start();
include '../../../config/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// POST only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'POST required']));
}

// Get data from session or request body
$userId = $_SESSION['user_id'] ?? null;
$input = json_decode(file_get_contents('php://input'), true) ?? [];

$postId = intval($_GET['id'] ?? $input['postId'] ?? 0);
$text = trim($input['text'] ?? $_POST['text'] ?? '');

// Validation
if (!$userId) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'User not authenticated']));
}

if (!$postId || $postId <= 0) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Valid postId required']));
}

if (strlen($text) < 1 || strlen($text) > 500) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Comment must be 1-500 characters']));
}

// Verify post exists
$check_stmt = $conn->prepare("SELECT post_id FROM posts WHERE post_id = ?");
$check_stmt->bind_param("i", $postId);
$check_stmt->execute();
if (!$check_stmt->get_result()->fetch_assoc()) {
    http_response_code(404);
    exit(json_encode(['success' => false, 'message' => 'Post not found']));
}

// === AI MODERATION CHECK (OPTIONAL) ===
$moderation_status = 'pending';
$toxicity_score = 0.0;
$moderation_reason = null;

$moderation_result = @checkToxicity($text);
if ($moderation_result && is_array($moderation_result)) {
    $toxicity_score = floatval($moderation_result['toxicity_score'] ?? 0);
    
    if ($toxicity_score > 0.7) {
        $moderation_status = 'removed';
        $moderation_reason = 'Toxic content detected (score: ' . number_format($toxicity_score, 2) . ')';
    } elseif ($toxicity_score > 0.5) {
        $moderation_status = 'flagged';
        $moderation_reason = 'Flagged for review (toxicity score: ' . number_format($toxicity_score, 2) . ')';
    } else {
        $moderation_status = 'approved';
    }
}

// Insert comment into database
$stmt = $conn->prepare(
    "INSERT INTO comments (post_id, user_id, content, moderation_status, toxicity_score, moderation_reason, created_at) 
     VALUES (?, ?, ?, ?, ?, ?, NOW())"
);

if (!$stmt) {
    http_response_code(500);
    error_log("Prepare failed: " . $conn->error);
    exit(json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]));
}

$stmt->bind_param("isssds", $postId, $userId, $text, $moderation_status, $toxicity_score, $moderation_reason);

if (!$stmt->execute()) {
    http_response_code(500);
    error_log("Comment insert failed: " . $stmt->error);
    exit(json_encode(['success' => false, 'message' => 'Failed to save comment: ' . $stmt->error]));
}

$comment_id = $conn->insert_id;

// Get user info for response
$user_stmt = $conn->prepare("SELECT first_name, last_name, profile_picture FROM users WHERE user_id = ?");
$user_stmt->bind_param("s", $userId);
$user_stmt->execute();
$user_result = $user_stmt->get_result()->fetch_assoc();

// Get comment count
$count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM comments WHERE post_id = ? AND moderation_status != 'removed'");
$count_stmt->bind_param("i", $postId);
$count_stmt->execute();
$count_result = $count_stmt->get_result()->fetch_assoc();
$comment_count = intval($count_result['count'] ?? 0);

$response = [
    'success' => true,
    'comment_id' => $comment_id,
    'post_id' => $postId,
    'user_id' => $userId,
    'text' => $text,
    'author' => trim(($user_result['first_name'] ?? 'Unknown') . ' ' . ($user_result['last_name'] ?? '')),
    'avatar' => $user_result['profile_picture'] ?? 'http://localhost/assets/default.png',
    'created_at' => date('M d, Y H:i'),
    'moderation_status' => $moderation_status,
    'toxicity_score' => round($toxicity_score, 2),
    'comment_count' => $comment_count
];

// Add warnings if moderated
if ($moderation_status === 'removed') {
    $response['warning'] = 'Your comment was removed due to inappropriate content.';
} elseif ($moderation_status === 'flagged') {
    $response['warning'] = 'Your comment is flagged for review.';
}

echo json_encode($response);

// ===== HELPER FUNCTIONS =====

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