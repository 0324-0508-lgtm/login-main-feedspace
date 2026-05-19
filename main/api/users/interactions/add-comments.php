<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

$data = json_decode(file_get_contents('php://input'), true);
$post_id = (int)($data['post_id'] ?? 0);
$content = trim((string)($data['content'] ?? ''));

if (!$post_id || $content === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Post ID and content required']);
    exit;
}

try {
    // Verify post exists
    $check = $conn->prepare('SELECT post_id FROM posts WHERE post_id = ? AND is_deleted = 0');
    $check->execute([$post_id]);
    if (!$check->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Post not found']);
        exit;
    }

    // Insert comment
    $stmt = $conn->prepare('INSERT INTO comments (post_id, user_id, content, moderation_status) VALUES (?, ?, ?, ?)');
    $ok = $stmt->execute([$post_id, $user_id, $content, 'approved']);

    if (!$ok) {
        throw new Exception('Insert failed: ' . implode(', ', $stmt->errorInfo()));
    }

    $comment_id = (int)$conn->lastInsertId();

    if (!$comment_id) {
        throw new Exception('Failed to get last insert ID');
    }

    // Fetch the inserted comment with user info
    $get = $conn->prepare(
        'SELECT c.*, u.first_name, u.last_name, u.profile_picture
         FROM comments c
         JOIN users u ON c.user_id = u.user_id
         WHERE c.comment_id = ?'
    );
    $get->execute([$comment_id]);
    $result = $get->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        throw new Exception('Comment inserted but fetch failed');
    }

    // Get comment count
    $countStmt = $conn->prepare("SELECT COUNT(*) FROM comments WHERE post_id = ? AND moderation_status IN ('approved', 'flagged')");
    $countStmt->execute([$post_id]);
    $comment_count = $countStmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'comment' => $result,
        'moderation_status' => 'approved',
        'text' => $result['content'] ?? '',
        'author' => trim(($result['first_name'] ?? '') . ' ' . ($result['last_name'] ?? '')),
        'avatar' => $result['profile_picture'] ?? null,
        'comment_count' => $comment_count
    ]);

} catch (Exception $e) {
    error_log('Add comment error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to add comment: ' . $e->getMessage()]);
}