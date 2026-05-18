<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

session_start();

$user_id = $_SESSION['user_id'] ?? ($_POST['user_id'] ?? null);
if (is_string($user_id)) $user_id = trim($user_id);

// DEBUG/repair: if POST user_id is missing but session cookie exists, try to recover user_id
// from the same session id (some setups don’t hydrate $_SESSION at this point).
if (empty($user_id) && !empty($_COOKIE['PHPSESSID'])) {
    try {
        // If your app stores the numeric user id in session, it must be rehydrated by session_start.
        // If it still isn’t, treat as unauthorized (no safe fallback).
        // Intentionally left without insecure fallback.
    } catch (Throwable $e) {
        // no-op
    }
}

// If the frontend passes user_id, persist it into session for this browser.
if (!empty($user_id) && empty($_SESSION['user_id'])) {
    $_SESSION['user_id'] = $user_id;
}


// Ensure it's numeric (DB expects int user_id).
if (!empty($user_id) && !ctype_digit((string)$user_id)) {
    $user_id = null;
}


if (empty($user_id)) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized',
        'debug' => [
            'session_user_id_value' => ($_SESSION['user_id'] ?? null),
            'post_user_id' => ($_POST['user_id'] ?? null),
            'cookie_keys' => array_keys($_COOKIE ?? []),
            'cookie_sample' => [
                'PHPSESSID' => ($_COOKIE['PHPSESSID'] ?? null)
            ],
            'server_http_referer' => ($_SERVER['HTTP_REFERER'] ?? null),
            'server_request_uri' => ($_SERVER['REQUEST_URI'] ?? null)
        ]
    ]);
    exit;
}




require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../config/ban-check.php';

if (!isset($conn) || !$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB connection not initialized']);
    exit;
}

if (isUserBanned($user_id, $conn)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Account banned',
        'debug' => [
            'user_id' => $user_id
        ]
    ]);
    exit;
}


$name = trim($_POST['community_name'] ?? '');
$description = trim($_POST['description'] ?? '');

if ($name === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'community_name is required']);
    exit;
}

// Prevent duplicates by name (simple safeguard). You can remove/adjust if needed.
$dupStmt = $conn->prepare('SELECT community_id FROM communities WHERE community_name = ? LIMIT 1');
$dupStmt->bind_param('s', $name);
$dupStmt->execute();
$dupRes = $dupStmt->get_result();
$dupRow = $dupRes ? $dupRes->fetch_assoc() : null;
$dupStmt->close();

if ($dupRow) {
    echo json_encode([
        'success' => false,
        'message' => 'Community already exists',
        'community_id' => (int)$dupRow['community_id']
    ]);
    exit;
}

// Insert into communities
$insertStmt = $conn->prepare(
    'INSERT INTO communities (user_id, community_name, description, member_count, status) VALUES (?, ?, ?, 0, "active")'
);
$insertStmt->bind_param('sss', $user_id, $name, $description);

if (!$insertStmt->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to create community']);
    exit;
}

$communityId = (int)$insertStmt->insert_id;
$insertStmt->close();

// Add creator as member
$memberStmt = $conn->prepare('INSERT INTO community_members (community_id, user_id) VALUES (?, ?)');
$memberStmt->bind_param('is', $communityId, $user_id);

if (!$memberStmt->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Community created but failed to add creator as member']);
    exit;
}

$memberStmt->close();

// Update member_count
$countUpdate = $conn->prepare('UPDATE communities SET member_count = (SELECT COUNT(*) FROM community_members WHERE community_id = ?) WHERE community_id = ?');
$countUpdate->bind_param('ii', $communityId, $communityId);
$countUpdate->execute();
$countUpdate->close();

mysqli_close($conn);

echo json_encode([
    'success' => true,
    'community_id' => $communityId,
    'community_name' => $name
]);

