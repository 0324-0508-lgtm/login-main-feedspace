<?php
session_start();
require_once __DIR__ . '/../../../../config/db.php';
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$_POST = array_merge($_POST, $input ?? []);

$postId = intval($_POST['post_id'] ?? 0);
$page = max(1, intval($_POST['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

if (!$postId || $postId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Valid post_id required']);
    exit();
}

$check_stmt = $conn->prepare("SELECT post_id FROM posts WHERE post_id = ? AND is_deleted = 0");
$check_stmt->execute([$postId]);
if (!$check_stmt->fetch(PDO::FETCH_ASSOC)) {
    http_response_code(404);
    echo json_encode(['error' => 'Post not found']);
    exit();
}

$stmt = $conn->prepare("
    SELECT 
        c.comment_id,
        c.post_id,
        c.user_id,
        c.content,
        c.moderation_status,
        c.toxicity_score,
        c.created_at,
        u.first_name,
        u.last_name,
        u.profile_picture
    FROM comments c
    JOIN users u ON c.user_id = u.user_id
    WHERE c.post_id = ? AND c.moderation_status IN ('approved', 'flagged')
    ORDER BY c.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->bindValue(1, $postId, PDO::PARAM_INT);
$stmt->bindValue(2, (int)$limit, PDO::PARAM_INT);
$stmt->bindValue(3, (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$comments = [];

while ($comment = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $comment['author'] = trim($comment['first_name'] . ' ' . $comment['last_name']);
    $comment['avatar'] = $comment['profile_picture'] ?? 'default.png';
    $comment['created_at'] = date('M d, Y H:i', strtotime($comment['created_at']));
    unset($comment['first_name'], $comment['last_name'], $comment['profile_picture']);
    $comments[] = $comment;
}

$count_stmt = $conn->prepare("
    SELECT COUNT(*) as total FROM comments 
    WHERE post_id = ? AND moderation_status IN ('approved', 'flagged')
");
$count_stmt->execute([$postId]);
$total_comments = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];

echo json_encode([
    'success' => true,
    'comments' => $comments,
    'pagination' => [
        'page' => $page,
        'limit' => $limit,
        'total' => $total_comments,
        'pages' => ceil($total_comments / $limit)
    ]
]);