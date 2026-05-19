<?php
session_start();
header('Content-Type: application/json');

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

if (!$configFound || !isset($conn)) {
    echo json_encode(['success' => false, 'error' => 'Config not found']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

$data = json_decode(file_get_contents('php://input'), true);
$post_id = (int)($data['post_id'] ?? 0);
$comment = trim($data['comment'] ?? '');

if (!$post_id) {
    echo json_encode(['success' => false, 'error' => 'Post ID required']);
    exit;
}

try {
    // Get original post with author info
    $check = $conn->prepare("
        SELECT p.post_id, p.content, p.file_url, p.user_id,
               u.first_name, u.last_name, u.profile_picture
        FROM posts p
        JOIN users u ON p.user_id = u.user_id
        WHERE p.post_id = ? AND p.is_deleted = 0 AND p.status = 'approved' AND p.visibility = 'public'
    ");
    $check->execute([$post_id]);
    $post = $check->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        echo json_encode(['success' => false, 'error' => 'Post not found']);
        exit;
    }

    // Record share
    $stmt = $conn->prepare("INSERT INTO shares (post_id, user_id) VALUES (?, ?)");
    $stmt->execute([$post_id, $user_id]);

    // Create shared post with shared_post_id reference
    $shared_content = $comment ? $comment : '';

    $insert = $conn->prepare("
        INSERT INTO posts (user_id, shared_post_id, content, file_url, file_type, status, visibility) 
        VALUES (?, ?, ?, ?, 'none', 'approved', 'public')
    ");
    $insert->execute([$user_id, $post_id, $shared_content, $post['file_url']]);

    $new_post_id = $conn->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => 'Post shared',
        'post_id' => $new_post_id
    ]);

} catch (Exception $e) {
    error_log('Share error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}