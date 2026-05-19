<?php
session_start();
require_once '../../../../config/db.php';
require_once '../../../../config/session.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$currentUserId = $_SESSION['user_id'];

$input = json_decode(file_get_contents('php://input'), true);
$targetUserId = $input['target_user_id'] ?? null;
$action       = $input['action'] ?? 'follow'; // 'follow' or 'unfollow'

if (!$targetUserId) {
    echo json_encode(['success' => false, 'error' => 'Missing target_user_id']);
    exit;
}

if ($targetUserId === $currentUserId) {
    echo json_encode(['success' => false, 'error' => 'You cannot follow yourself']);
    exit;
}

try {
    if ($action === 'follow') {
        $stmt = $conn->prepare("
            INSERT IGNORE INTO followers (follower_id, following_id)
            VALUES (?, ?)
        ");
        $stmt->execute([$currentUserId, $targetUserId]);
        echo json_encode(['success' => true, 'message' => 'Now following!']);

    } elseif ($action === 'unfollow') {
        $stmt = $conn->prepare("
            DELETE FROM followers
            WHERE follower_id = ? AND following_id = ?
        ");
        $stmt->execute([$currentUserId, $targetUserId]);
        echo json_encode(['success' => true, 'message' => 'Unfollowed']);

    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}