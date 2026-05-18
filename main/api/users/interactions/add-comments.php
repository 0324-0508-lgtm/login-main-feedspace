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

// Verify post exists and is not deleted
$check = $conn->prepare("SELECT post_id FROM posts WHERE post_id = ? AND is_deleted = 0");
$check->bind_param("i", $post_id);
$check->execute();
if (!$check->get_result()->num_rows) {
    http_response_code(404);
    echo json_encode(["error" => "Post not found"]);
    exit;
}

$stmt = $conn->prepare("INSERT INTO comments (post_id, user_id, content) VALUES (?, ?, ?)");
$stmt->bind_param("iss", $post_id, $user_id, $content);

if ($stmt->execute()) {
    $comment_id = $stmt->insert_id;
    
    // Get comment with user info
    $get = $conn->prepare("
        SELECT c.*, u.first_name, u.last_name, u.profile_picture 
        FROM comments c 
        JOIN users u ON c.user_id = u.user_id 
        WHERE c.comment_id = ?
    ");
    $get->bind_param("i", $comment_id);
    $get->execute();
    $result = $get->get_result()->fetch_assoc();
    
    echo json_encode(["success" => true, "comment" => $result]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Failed to add comment"]);
}
?>