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
$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$college = $_POST['college'] ?? '';

if (!$community_id || !$name || !$college) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

try {
    // Verify user is admin of this community
    $adminCheck = $db->prepare("SELECT role FROM community_members WHERE community_id = ? AND user_id = ? AND role = 'admin'");
    $adminCheck->execute([$community_id, $user_id]);
    
    if (!$adminCheck->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Only admins can edit this community']);
        exit;
    }

    // Update community
    $stmt = $db->prepare("UPDATE communities SET name = ?, description = ?, college = ? WHERE community_id = ?");
    $stmt->execute([$name, $description, $college, $community_id]);
    
    echo json_encode(['success' => true, 'message' => 'Community updated']);

} catch (PDOException $e) {
    error_log('Edit community error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}