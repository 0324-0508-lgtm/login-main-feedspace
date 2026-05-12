<?php
// API endpoint to get user profile and their posts
session_start();
include '../../../config/db.php';

header('Content-Type: application/json; charset=utf-8');
// Only allow POST requests
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}
// Get user_id from session and optional target user_id from POST data
$user_id = $_SESSION['user_id'];
$target_user_id = $_POST['user_id'] ?? $user_id;
$page = max(1, intval($_POST['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// 1. Get user profile
$stmt = $conn->prepare("
    SELECT 
        user_id, first_name, last_name, email, bio,
        profile_picture, cover_photo, created_at,
        (SELECT COUNT(*) FROM posts WHERE user_id = ?) as post_count,
        (SELECT COUNT(*) FROM shares WHERE user_id = ?) as share_count,
        (SELECT COUNT(*) FROM communities WHERE user_id = ?) as community_count
    FROM users 
    WHERE user_id = ?
");
$stmt->bind_param("ssss", $target_user_id, $target_user_id, $target_user_id, $target_user_id);
$stmt->execute();
$result = $stmt->get_result();
// If user not found, return error
if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
    exit();
}

$profile = $result->fetch_assoc();

// Full image URLs
$profile['profile_picture'] = $profile['profile_picture'] ? 
    "http://localhost/uploads/profiles/" . $profile['profile_picture'] : 
    "http://localhost/assets/default.png";
$profile['cover_photo'] = $profile['cover_photo'] ? 
    "http://localhost/uploads/covers/" . $profile['cover_photo'] : 
    "http://localhost/assets/cover-default.jpg";
$profile['full_name'] = trim($profile['first_name'] . ' ' . $profile['last_name']);
$profile['created_at'] = date('M d, Y', strtotime($profile['created_at']));

//Get user's POSTS (original posts)
$posts_stmt = $conn->prepare("
    SELECT p.id, p.content, p.image, p.created_at,
        (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) as likes,
        (SELECT COUNT(*) FROM shares WHERE post_id = p.id) as shares
    FROM posts p 
    WHERE p.user_id = ? 
    ORDER BY p.created_at DESC 
    LIMIT ? OFFSET ?
");
$posts_stmt->bind_param("sii", $target_user_id, $limit, $offset);
$posts_stmt->execute();
$posts_result = $posts_stmt->get_result();
$posts = [];
while ($post = $posts_result->fetch_assoc()) {
    $post['image'] = $post['image'] ? "http://localhost/uploads/posts/" . $post['image'] : null;
    $post['created_at'] = date('M d', strtotime($post['created_at']));
    $posts[] = $post;
}

// Get user's SHARED POSTS
$shares_stmt = $conn->prepare("
    SELECT s.id as share_id, s.created_at as shared_at,
        p.id as post_id, p.content, p.image, p.user_id as original_user,
        (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) as likes,
        (SELECT COUNT(*) FROM shares WHERE post_id = p.id) as shares
    FROM shares s 
    JOIN posts p ON s.post_id = p.id
    WHERE s.user_id = ? 
    ORDER BY s.created_at DESC 
    LIMIT ? OFFSET ?
");
$shares_stmt->bind_param("sii", $target_user_id, $limit, $offset);
$shares_stmt->execute();
$shares_result = $shares_stmt->get_result();
$shared_posts = [];
while ($share = $shares_result->fetch_assoc()) {
    $share['image'] = $share['image'] ? "http://localhost/uploads/posts/" . $share['image'] : null;
    $share['created_at'] = date('M d', strtotime($share['shared_at']));
    $shared_posts[] = $share;
}

echo json_encode([
    'success' => true,
    'profile' => $profile,
    'posts' => $posts,
    'shared_posts' => $shared_posts,
    'pagination' => [
        'page' => $page,
        'limit' => $limit,
        'post_count' => $profile['post_count'],
        'share_count' => $profile['share_count']
    ]
]);
?>