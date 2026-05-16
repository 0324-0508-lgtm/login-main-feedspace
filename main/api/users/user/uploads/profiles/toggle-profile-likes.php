<?php
session_start();
include '../../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$liked_user_id = $_POST['liked_user_id'] ?? '';
$user_id = $_SESSION['user_id'];

if (empty($liked_user_id) || $liked_user_id === $user_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid user']);
    exit();
}

// Toggle like (INSERT or DELETE)
$stmt = $conn->prepare("
    INSERT INTO user_likes (user_id, liked_user_id) 
    VALUES (?, ?) 
    ON DUPLICATE KEY UPDATE id = id
");
$stmt->bind_param("ss", $user_id, $liked_user_id);
$stmt->execute();

$is_liked = $stmt->affected_rows === 1;
$action = $is_liked ? 'liked' : 'unliked';

echo json_encode([
    'success' => true,
    'action' => $action,
    'liked_user_id' => $liked_user_id,
    'is_liked' => $is_liked
]);
?>