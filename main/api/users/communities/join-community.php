<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

$paths = [
    __DIR__ . '/../../../config/db.php',
    __DIR__ . '/../../../../config/db.php',
    __DIR__ . '/../../config/db.php',
];

$configLoaded = false;
foreach ($paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $configLoaded = true;
        break;
    }
}

if (!$configLoaded) {
    echo json_encode(['success' => false, 'error' => 'db.php not found']);
    exit;
}

$db = $conn ?? $pdo ?? null;
if (!$db) {
    echo json_encode(['success' => false, 'error' => 'No DB connection']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$community_id = intval($_POST['community_id'] ?? 0);
$action = $_POST['action'] ?? '';

if (!$community_id || !in_array($action, ['join', 'leave'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

try {
    if ($action === 'join') {
        $check = $db->prepare("SELECT 1 FROM community_members WHERE community_id = ? AND user_id = ?");
        $check->execute([$community_id, $user_id]);
        
        if ($check->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Already a member']);
            exit;
        }
        
        $stmt = $db->prepare("INSERT INTO community_members (community_id, user_id, role, joined_at) VALUES (?, ?, 'member', NOW())");
        $stmt->execute([$community_id, $user_id]);
        
        echo json_encode(['success' => true, 'message' => 'Joined successfully']);
        
    } else {
        $stmt = $db->prepare("DELETE FROM community_members WHERE community_id = ? AND user_id = ?");
        $stmt->execute([$community_id, $user_id]);
        
        echo json_encode(['success' => true, 'message' => 'Left successfully']);
    }
    
} catch (PDOException $e) {
    error_log('Join/leave error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}