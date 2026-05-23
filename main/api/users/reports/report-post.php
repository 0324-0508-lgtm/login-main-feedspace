<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    header('Content-Type: application/json');
    echo json_encode([
        "success" => false,
        "error" => "PHP Error [$errno]: $errstr",
        "file" => basename($errfile),
        "line" => $errline
    ]);
    exit;
});

set_exception_handler(function($e) {
    header('Content-Type: application/json');
    echo json_encode([
        "success" => false,
        "error" => "Exception: " . $e->getMessage(),
        "file" => basename($e->getFile()),
        "line" => $e->getLine()
    ]);
    exit;
});

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
$reason = $data['reason'] ?? '';
$description = trim($data['description'] ?? '');

$valid_reasons = ['spam', 'harassment', 'inappropriate', 'fake_news', 'copyright', 'other'];

if (!$post_id) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Post ID is required"]);
    exit;
}

if (empty($reason) || !in_array($reason, $valid_reasons)) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Please select a valid reason"]);
    exit;
}

if ($reason === 'other' && empty($description)) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Description required for 'Other'"]);
    exit;
}

try {
    $check = $conn->prepare("SELECT user_id FROM posts WHERE post_id = ? AND is_deleted = 0");
    $check->execute([$post_id]);
    $post = $check->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "error" => "Database error: " . $e->getMessage()]);
    exit;
}

if (!$post) {
    http_response_code(404);
    echo json_encode(["success" => false, "error" => "Post not found"]);
    exit;
}

if ($post['user_id'] == $user_id) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Cannot report your own post"]);
    exit;
}

try {
    $dup_check = $conn->prepare("SELECT report_id FROM post_reports WHERE reporter_id = ? AND post_id = ?");
    $dup_check->execute([$user_id, $post_id]);
    if ($dup_check->rowCount() > 0) {
        http_response_code(409);
        echo json_encode(["success" => false, "error" => "You have already reported this post"]);
        exit;
    }
} catch (PDOException $e) {
    echo json_encode(["success" => false, "error" => "Database error: " . $e->getMessage()]);
    exit;
}

try {
    $stmt = $conn->prepare("
        INSERT INTO post_reports (reporter_id, post_id, reason, description) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$user_id, $post_id, $reason, $description]);
    
    echo json_encode(["success" => true, "message" => "Report submitted successfully"]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Report failed: " . $e->getMessage()]);
}
?>