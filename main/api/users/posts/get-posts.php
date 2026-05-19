<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

$input = json_decode(file_get_contents('php://input'), true);
$_POST = array_merge($_POST, $input ?? []);

$page = max(1, intval($_POST['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

try {
    $sql = "
        SELECT 
            p.post_id,
            p.user_id,
            p.community_id,
            p.content,
            p.file_url,
            p.file_type,
            p.visibility,
            p.created_at,
            p.is_announcement,
            u.first_name,
            u.last_name,
            u.profile_picture,
            (SELECT COUNT(*) FROM post_likes WHERE post_id = p.post_id) as like_count,
            (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id AND moderation_status IN ('approved', 'flagged')) as comment_count,
            EXISTS(SELECT 1 FROM post_likes WHERE post_id = p.post_id AND user_id = ?) as user_liked
        FROM posts p
        JOIN users u ON p.user_id = u.user_id
        WHERE p.status = 'approved' AND p.is_deleted = 0 AND p.is_archived = 0
        ORDER BY p.is_announcement DESC, p.created_at DESC
        LIMIT ? OFFSET ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(1, $user_id, PDO::PARAM_STR);
$stmt->bindValue(2, (int)$limit, PDO::PARAM_INT);
$stmt->bindValue(3, (int)$offset, PDO::PARAM_INT);
$stmt->execute();
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formatted = [];
    foreach ($posts as $post) {
        $formatted[] = [
            'post_id' => $post['post_id'],
            'user_id' => $post['user_id'],
            'content' => $post['content'],
            'file_url' => $post['file_url'],
            'file_type' => $post['file_type'],
            'created_at' => date('M d, Y H:i', strtotime($post['created_at'])),
            'full_name' => trim($post['first_name'] . ' ' . $post['last_name']),
            'profile_picture' => $post['profile_picture'] ?? 'default.png',
            'like_count' => $post['like_count'],
            'comment_count' => $post['comment_count'],
            'user_liked' => (bool)$post['user_liked'],
            'is_announcement' => (bool)$post['is_announcement']
        ];
    }

    $countStmt = $conn->prepare("SELECT COUNT(*) FROM posts WHERE status = 'approved' AND is_deleted = 0 AND is_archived = 0");
    $countStmt->execute();
    $total = $countStmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'posts' => $formatted,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ]);

} catch (Exception $e) {
    error_log('Get posts error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to load posts']);
}