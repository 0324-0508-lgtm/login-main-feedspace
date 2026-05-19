<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

$data = json_decode(file_get_contents('php://input'), true);
$post_id = (int)($data['post_id'] ?? 0);

if (!$post_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Post ID required']);
    exit;
}

try {
    // Check if post exists
    $postCheck = $conn->prepare("SELECT post_id FROM posts WHERE post_id = ? AND is_deleted = 0");
    $postCheck->execute([$post_id]);
    if (!$postCheck->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Post not found']);
        exit;
    }

    // Check if already liked
    $check = $conn->prepare("SELECT like_id FROM post_likes WHERE post_id = ? AND user_id = ?");
    $check->execute([$post_id, $user_id]);
    $existing = $check->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        // Unlike
        $stmt = $conn->prepare("DELETE FROM post_likes WHERE post_id = ? AND user_id = ?");
        $stmt->execute([$post_id, $user_id]);
        $liked = false;
    } else {
        // Like
        $stmt = $conn->prepare("INSERT INTO post_likes (post_id, user_id) VALUES (?, ?)");
        $stmt->execute([$post_id, $user_id]);
        $liked = true;
    }

    // Get updated count
    $count = $conn->prepare("SELECT COUNT(*) FROM post_likes WHERE post_id = ?");
    $count->execute([$post_id]);
    $likesCount = $count->fetchColumn();

    echo json_encode([
        'success' => true,
        'liked' => $liked,
        'likesCount' => $likesCount
    ]);

} catch (Exception $e) {
    error_log('Toggle like error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to toggle like: ' . $e->getMessage()]);
}