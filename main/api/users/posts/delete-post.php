<?php
error_reporting(0);
ini_set('display_errors', 0);

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../../../config/db.php';
require_once __DIR__ . '/../../../../config/ban-check.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "Not logged in"]);
    exit;
}

$user_id = $_SESSION['user_id'];

if (isUserBanned($user_id, $conn)) {
    http_response_code(403);
    echo json_encode(["success" => false, "error" => "You are banned"]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$post_id = (int)($data['post_id'] ?? 0);

if (!$post_id) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Post ID required"]);
    exit;
}

// DEBUG: Log what we're checking
error_log("DEBUG: User $user_id trying to delete post $post_id");

// Get post owner - use explicit column name
$check = $conn->prepare("SELECT user_id FROM posts WHERE post_id = ? AND is_deleted = 0");
$check->execute([$post_id]);
$post = $check->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    http_response_code(404);
    echo json_encode(["success" => false, "error" => "Post not found"]);
    exit;
}

// DEBUG: Log what we found
error_log("DEBUG: Post owner is: " . ($post['user_id'] ?? 'NULL') . ", Current user: $user_id");

// Check role
$role_check = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
$role_check->execute([$user_id]);
$user = $role_check->fetch(PDO::FETCH_ASSOC);

$is_admin = in_array($user['role'] ?? '', ['Admin', 'Staff', 'admin', 'staff']);

// FIX: Use loose comparison (==) instead of strict (!==) since types may differ
// Also trim both values to be safe
$post_owner = trim($post['user_id'] ?? '');
$current_user = trim($user_id);

error_log("DEBUG: Comparing '$post_owner' == '$current_user' OR admin=" . ($is_admin ? 'yes' : 'no'));

if ($post_owner != $current_user && !$is_admin) {
    http_response_code(403);
    echo json_encode(["success" => false, "error" => "Not authorized"]);
    exit;
}

// Soft delete
$stmt = $conn->prepare("UPDATE posts SET is_deleted = 1, deleted_at = NOW() WHERE post_id = ?");
if ($stmt->execute([$post_id])) {
    echo json_encode(["success" => true, "message" => "Post deleted"]);
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Delete failed"]);
}
?>