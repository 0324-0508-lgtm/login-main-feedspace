<?php
session_start();
include '../../config/db.php';
include '../../config/ban-check.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Check if banned
if (isUserBanned($_SESSION['user_id'], $conn)) {
    http_response_code(403);
    echo json_encode(['error' => 'Account banned']);
    exit();
}

$page = max(1, intval($_POST['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Get posts (exclude banned users' posts)
$stmt = $conn->prepare("
    SELECT 
        p.id, p.content, p.image, p.created_at,
        u.user_id, u.first_name, u.last_name, u.profile_picture,
        (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p.id) as like_count,
        (SELECT COUNT(*) FROM shares s WHERE s.post_id = p.id) as share_count,
        (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) as comment_count,
        EXISTS(SELECT 1 FROM post_likes pl WHERE pl.post_id = p.id AND pl.user_id = ?) as user_liked,
        EXISTS(SELECT 1 FROM shares s WHERE s.post_id = p.id AND s.user_id = ?) as user_shared
    FROM posts p
    JOIN users u ON p.user_id = u.user_id
    LEFT JOIN user_bans b ON u.user_id = b.user_id 
        AND (b.expires_at > NOW() OR b.expires_at IS NULL)
    WHERE b.id IS NULL  -- Exclude banned users
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->bind_param("ssii", $_SESSION['user_id'], $_SESSION['user_id'], $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$posts = [];
while ($post = $result->fetch_assoc()) {
    // Image URLs
    $post['image'] = $post['image'] ? 
        "http://localhost/uploads/posts/" . $post['image'] : null;
    
    $post['profile_picture'] = $post['profile_picture'] ? 
        "http://localhost/uploads/profiles/" . $post['profile_picture'] : 
        "http://localhost/assets/default.png";
    
    $post['created_at'] = date('M d, Y H:i', strtotime($post['created_at']));
    $post['full_name'] = trim($post['first_name'] . ' ' . $post['last_name']);
    
    $posts[] = $post;
}

// Total count (exclude banned)
$count_stmt = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM posts p
    JOIN users u ON p.user_id = u.user_id
    LEFT JOIN user_bans b ON u.user_id = b.user_id 
        AND (b.expires_at > NOW() OR b.expires_at IS NULL)
    WHERE b.id IS NULL
");
$count_stmt->execute();
$total_posts = $count_stmt->get_result()->fetch_assoc()['total'];

echo json_encode([
    'success' => true,
    'posts' => $posts,
    'pagination' => [
        'page' => $page,
        'limit' => $limit,
        'total' => $total_posts,
        'pages' => ceil($total_posts / $limit)
    ]
]);
?>