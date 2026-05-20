<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Try multiple paths to find db.php
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

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$name = trim($input['community_name'] ?? $input['name'] ?? '');
$description = trim($input['description'] ?? '');
$college = trim($input['college'] ?? '');
$icon = trim($input['icon'] ?? 'users');

if (empty($name)) {
    echo json_encode(['success' => false, 'error' => 'Community name is required']);
    exit;
}

if (empty($college)) {
    echo json_encode(['success' => false, 'error' => 'College is required']);
    exit;
}

try {
    // Check duplicate
    $check = $db->prepare("SELECT 1 FROM communities WHERE name = ?");
    $check->execute([$name]);
    if ($check->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Name already exists']);
        exit;
    }

    // Insert community (using your actual columns: name, not community_name)
    $stmt = $db->prepare("
        INSERT INTO communities (name, description, college, icon, created_by, status, is_active) 
        VALUES (?, ?, ?, ?, ?, 'active', 1)
    ");
    $stmt->execute([$name, $description, $college, $icon, $user_id]);
    
    $communityId = $db->lastInsertId();

    // Add creator as admin
    $memberStmt = $db->prepare("
        INSERT INTO community_members (community_id, user_id, role) 
        VALUES (?, ?, 'admin')
    ");
    $memberStmt->execute([$communityId, $user_id]);

    echo json_encode([
        'success' => true,
        'message' => 'Community created!',
        'community_id' => $communityId,
        'name' => $name
    ]);

} catch (PDOException $e) {
    error_log('Create community error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}