<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$community_id = intval($_POST['community_id'] ?? $_GET['community_id'] ?? 0);
$action = $_POST['action'] ?? $_GET['action'] ?? 'join';

if (!$community_id) {
    echo json_encode(['success' => false, 'error' => 'Community ID required']);
    exit;
}

try {
    if ($action === 'join') {
        // Check if already member
        $check = $conn->prepare("SELECT 1 FROM community_members WHERE community_id = ? AND user_id = ?");
        $check->execute([$community_id, $user_id]);
        if ($check->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Already a member']);
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO community_members (community_id, user_id, role) VALUES (?, ?, 'member')");
        $stmt->execute([$community_id, $user_id]);
        
        echo json_encode(['success' => true, 'message' => 'Joined community!']);

    } else { // leave
        // Prevent creator from leaving
        $check = $conn->prepare("SELECT created_by FROM communities WHERE community_id = ?");
        $check->execute([$community_id]);
        $creator = $check->fetchColumn();
        
        if ($creator === $user_id) {
            echo json_encode(['success' => false, 'error' => 'Creator cannot leave. Delete community instead.']);
            exit;
        }

        $stmt = $conn->prepare("DELETE FROM community_members WHERE community_id = ? AND user_id = ?");
        $stmt->execute([$community_id, $user_id]);
        
        echo json_encode(['success' => true, 'message' => 'Left community']);
    }

} catch (PDOException $e) {
    error_log('Join/leave error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Action failed: ' . $e->getMessage()]);
}