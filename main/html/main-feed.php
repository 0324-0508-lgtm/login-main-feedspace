<?php
session_start();

// Auth guard (no test-user fallback)
if (empty($_SESSION['user_id'])) {
    header('Location: ../../index.html');
    exit();
}

$user_id = $_SESSION['user_id'];

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/ban-check.php';

if (isUserBanned($user_id, $conn)) {
    http_response_code(403);
    echo 'Account banned';
    exit();
}

// Check if this is an API request (AJAX call)
$isApiRequest = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($isApiRequest && isset($_POST['action']) && $_POST['action'] === 'get_posts') {
    header('Content-Type: application/json');
    
    $page = max(1, intval($_POST['page'] ?? 1));
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    $stmt = $conn->prepare(
        "SELECT
            p.post_id,
            p.content,
            p.file_url,
            p.created_at,
            u.first_name,
            u.last_name,
            u.profile_picture,
            (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p.post_id) AS like_count,
            (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.post_id) AS comment_count,
            EXISTS(SELECT 1 FROM post_likes pl WHERE pl.post_id = p.post_id AND pl.user_id = ?) AS user_liked
         FROM posts p
         JOIN users u ON p.user_id = u.user_id
         LEFT JOIN user_bans b ON u.user_id = b.user_id
            AND (b.expires_at > NOW() OR b.expires_at IS NULL)
         WHERE p.is_deleted = 0
            AND p.deleted_at IS NULL
            AND p.is_archived = 0
            AND p.status = 'approved'
            AND p.ai_status != 'rejected'
            AND b.id IS NULL
         ORDER BY p.created_at DESC
         LIMIT ? OFFSET ?"
    );
    $stmt->bind_param('sii', $user_id, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $posts = [];
    while ($row = $result->fetch_assoc()) {
        $row['full_name'] = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
        $row['profile_picture'] = !empty($row['profile_picture'])
            ? 'http://localhost/uploads/profiles/' . $row['profile_picture']
            : 'http://localhost/assets/default.png';
        
        if (!empty($row['file_url'])) {
            $row['image'] = preg_match('#^https?://#i', $row['file_url'])
                ? $row['file_url']
                : 'http://localhost/uploads/posts/' . $row['file_url'];
        } else {
            $row['image'] = null;
        }
        
        $row['created_at'] = date('M d, Y H:i', strtotime($row['created_at']));
        $row['user_liked'] = !empty($row['user_liked']);
        $posts[] = $row;
    }
    
    echo json_encode(['success' => true, 'posts' => $posts]);
    exit();
}

// Fetch posts for server-side rendering
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$stmt = $conn->prepare(
    "SELECT
        p.post_id,
        p.content,
        p.file_url,
        p.created_at,
        u.first_name,
        u.last_name,
        u.profile_picture,
        (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p.post_id) AS like_count,
        (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.post_id) AS comment_count,
        EXISTS(SELECT 1 FROM post_likes pl WHERE pl.post_id = p.post_id AND pl.user_id = ?) AS user_liked
     FROM posts p
     JOIN users u ON p.user_id = u.user_id
     LEFT JOIN user_bans b ON u.user_id = b.user_id
        AND (b.expires_at > NOW() OR b.expires_at IS NULL)
         WHERE p.is_deleted = 0
            AND p.deleted_at IS NULL
            AND p.is_archived = 0
            AND p.status = 'approved'
            AND p.ai_status != 'rejected'
            AND b.id IS NULL
         ORDER BY p.created_at DESC
     LIMIT ? OFFSET ?"
);
$stmt->bind_param('sii', $user_id, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$posts = [];
while ($row = $result->fetch_assoc()) {
    $row['full_name'] = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
    $row['profile_picture'] = !empty($row['profile_picture'])
        ? 'http://localhost/uploads/profiles/' . $row['profile_picture']
        : 'http://localhost/assets/default.png';
    
    if (!empty($row['file_url'])) {
        $row['image'] = preg_match('#^https?://#i', $row['file_url'])
            ? $row['file_url']
            : 'http://localhost/uploads/posts/' . $row['file_url'];
    } else {
        $row['image'] = null;
    }
    
    $row['created_at'] = date('M d, Y H:i', strtotime($row['created_at']));
    $row['user_liked'] = !empty($row['user_liked']);
    $posts[] = $row;
}

// Make posts available to the included HTML
$FEED_POSTS = $posts;

// Render the page
include __DIR__ . '/main-feed.html';
?>