<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

$input = json_decode(file_get_contents('php://input'), true);
$userId = $input['userId'] ?? '';
$postId = $_GET['id'] ?? '';

if (!$userId || !$postId) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'userId and postId required']));
}

// Prevent duplicate likes
$stmt = $pdo->prepare("
    INSERT INTO post_likes (post_id, user_id) 
    VALUES (?, ?) 
    ON DUPLICATE KEY UPDATE created_at = NOW()
");

$stmt->execute([$postId, $userId]);

// Get updated count
$countStmt = $pdo->prepare("SELECT COUNT(*) as count FROM post_likes WHERE post_id = ?");
$countStmt->execute([$postId]);
$count = $countStmt->fetch()['count'];

echo json_encode([
    'success' => true,
    'likesCount' => (int)$count
]);
?>