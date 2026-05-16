<?php
// get-comments.php - Retrieve comments for a post with moderation filtering

session_start();
include '../../../config/db.php';

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

$postId = intval($_POST['post_id'] ?? 0);
$page = max(1, intval($_POST['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

if (!$postId || $postId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Valid post_id required']);
    exit();
}

// Verify post exists
$check_stmt = $conn->prepare("SELECT post_id FROM posts WHERE post_id = ?");
$check_stmt->bind_param("i", $postId);
$check_stmt->execute();
if (!$check_stmt->get_result()->fetch_assoc()) {
    http_response_code(404);
    echo json_encode(['error' => 'Post not found']);
    exit();
}

// Get comments (only approved and flagged, exclude removed)
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
$stmt->bind_param("iii", $postId, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$comments = [];
while ($comment = $result->fetch_assoc()) {
    $comment['author'] = trim($comment['first_name'] . ' ' . $comment['last_name']);
    $comment['avatar'] = $comment['profile_picture'] ?? 'http://localhost/assets/default.png';
    $comment['created_at'] = date('M d, Y H:i', strtotime($comment['created_at']));
    unset($comment['first_name'], $comment['last_name'], $comment['profile_picture']);
    $comments[] = $comment;
}

// Get total comment count
$count_stmt = $conn->prepare("
    SELECT COUNT(*) as total FROM comments 
    WHERE post_id = ? AND moderation_status IN ('approved', 'flagged')
");
$count_stmt->bind_param("i", $postId);
$count_stmt->execute();
$total_comments = $count_stmt->get_result()->fetch_assoc()['total'];

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
?>
