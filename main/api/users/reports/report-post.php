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
$reason = $data['reason'] ?? '';
$description = trim($data['description'] ?? '');

$valid_reasons = ['spam', 'harassment', 'inappropriate', 'fake_news', 'copyright', 'other'];

if (!$post_id || !in_array($reason, $valid_reasons)) {
    http_response_code(400);
    echo json_encode(["error" => "Valid post ID and reason required"]);
    exit;
}

// Get post owner
$check = $conn->prepare("SELECT user_id FROM posts WHERE post_id = ? AND is_deleted = 0");
$check->bind_param("i", $post_id);
$check->execute();
$post = $check->get_result()->fetch_assoc();

if (!$post) {
    http_response_code(404);
    echo json_encode(["error" => "Post not found"]);
    exit;
}

// Can't report your own post
if ($post['user_id'] === $user_id) {
    http_response_code(400);
    echo json_encode(["error" => "Cannot report your own post"]);
    exit;
}

$stmt = $conn->prepare("
    INSERT INTO post_reports (reporter_id, post_id, reason, description) 
    VALUES (?, ?, ?, ?)
");
$stmt->bind_param("siss", $user_id, $post_id, $reason, $description);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Report submitted"]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Report failed"]);
}
?>