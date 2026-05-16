<?php
// Toggle like for a post
session_start();
include '../../config/db.php';

header('Content-Type: application/json');
// Check if user is logged in
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$post_id = intval($_POST['post_id'] ?? 0);
$user_id = $_SESSION['user_id'];

if ($post_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid post ID']);
    exit();
}

// Toggle like
$stmt = $conn->prepare("
    INSERT INTO post_likes (post_id, user_id) 
    VALUES (?, ?) 
    ON DUPLICATE KEY UPDATE id = id
");
$stmt->bind_param("is", $post_id, $user_id);
$stmt->execute();
// Check if like was added or removed
$is_liked = $stmt->affected_rows === 1;
$action = $is_liked ? 'liked' : 'unliked';

// Get new count
$count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM post_likes WHERE post_id = ?");
$count_stmt->bind_param("i", $post_id);
$count_stmt->execute();
$count = $count_stmt->get_result()->fetch_assoc()['count'];
// Return response
echo json_encode([
    'success' => true,
    'action' => $action,
    'is_liked' => $is_liked,
    'like_count' => $count
]);
?>