<?php
// TEMP DEBUG: notifications debug endpoint
// Usage: visit /temp_notifications_debug.php?uid=0324-0501

session_start();

$userId = $_GET['uid'] ?? ($_SESSION['user_id'] ?? null);

header('Content-Type: application/json; charset=utf-8');

include 'config/db.php';

if (!$userId) {
  echo json_encode(['ok' => false, 'reason' => 'No user id', 'session_user_id' => $_SESSION['user_id'] ?? null]);
  exit;
}

$stmt = $conn->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN is_read=0 THEN 1 ELSE 0 END) as unread FROM notifications WHERE receiver_user_id = ? AND receiver_type='user'");
$stmt->bind_param('s', $userId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

$recentStmt = $conn->prepare("SELECT id, message, is_read, created_at, receiver_user_id, receiver_type FROM notifications WHERE receiver_user_id = ? AND receiver_type='user' ORDER BY created_at DESC LIMIT 5");
$recentStmt->bind_param('s', $userId);
$recentStmt->execute();
$recent = [];
$r = $recentStmt->get_result();
while ($x = $r->fetch_assoc()) $recent[] = $x;
$recentStmt->close();

echo json_encode([
  'ok' => true,
  'userId_used' => $userId,
  'session_user_id' => $_SESSION['user_id'] ?? null,
  'counts' => $row,
  'recent' => $recent
]);

