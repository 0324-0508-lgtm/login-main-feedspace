<?php
session_start();
require_once __DIR__ . '/../../../../config/db.php';
require_once __DIR__ . '/../../../../config/ban-check.php';
header('Content-Type: application/json; charset=utf-8');

$user_id = $_SESSION['user_id'] ?? '';

if (empty($user_id)) {
    $user_id = trim($_REQUEST['user_id'] ?? '');
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

$stmt = $conn->prepare("
    SELECT 
        p.post_id AS id,
        p.content,
        p.file_url AS image,
        p.created_at,
        u.first_name,
        u.last_name,
        u.profile_picture,
        (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p.post_id) AS like_count,
        (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.post_id) AS comment_count,
        EXISTS(
            SELECT 1 FROM post_likes pl 
            WHERE pl.post_id = p.post_id AND pl.user_id = ?
        ) AS user_liked
    FROM posts p
    JOIN users u ON p.user_id = u.user_id
    LEFT JOIN user_bans b ON u.user_id = b.user_id
        AND (b.expires_at > NOW() OR b.expires_at IS NULL)
    WHERE p.is_deleted = 0 AND b.id IS NULL
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?
");

$stmt->bind_param("sii", $user_id, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$posts = [];

while ($row = $result->fetch_assoc()) {
    $row['full_name'] = trim($row['first_name'] . ' ' . $row['last_name']);

    if (!empty($row['image'])) {
        $row['image'] = preg_match('#^https?://#i', $row['image'])
            ? $row['image']
            : "http://localhost/uploads/posts/" . $row['image'];
    } else {
        $row['image'] = null;
    }

    if (!empty($row['profile_picture'])) {
        $row['profile_picture'] = "http://localhost/uploads/profiles/" . $row['profile_picture'];
    } else {
        $row['profile_picture'] = "http://localhost/assets/default.png";
    }

    $row['created_at'] = date('M d, Y H:i', strtotime($row['created_at']));
    $row['user_liked'] = $row['user_liked'] ? true : false;

    $posts[] = $row;
}

echo json_encode([
    'success' => true,
    'posts' => $posts
]);