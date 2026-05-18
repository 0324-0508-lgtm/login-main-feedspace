<?php
session_start();

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
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
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
// Your provided assumption: posts table has: id, user_id, content, created_at, like_count, comment_count
// Your actual dump uses: post_id, file_url, status, ai_status, etc.
// We'll insert into the columns that exist in your actual dump.
// Strategy: try the full schema first (post_id + file_url + status/ai_status), then fallback to minimal.

// Determine values
$file_url = $image ? $image : null;
$file_type = $image ? 'image' : 'none';

// Default moderation fields (safe)
$status = 'approved';
$ai_status = 'safe';
$ai_score = 0.0;
$ai_reason = null;
$is_archived = 0;
$is_deleted = 0;
$visibility = 'public';
$is_announcement = 0;
$community_id = null;

// Full-schema insert (matches your provided DB dump)
$insertSqlFull = "INSERT INTO posts 
    (user_id, content, file_url, file_type, visibility, status, ai_status, ai_score, ai_reason, created_at, updated_at, is_archived, is_deleted, is_announcement, community_id)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?, ?, ?)";

$stmt = $conn->prepare($insertSqlFull);

if ($stmt) {
    // Types: s(user_id) s(content) s(file_url) s(file_type) s(visibility) s(status) s(ai_status) d(ai_score) s(ai_reason) i(is_archived) i(is_deleted) i(is_announcement) i(community_id)
    $community_id_int = $community_id === null ? 0 : (int)$community_id;

    // mysqli bind_param cannot bind NULL for an 's' param unless we pass null.
    // ai_reason may be null.
    $stmt->bind_param(
        'sssssssdssiiii',
        $user_id,
        $content,
        $file_url,
        $file_type,
        $visibility,
        $status,
        $ai_status,
        $ai_score,
        $ai_reason,
        $is_archived,
        $is_deleted,
        $is_announcement,
        $community_id_int
    );

    if ($stmt->execute()) {
        $postId = $conn->insert_id;
        echo json_encode(['success' => true, 'post_id' => (int)$postId]);
        exit();
    }
}

// Fallback minimal insert (if your schema differs)
$insertSqlMin = "INSERT INTO posts (user_id, content, created_at) VALUES (?, ?, NOW())";
$stmtMin = $conn->prepare($insertSqlMin);
if ($stmtMin) {
    $stmtMin->bind_param('ss', $user_id, $content);
    if ($stmtMin->execute()) {
        $postId = $conn->insert_id;
        echo json_encode(['success' => true, 'post_id' => (int)$postId]);
        exit();
    }
}

http_response_code(500);
echo json_encode(['success' => false, 'error' => 'Failed to create post']);
exit();


