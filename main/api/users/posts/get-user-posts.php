<?php
session_start();
include '../../config/db.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$profile_user_id = $_POST['profile_user_id'] ?? '';
$page = max(1, intval($_POST['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

if (empty($profile_user_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Profile user_id required']);
    exit();
}

// Get profile owner's posts
$stmt = $conn->prepare("
    SELECT 
        p.post_id AS id,
        p.content,
        p.file_url AS image,
        p.created_at,
        (SELECT COUNT(*) FROM post_likes WHERE post_id = p.post_id) as like_count,
        (SELECT COUNT(*) FROM shares WHERE post_id = p.post_id) as share_count,
        (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id) as comment_count,
        EXISTS(SELECT 1 FROM post_likes pl WHERE pl.post_id = p.post_id AND pl.user_id = ?) as user_liked
    FROM posts p
    WHERE p.user_id = ?
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->bind_param("ssii", $_SESSION['user_id'], $profile_user_id, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$posts = [];
while ($post = $result->fetch_assoc()) {
    if ($post['image']) {
        $post['image'] = preg_match('#^https?://#i', $post['image']) ? $post['image'] : "http://localhost/uploads/posts/" . $post['image'];
    } else {
        $post['image'] = null;
    }
    $post['created_at'] = date('M d, Y', strtotime($post['created_at']));
    $posts[] = $post;
}

// Total count
$count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM posts WHERE user_id = ?");
$count_stmt->bind_param("s", $profile_user_id);
$count_stmt->execute();
$total = $count_stmt->get_result()->fetch_assoc()['total'];

echo json_encode([
    'success' => true,
    'posts' => $posts,
    'profile_user_id' => $profile_user_id,
    'pagination' => [
        'page' => $page,
        'limit' => $limit,
        'total' => $total,
        'pages' => ceil($total / $limit)
    ]
]);
?>