<?php
// Prevent ANY output before JSON
ob_start();

session_start();
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

try {
    // Find config
    $paths = [
        __DIR__ . '/../../../config/db.php',
        __DIR__ . '/../../../../config/db.php',
    ];

    $configFound = false;
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $configFound = true;
            break;
        }
    }

    if (!$configFound) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Config not found']);
        exit;
    }

    // Check login
    if (!isset($_SESSION['user_id'])) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $content = trim($_POST['content'] ?? '');
    $file_url = null;
    $file_type = null;

    // Validate
    if (empty($content) && empty($_FILES['image'])) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Content or image required']);
        exit;
    }

    // Handle image upload
    if (!empty($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../../../../uploads/posts/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (!in_array($ext, $allowed)) {
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => 'Invalid image type']);
            exit;
        }
        
        $filename = $user_id . '_' . time() . '.' . $ext;
        $filepath = $uploadDir . $filename;
        
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $filepath)) {
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => 'Failed to upload image']);
            exit;
        }
        
        $file_url = $filename;
        $file_type = 'image/' . $ext;
    }

    // AI Moderation (optional)
    $ai_score = 0;
    $ai_status = 'safe';
    $ai_reason = null;
    $status = 'approved';

    $aiPath = __DIR__ . '/../../users/ai/ai-moderator.php';
    if (!file_exists($aiPath)) {
        $aiPath = __DIR__ . '/../../../users/ai/ai-moderator.php';
    }

    if (file_exists($aiPath)) {
        require_once $aiPath;
        if (class_exists('AIModerator')) {
            $moderator = new AIModerator($conn);
            $aiResult = $moderator->analyzeContent($content);
            $ai_score = $aiResult['score'] ?? 0;
            $ai_status = $aiResult['status'] ?? 'safe';
            $ai_reason = $aiResult['reason'] ?? null;
            
            if ($ai_status === 'rejected') {
                $status = 'rejected';
            } elseif ($ai_status === 'flagged') {
                $status = 'pending';
            }
        }
    }

    // Insert post
    $stmt = $conn->prepare("
        INSERT INTO posts (user_id, content, file_url, file_type, status, ai_score, ai_status, ai_reason, created_at, is_deleted, is_archived)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 0, 0)
    ");
    
    $stmt->execute([
        $user_id,
        $content,
        $file_url,
        $file_type,
        $status,
        $ai_score,
        $ai_status,
        $ai_reason
    ]);
    
    $post_id = $conn->lastInsertId();

    ob_end_clean();
    echo json_encode([
        'success' => true,
        'post_id' => $post_id,
        'status' => $status,
        'ai_status' => $ai_status
    ]);

} catch (Exception $e) {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}