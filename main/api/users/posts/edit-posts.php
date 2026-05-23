<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../../../config/db.php';
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

// Read POST data (JS sends x-www-form-urlencoded)
$post_id = intval($_POST['post_id'] ?? 0);
$content = trim($_POST['content'] ?? '');

if (!$post_id || empty($content)) {
    http_response_code(400);
    echo json_encode(["error" => "Post ID and content required"]);
    exit;
}

// Verify ownership
$check = $conn->prepare("SELECT user_id FROM posts WHERE post_id = ? AND is_deleted = 0");
$check->execute([$post_id]);
$post = $check->fetch(PDO::FETCH_ASSOC);

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

// Update post
$stmt = $conn->prepare("UPDATE posts SET content = ? WHERE post_id = ?");
if ($stmt->execute([$content, $post_id])) {
    echo json_encode(["success" => true, "message" => "Post updated"]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Update failed"]);
}