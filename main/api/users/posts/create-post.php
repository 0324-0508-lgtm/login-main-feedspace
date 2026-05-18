<?php
// Ensure we never output HTML/PHP warnings into the JSON response.
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

session_start();

error_log('SESSION on create-post: ' . print_r($_SESSION, true));

require_once __DIR__ . '/../../../../config/db.php';
require_once __DIR__ . '/../../../../config/ban-check.php';

header('Content-Type: application/json; charset=utf-8');

$fail = function(string $message, int $code = 500, array $extra = []) {
    if (!headers_sent()) {
        http_response_code($code);
    }
    $payload = array_merge(['success' => false, 'error' => $message], $extra);
    if (ob_get_length()) ob_clean();
    echo json_encode($payload);
    exit();
};

try {
    // ---- Auth ----
    $user_id = $_SESSION['user_id'] ?? '';
    if (!is_string($user_id)) {
        $user_id = '';
    }
    $user_id = trim($user_id);

    if ($user_id === '') {
        $fail('Unauthorized', 401, [
            'debug' => [
                'session_id' => session_id(),
                'session_user_id_type' => gettype($_SESSION['user_id'] ?? null),
                'session_user_id' => $_SESSION['user_id'] ?? 'NOT SET',
            ]
        ]);
    }

    if (function_exists('isUserBanned') && isUserBanned($user_id, $conn)) {
        $fail('Account banned', 403);
    }

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        $fail('Method not allowed: ' . ($_SERVER['REQUEST_METHOD'] ?? 'NONE'), 405);
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
            $fail('Image upload failed', 400);
        }

        $mime = $file['type'] ?? '';
        if ($mime && !in_array($mime, $allowed_types, true)) {
            $fail('Invalid image type', 400);
        }

        $size = intval($file['size'] ?? 0);
        if ($size <= 0 || $size > $max_size) {
            $fail('Image too large', 400);
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
            $fail('Failed to save image', 400);
        }

        $image = $image_name;
    }

    if ($content === '' && $image === null) {
        $fail('Content or image required', 400);
    }

    if ($content !== '' && mb_strlen($content) > 1000) {
        $fail('Content max 1000 characters', 400);
    }

    // ---- Insert (PDO) ----
    $file_url = $image ?: null;
    $file_type = $image ? 'image' : 'none';
    $visibility = 'public';
    $status = 'approved';
    $ai_status = 'safe';
    $ai_score = null;
    $ai_reason = null;
    $is_archived = 0;
    $is_deleted = 0;
    $is_announcement = 0;
    $community_id = null;

    $sql = "INSERT INTO posts (
        user_id, community_id, content, file_url, file_type, visibility, status,
        is_archived, is_deleted, ai_score, ai_status, ai_reason, is_announcement
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $fail('Failed to prepare statement', 500);
    }

    $stmt->execute([
        $user_id,
        $community_id,
        $content,
        $file_url,
        $file_type,
        $visibility,
        $status,
        $is_archived,
        $is_deleted,
        $ai_score,
        $ai_status,
        $ai_reason,
        $is_announcement
    ]);

    echo json_encode(['success' => true, 'post_id' => (int)$conn->lastInsertId()]);
    exit();

}
catch (Throwable $e) {
    if (ob_get_length()) ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error', 'exception' => $e->getMessage()]);
    exit();
}

$fail('Failed to create post', 500);