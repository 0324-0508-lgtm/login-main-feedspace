<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';

$userId = $_GET['user_id'] ?? null;
if (!$userId) {
    echo json_encode(['success' => false, 'error' => 'User ID required']);
    exit;
}

$currentUserId = $_SESSION['user_id'] ?? null;

try {
    $sql = "
        SELECT 
            p.post_id, p.user_id, p.content, p.image_url, p.created_at, p.shared_post_id,
            u.first_name, u.last_name, u.profile_picture, u.college,
            (SELECT COUNT(*) FROM post_likes WHERE post_id = p.post_id) as like_count,
            (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id) as comment_count,
            (SELECT COUNT(*) FROM posts WHERE shared_post_id = p.post_id) as share_count
            " . ($currentUserId ? ", (SELECT COUNT(*) FROM post_likes WHERE post_id = p.post_id AND user_id = ?) as user_liked" : ", 0 as user_liked") . "
        FROM posts p
        JOIN users u ON p.user_id = u.user_id
        WHERE p.user_id = ?
        ORDER BY p.created_at DESC
    ";

    $params = $currentUserId ? [$currentUserId, $userId] : [$userId];
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($posts as &$post) {
        if ($post['shared_post_id']) {
            $spStmt = $pdo->prepare("
                SELECT p.post_id, p.content, p.image_url, p.created_at,
                       u.first_name, u.last_name, u.profile_picture
                FROM posts p
                JOIN users u ON p.user_id = u.user_id
                WHERE p.post_id = ?
            ");
            $spStmt->execute([$post['shared_post_id']]);
            $post['shared_post'] = $spStmt->fetch(PDO::FETCH_ASSOC);
        }
    }

    echo json_encode(['success' => true, 'posts' => $posts]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}