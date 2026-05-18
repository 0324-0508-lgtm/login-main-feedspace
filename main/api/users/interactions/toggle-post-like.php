<?php
session_start();
include '../../config/db.php';
include '../../config/ban-check.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['error' => 'Unauthorized']);
  exit();
}

if (isUserBanned($_SESSION['user_id'], $conn)) {
  http_response_code(403);
  echo json_encode(['error' => 'Account banned']);
  exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'message' => 'Method not allowed']);
  exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$post_id = intval($input['post_id'] ?? 0);

if ($post_id <= 0) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'post_id required']);
  exit();
}

$user_id = intval($_SESSION['user_id']);

// Check if already liked
$checkStmt = $conn->prepare('SELECT 1 FROM post_likes WHERE post_id = ? AND user_id = ? LIMIT 1');
$checkStmt->bind_param('ii', $post_id, $user_id);
$checkStmt->execute();
$checkStmt->store_result();
$is_liked = $checkStmt->num_rows > 0;
$checkStmt->close();

if ($is_liked) {
  $del = $conn->prepare('DELETE FROM post_likes WHERE post_id = ? AND user_id = ?');
  $del->bind_param('ii', $post_id, $user_id);
  $del->execute();
  $del->close();
  $action = 'unliked';
} else {
  $ins = $conn->prepare('INSERT INTO post_likes (post_id, user_id) VALUES (?, ?)');
  $ins->bind_param('ii', $post_id, $user_id);
  $ins->execute();
  $ins->close();
  $action = 'liked';
}

$countStmt = $conn->prepare('SELECT COUNT(*) as cnt FROM post_likes WHERE post_id = ?');
$countStmt->bind_param('i', $post_id);
$countStmt->execute();
$count = $countStmt->get_result()->fetch_assoc()['cnt'];
$countStmt->close();

echo json_encode([
  'success' => true,
  'action' => $action,
  'is_liked' => ($action === 'liked'),
  'likesCount' => (int)$count,
  'post_id' => $post_id
]);
?>
