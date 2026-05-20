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

try {
    $sql = "
        SELECT 
            c.community_id,
            c.name,
            c.description,
            c.college,
            c.icon,
            (SELECT COUNT(*) FROM community_members WHERE community_id = c.community_id) as member_count,
            EXISTS(SELECT 1 FROM community_members WHERE community_id = c.community_id AND user_id = ?) as is_member
        FROM communities c
        WHERE c.is_active = 1
        ORDER BY c.created_at DESC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$user_id]);
    $communities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($communities as &$c) {
        $c['id'] = $c['community_id'];
        $c['community_name'] = $c['name'];
        $c['is_member'] = (bool)$c['is_member'];
        $c['member_count'] = (int)$c['member_count'];
    }

    echo json_encode(['success' => true, 'communities' => $communities]);

} catch (PDOException $e) {
    error_log('Get communities error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'DB error: ' . $e->getMessage()]);
}