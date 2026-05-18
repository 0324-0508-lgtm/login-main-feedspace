<?php
session_start();
require_once __DIR__ . '/../../../../config/db.php';
require_once __DIR__ . '/../../../../config/ban-check.php';
header('Content-Type: application/json; charset=utf-8');

$user_id = $_SESSION['user_id'] ?? '';

if (empty($user_id)) {
    $user_id = trim($_POST['user_id'] ?? $_GET['user_id'] ?? '');
}

if (!empty($user_id) && empty($_SESSION['user_id'])) {
    $_SESSION['user_id'] = $user_id;
}

if (empty($user_id)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$page = max(1, intval($_POST['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Fixed query to match your database schema
$query = "
    SELECT 
        p.post_id,
        p.user_id,
        p.community_id,
        p.content,
        p.file_url,
        p.file_type,
        p.visibility,
        p.created_at,
        p.updated_at,
        p.is_archived,
        p.is_deleted,
        p.ai_score,
        p.ai_status,
        p.is_announcement,
        u.first_name,
        u.last_name,
        u.profile_picture,
        (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p.post_id) AS like_count,
        (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.post_id AND c.is_deleted = 0) AS comment_count,
        EXISTS(
            SELECT 1 FROM post_likes pl 
            WHERE pl.post_id = p.post_id AND pl.user_id = ?
        ) AS user_liked
    FROM posts p
    JOIN users u ON p.user_id = u.user_id
    LEFT JOIN user_bans b ON u.user_id = b.user_id
        AND (b.expires_at > NOW() OR b.expires_at IS NULL)
    WHERE p.is_deleted = 0 
        AND p.deleted_at IS NULL
        AND p.is_archived = 0
        AND p.status = 'approved'
        AND p.ai_status != 'rejected'
        AND (p.visibility = 'public' OR p.user_id = ?)
        AND (b.id IS NULL OR b.user_id IS NULL)
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($query);

if (!$stmt) {
    error_log("get-posts.php - Prepare failed: " . $conn->error);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
    exit();
}

$stmt->bind_param("sii", $user_id, $user_id, $limit, $offset);

if (!$stmt->execute()) {
    error_log("get-posts.php - Execute failed: " . $stmt->error);
    echo json_encode(['success' => false, 'error' => 'Query execution failed']);
    exit();
}

$result = $stmt->get_result();
$posts = [];

while ($row = $result->fetch_assoc()) {
    // Build full name
    $row['full_name'] = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
    if (empty($row['full_name'])) {
        $row['full_name'] = 'User ' . ($row['user_id'] ?? 'Unknown');
    }
    
    // Handle post image (file_url)
    if (!empty($row['file_url'])) {
        if (preg_match('#^https?://#i', $row['file_url'])) {
            $row['image'] = $row['file_url'];
        } else {
            $row['image'] = "http://localhost/uploads/posts/" . ltrim($row['file_url'], '/');
        }
    } else {
        $row['image'] = null;
    }
    
    // Handle profile picture
    if (!empty($row['profile_picture'])) {
        if (preg_match('#^https?://#i', $row['profile_picture'])) {
            $row['profile_picture'] = $row['profile_picture'];
        } else {
            $row['profile_picture'] = "http://localhost/uploads/profiles/" . ltrim($row['profile_picture'], '/');
        }
    } else {
        $row['profile_picture'] = "http://localhost/assets/default.png";
    }
    
    // Format date
    $row['created_at'] = date('M d, Y H:i', strtotime($row['created_at']));
    
    // Ensure boolean for user_liked
    $row['user_liked'] = (bool)$row['user_liked'];
    
    // Ensure numeric counts
    $row['like_count'] = (int)($row['like_count'] ?? 0);
    $row['comment_count'] = (int)($row['comment_count'] ?? 0);
    
    // Add AI moderation status if needed
    $row['ai_status'] = $row['ai_status'] ?? null;
    $row['ai_score'] = floatval($row['ai_score'] ?? 0);
    
    $posts[] = $row;
}

$stmt->close();

echo json_encode([
    'success' => true,
    'posts' => $posts,
    'pagination' => [
        'page' => $page,
        'limit' => $limit
    ]
]);
?>