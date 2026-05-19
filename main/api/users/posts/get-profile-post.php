<?php
session_start();
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

require_once '../../../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$currentUserId = $_SESSION['user_id'];
$profileUserId = $_GET['user_id'] ?? $currentUserId;

$page = max(1, intval($_GET['page'] ?? 1));
$limit = min(20, max(1, intval($_GET['limit'] ?? 10)));
$offset = ($page - 1) * $limit;

try {
    $stmt = $conn->prepare("
        SELECT
            p.post_id,
            p.content,
            p.file_url,
            p.created_at,
            p.shared_post_id,
            u.first_name,
            u.last_name,
            u.profile_picture,
            (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p.post_id) AS like_count,
            (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.post_id) AS comment_count,
            EXISTS(SELECT 1 FROM post_likes pl WHERE pl.post_id = p.post_id AND pl.user_id = ?) AS user_liked,
            -- Original post data (if shared)
            op.post_id AS orig_post_id,
            op.content AS orig_content,
            op.file_url AS orig_file_url,
            op.created_at AS orig_created_at,
            ou.first_name AS orig_first_name,
            ou.last_name AS orig_last_name,
            ou.profile_picture AS orig_profile_picture
         FROM posts p
         JOIN users u ON p.user_id = u.user_id
         LEFT JOIN posts op ON p.shared_post_id = op.post_id AND op.is_deleted = 0
         LEFT JOIN users ou ON op.user_id = ou.user_id
         WHERE p.user_id = ?
            AND p.is_deleted = 0
            AND p.deleted_at IS NULL
            AND p.is_archived = 0
            AND p.status = 'approved'
            AND p.ai_status != 'rejected'
         ORDER BY p.created_at DESC
         LIMIT ? OFFSET ?
    ");

    $stmt->bindValue(1, $currentUserId, PDO::PARAM_STR);
    $stmt->bindValue(2, $profileUserId, PDO::PARAM_STR);
    $stmt->bindValue(3, (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue(4, (int)$offset, PDO::PARAM_INT);
    $stmt->execute();

    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($posts as &$row) {
        $row['full_name'] = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
        $row['profile_picture'] = !empty($row['profile_picture'])
            ? '../../uploads/profiles/' . $row['profile_picture']
            : '../assets/default.jpg';

        if (!empty($row['file_url'])) {
            $row['image'] = preg_match('#^https?://#i', $row['file_url'])
                ? $row['file_url']
                : '../../uploads/posts/' . $row['file_url'];
        } else {
            $row['image'] = null;
        }

        $row['created_at'] = date('M d, Y H:i', strtotime($row['created_at']));
        $row['user_liked'] = !empty($row['user_liked']);

        // Process original post data for shared posts
        if (!empty($row['shared_post_id'])) {
            $row['is_shared'] = true;
            $row['original'] = [
                'post_id' => $row['orig_post_id'],
                'content' => $row['orig_content'],
                'full_name' => trim(($row['orig_first_name'] ?? '') . ' ' . ($row['orig_last_name'] ?? '')),
                'profile_picture' => !empty($row['orig_profile_picture'])
                    ? '../../uploads/profiles/' . $row['orig_profile_picture']
                    : '../assets/default.jpg',
                'created_at' => date('M d, Y H:i', strtotime($row['orig_created_at'] ?? 'now')),
                'image' => null
            ];
            if (!empty($row['orig_file_url'])) {
                $row['original']['image'] = preg_match('#^https?://#i', $row['orig_file_url'])
                    ? $row['orig_file_url']
                    : '../../uploads/posts/' . $row['orig_file_url'];
            }
        } else {
            $row['is_shared'] = false;
        }
    }

    echo json_encode(['success' => true, 'posts' => $posts]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}