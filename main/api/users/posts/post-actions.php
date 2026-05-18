<?php
session_start();

require_once __DIR__ . '/../../../../config/db.php';
require_once __DIR__ . '/../../../../config/ban-check.php';

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Unauthorized';
    exit();
}

$user_id = $_SESSION['user_id'];

// Basic routing
$action = $_POST['action'] ?? '';
$action = is_string($action) ? trim($action) : '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $action === '') {
    http_response_code(405);
    echo 'Method not allowed';
    exit();
}

if (isUserBanned($user_id, $conn)) {
    http_response_code(403);
    echo 'Account banned';
    exit();
}

function redirect_back(string $msg = '', string $type = 'success') : void {
    $params = [];
    if ($msg !== '') $params[] = 'msg=' . urlencode($msg);
    if ($type !== '') $params[] = 'type=' . urlencode($type);
    $qs = $params ? ('?' . implode('&', $params)) : '';
    header('Location: ' . 'main-feed.php' . $qs);
    exit();
}

function get_post_owner(mysqli $conn, int $post_id) : ?string {
    $stmt = $conn->prepare('SELECT user_id FROM posts WHERE post_id = ? AND is_deleted = 0');
    $stmt->bind_param('i', $post_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res || $res->num_rows === 0) return null;
    $row = $res->fetch_assoc();
    return $row['user_id'] ?? null;
}

function handle_edit(mysqli $conn, string $user_id) : void {
    $post_id = intval($_POST['post_id'] ?? 0);
    $content = trim($_POST['content'] ?? '');

    if ($post_id <= 0) redirect_back('Invalid post.', 'error');
    if ($content === '') redirect_back('Post content cannot be empty.', 'error');
    if (mb_strlen($content) > 1000) redirect_back('Content max 1000 characters.', 'error');

    $owner = get_post_owner($conn, $post_id);
    if (!$owner || $owner !== $user_id) {
        http_response_code(403);
        redirect_back('You cannot edit this post.', 'error');
    }

    // Update (content only; keep image upload out for now to avoid changing UI requirements)
    $stmt = $conn->prepare('UPDATE posts SET content = ?, updated_at = NOW() WHERE post_id = ?');
    $stmt->bind_param('si', $content, $post_id);

    if ($stmt->execute()) {
        redirect_back('Post updated successfully.', 'success');
    }

    redirect_back('Update failed.', 'error');
}

function handle_delete(mysqli $conn, string $user_id) : void {
    $post_id = intval($_POST['post_id'] ?? 0);

    if ($post_id <= 0) redirect_back('Invalid post.', 'error');

    $owner = get_post_owner($conn, $post_id);
    if (!$owner || $owner !== $user_id) {
        http_response_code(403);
        redirect_back('You cannot delete this post.', 'error');
    }

    $conn->begin_transaction();
    try {
        $conn->prepare('DELETE FROM post_likes WHERE post_id = ?')->execute([$post_id]);
        $conn->prepare('DELETE FROM shares WHERE post_id = ?')->execute([$post_id]);
        $conn->prepare('DELETE FROM comments WHERE post_id = ?')->execute([$post_id]);
        $conn->prepare('DELETE FROM posts WHERE post_id = ?')->execute([$post_id]);

        $conn->commit();
        redirect_back('Post deleted successfully.', 'success');
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('Delete failed: ' . $e->getMessage());
        redirect_back('Delete failed.', 'error');
    }
}

function handle_report(mysqli $conn, string $user_id) : void {
    $post_id = intval($_POST['post_id'] ?? 0);
    $reason = $_POST['reason'] ?? 'other';
    $description = trim($_POST['description'] ?? '');

    $allowed = ['spam','harassment','inappropriate','fake_news','copyright','other'];
    if (!in_array($reason, $allowed, true)) $reason = 'other';

    if ($post_id <= 0) redirect_back('Invalid post.', 'error');

    $stmt = $conn->prepare('INSERT INTO post_reports (reporter_id, post_id, reason, description, status, admin_action) VALUES (?, ?, ?, ?, "pending", "none")');
    $stmt->bind_param('siss', $user_id, $post_id, $reason, $description);

    if ($stmt->execute()) {
        redirect_back('Post reported. Thank you!', 'success');
    }

    redirect_back('Report failed.', 'error');
}

function handle_request_announce(mysqli $conn, string $user_id) : void {
    $post_id = intval($_POST['post_id'] ?? 0);
    $request_reason = trim($_POST['request_reason'] ?? '');

    if ($post_id <= 0) redirect_back('Invalid post.', 'error');
    if ($request_reason === '') redirect_back('Request reason is required.', 'error');

    // Insert request
    $stmt = $conn->prepare('INSERT INTO announcement_requests (post_id, user_id, request_reason, status, reviewed_by, reviewed_at) VALUES (?, ?, ?, "pending", NULL, NULL)');
    $stmt->bind_param('iis', $post_id, $user_id, $request_reason);

    if ($stmt->execute()) {
        redirect_back('Request submitted! Admins will review your announcement request.', 'success');
    }

    redirect_back('Request failed.', 'error');
}

switch ($action) {
    case 'edit':
        handle_edit($conn, $user_id);
        break;
    case 'delete':
        handle_delete($conn, $user_id);
        break;
    case 'report':
        handle_report($conn, $user_id);
        break;
    case 'request_announce':
        handle_request_announce($conn, $user_id);
        break;
    default:
        redirect_back('Unknown action.', 'error');
        break;
}

