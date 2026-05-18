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
$comment = trim($data['comment'] ?? '');

if (!$post_id) {
    http_response_code(400);
    echo json_encode(["error" => "Post ID required"]);
    exit;
}

// Verify post exists and is public/approved
$check = $conn->prepare("
    SELECT post_id, content, file_url, user_id 
    FROM posts 
    WHERE post_id = ? AND is_deleted = 0 AND status = 'approved' AND visibility = 'public'
");
$check->bind_param("i", $post_id);
$check->execute();
$post = $check->get_result()->fetch_assoc();

if (!$post) {
    http_response_code(404);
    echo json_encode(["error" => "Post not available for sharing"]);
    exit;
}

// Record share
$stmt = $conn->prepare("INSERT INTO shares (post_id, user_id) VALUES (?, ?)");
$stmt->bind_param("is", $post_id, $user_id);

if ($stmt->execute()) {
    // Create shared post
    $shared_content = $comment ? $comment . "\n\n[Shared post]" : "[Shared post]";
    $insert = $conn->prepare("
        INSERT INTO posts (user_id, content, file_url, file_type, status, visibility) 
        VALUES (?, ?, ?, 'none', 'approved', 'public')
    ");
    $insert->bind_param("sss", $user_id, $shared_content, $post['file_url']);
    $insert->execute();
    
    echo json_encode(["success" => true, "message" => "Post shared"]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Share failed"]);
}
?>