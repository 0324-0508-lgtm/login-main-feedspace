<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$community_id = intval($input['community_id'] ?? 0);

if (!$community_id) {
    echo json_encode(['success' => false, 'error' => 'Community ID required']);
    exit();
}

require_once __DIR__ . '/../../../config/db.php';

// Verify user is creator or admin
$checkStmt = $conn->prepare("SELECT created_by FROM communities WHERE community_id = ?");
$checkStmt->execute([$community_id]);
$created_by = $checkStmt->fetchColumn();

$isCreator = ((string)$created_by === (string)$user_id);

if (!$isCreator) {
    $roleStmt = $conn->prepare("SELECT role FROM community_members WHERE community_id = ? AND user_id = ? AND role = 'admin'");
    $roleStmt->execute([$community_id, $user_id]);
    if (!$roleStmt->fetchColumn()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Only the creator or admin can delete this community']);
        exit();
    }
}

try {
    $stmt = $conn->prepare("UPDATE communities SET is_active = 0 WHERE community_id = ?");
    $stmt->execute([$community_id]);
    
    echo json_encode(['success' => true, 'message' => 'Community deleted']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}