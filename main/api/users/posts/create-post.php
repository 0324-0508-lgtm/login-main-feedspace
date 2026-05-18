<?php
session_start();

error_log('SESSION on create-post: ' . print_r($_SESSION, true));


require_once __DIR__ . '/../../../../config/db.php';
require_once __DIR__ . '/../../../../config/ban-check.php';

header('Content-Type: application/json; charset=utf-8');

// ---- Auth ----
$user_id = $_SESSION['user_id'] ?? '';
if (!is_string($user_id)) {
    $user_id = '';
}
$user_id = trim($user_id);

if ($user_id === '') {
    http_response_code(401);
    // Return debug info to identify missing session cookie / user_id.
    // Remove or disable after confirming the root cause.
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized',
        'debug' => [
            'session_id' => session_id(),
            'session_user_id_type' => is_array($_SESSION['user_id'] ?? null) ? 'array' : gettype($_SESSION['user_id'] ?? null),
            'session_user_id' => is_scalar($_SESSION['user_id'] ?? null) ? (string)($_SESSION['user_id'] ?? '') : '',
            'cookies' => $_COOKIE,
            'server_script' => $_SERVER['SCRIPT_NAME'] ?? null,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? null
        ]
    ]);
    exit();
}


if (function_exists('isUserBanned') && isUserBanned($user_id, $conn)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Account banned']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

// ---- Input ----
$content = trim($_POST['content'] ?? '');

$image = null;
$image_name = null;

if (isset($_FILES['image']) && is_array($_FILES['image']) && !empty($_FILES['image']['name'])) {
    $file = $_FILES['image'];

    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024; // 5MB

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Image upload failed']);
        exit();
    }

    $mime = $file['type'] ?? '';
    if ($mime && !in_array($mime, $allowed_types, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid image type']);
        exit();
    }

    $size = intval($file['size'] ?? 0);
    if ($size <= 0 || $size > $max_size) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Image too large']);
        exit();
    }

    $uploadDir = __DIR__ . '/../../../../uploads/posts/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $safeUserId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$user_id);
    $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    if ($ext === '') {
        $ext = 'jpg';
    }

    $image_name = ($safeUserId !== '' ? $safeUserId : 'anon') . '_' . time() . '.' . $ext;
    $dest = $uploadDir . $image_name;

    if (!move_uploaded_file((string)$file['tmp_name'], $dest)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Failed to save image']);
        exit();
    }

    $image = $image_name;
}

if ($content === '' && $image === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Content or image required']);
    exit();
}

if ($content !== '' && mb_strlen($content) > 1000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Content max 1000 characters']);
    exit();
}

// ---- Insert ----
// ---- Insert ----
$file_url        = $image ?: null;
$file_type       = $image ? 'image' : 'none';
$visibility      = 'public';
$status          = 'approved';
$ai_status       = 'safe';
$ai_score        = null;
$ai_reason       = null;
$is_archived     = 0;
$is_deleted      = 0;
$is_announcement = 0;
$community_id    = null;

$sql = "INSERT INTO posts 
    (user_id, community_id, content, file_url, file_type, visibility, status, 
     is_archived, is_deleted, ai_score, ai_status, ai_reason, is_announcement)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
$stmt->bind_param(
    'sisssssiidssi',
    $user_id,         // s
    $community_id,    // i  ← NULL int
    $content,         // s
    $file_url,        // s
    $file_type,       // s
    $visibility,      // s
    $status,          // s
    $is_archived,     // i
    $is_deleted,      // i
    $ai_score,        // d
    $ai_status,       // s
    $ai_reason,       // s
    $is_announcement  // i
);

if ($stmt->execute()) {
    $postId = $conn->insert_id;
    echo json_encode(['success' => true, 'post_id' => (int)$postId]);
    exit();
}

http_response_code(500);
echo json_encode(['success' => false, 'error' => 'Failed to create post', 'db_error' => $conn->error]);
exit();


