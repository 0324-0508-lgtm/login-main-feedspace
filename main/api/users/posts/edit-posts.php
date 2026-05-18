<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../config/ban-check.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Not logged in"]);
    exit;
}

$user_id = $_SESSION['user_id'];

if (isUserBanned($user_id, $conn)) {
    http_response_code(403);
    echo json_encode(["error" => "You are banned"]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$post_id = (int)($data['post_id'] ?? 0);
$content = trim($data['content'] ?? '');

if (!$post_id || empty($content)) {
    http_response_code(400);
    echo json_encode(["error" => "Post ID and content required"]);
    exit;
}

// Verify ownership
$check = $conn->prepare("SELECT user_id FROM posts WHERE post_id = ? AND is_deleted = 0");
$check->bind_param("i", $post_id);
$check->execute();
$post = $check->get_result()->fetch_assoc();

if (!$post) {
    http_response_code(404);
    echo json_encode(["error" => "Post not found"]);
    exit;
}

if ($post['user_id'] !== $user_id) {
    http_response_code(403);
    echo json_encode(["error" => "Not your post"]);
    exit;
}

$stmt = $conn->prepare("UPDATE posts SET content = ? WHERE post_id = ?");
$stmt->bind_param("si", $content, $post_id);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Post updated"]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Update failed"]);
}
?>