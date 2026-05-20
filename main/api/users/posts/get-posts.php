<?php
session_start();
header('Content-Type: application/json');

$paths = [
    __DIR__ . '/../../../config/db.php',
    __DIR__ . '/../../../../config/db.php',
];

$configFound = false;
foreach ($paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $configFound = true;
        break;
    }
}

if (!$configFound || !isset($conn)) {
    echo json_encode(['success' => false, 'error' => 'Config not found']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

$input = json_decode(file_get_contents('php://input'), true);
$_POST = array_merge($_POST, $input ?? []);

$page = max(1, intval($_POST['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Check if profile-specific posts requested
$profileUserId = $_GET['user_id'] ?? null;

try {
    if ($profileUserId) {
        // Profile page: only this user's posts
        $sql = "
            SELECT 
                p.post_id,
                p.user_id,
                p.shared_post_id,
                p.content,
                p.file_url,
                p.file_type,
                p.visibility,
                p.created_at,
                p.is_announcement,
                p.ai_score,
                p.ai_status,
                p.ai_reason,
                u.first_name,
                u.last_name,
                u.profile_picture,
                (SELECT COUNT(*) FROM post_likes WHERE post_id = p.post_id) as like_count,
                (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id AND moderation_status IN ('approved', 'flagged')) as comment_count,
                EXISTS(SELECT 1 FROM post_likes WHERE post_id = p.post_id AND user_id = ?) as user_liked,
                op.post_id as orig_post_id,
                op.user_id as orig_user_id,
                op.content as orig_content,
                op.file_url as orig_file_url,
                op.created_at as orig_created_at,
                ou.first_name as orig_first_name,
                ou.last_name as orig_last_name,
                ou.profile_picture as orig_profile_picture
            FROM posts p
            JOIN users u ON p.user_id = u.user_id
            LEFT JOIN posts op ON op.post_id = p.shared_post_id AND op.is_deleted = 0
            LEFT JOIN users ou ON ou.user_id = op.user_id
            WHERE p.user_id = ? AND p.status = 'approved' AND p.is_deleted = 0 AND p.is_archived = 0
            ORDER BY p.is_announcement DESC, p.created_at DESC
            LIMIT ? OFFSET ?
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(1, $user_id, PDO::PARAM_STR);
        $stmt->bindValue(2, $profileUserId, PDO::PARAM_STR);
        $stmt->bindValue(3, (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(4, (int)$offset, PDO::PARAM_INT);
        
        $countStmt = $conn->prepare("SELECT COUNT(*) FROM posts WHERE user_id = ? AND status = 'approved' AND is_deleted = 0 AND is_archived = 0");
        $countStmt->execute([$profileUserId]);
        
    } else {
        // Main feed: all posts
        $sql = "
            SELECT 
                p.post_id,
                p.user_id,
                p.shared_post_id,
                p.content,
                p.file_url,
                p.file_type,
                p.visibility,
                p.created_at,
                p.is_announcement,
                p.ai_score,
                p.ai_status,
                p.ai_reason,
                u.first_name,
                u.last_name,
                u.profile_picture,
                (SELECT COUNT(*) FROM post_likes WHERE post_id = p.post_id) as like_count,
                (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id AND moderation_status IN ('approved', 'flagged')) as comment_count,
                EXISTS(SELECT 1 FROM post_likes WHERE post_id = p.post_id AND user_id = ?) as user_liked,
                op.post_id as orig_post_id,
                op.user_id as orig_user_id,
                op.content as orig_content,
                op.file_url as orig_file_url,
                op.created_at as orig_created_at,
                ou.first_name as orig_first_name,
                ou.last_name as orig_last_name,
                ou.profile_picture as orig_profile_picture
            FROM posts p
            JOIN users u ON p.user_id = u.user_id
            LEFT JOIN posts op ON op.post_id = p.shared_post_id AND op.is_deleted = 0
            LEFT JOIN users ou ON ou.user_id = op.user_id
            WHERE p.status = 'approved' AND p.is_deleted = 0 AND p.is_archived = 0
            ORDER BY p.is_announcement DESC, p.created_at DESC
            LIMIT ? OFFSET ?
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(1, $user_id, PDO::PARAM_STR);
        $stmt->bindValue(2, (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(3, (int)$offset, PDO::PARAM_INT);
        
        $countStmt = $conn->prepare("SELECT COUNT(*) FROM posts WHERE status = 'approved' AND is_deleted = 0 AND is_archived = 0");
        $countStmt->execute();
    }
    
    $stmt->execute();
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total = $countStmt->fetchColumn();

    $formatted = [];
    foreach ($posts as $post) {
        $isShared = !empty($post['shared_post_id']);

        // Build main post image path
        $image = null;
        if (!empty($post['file_url'])) {
            $image = preg_match('#^https?://#i', $post['file_url'])
                ? $post['file_url']
                : '../../uploads/posts/' . $post['file_url'];
        }

        $item = [
            'post_id' => $post['post_id'],
            'user_id' => $post['user_id'],
            'content' => $post['content'],
            'image' => $image,
            'file_type' => $post['file_type'],
            'created_at' => date('M d, Y H:i', strtotime($post['created_at'])),
            'full_name' => trim($post['first_name'] . ' ' . $post['last_name']),
            'profile_picture' => !empty($post['profile_picture']) 
                ? '../../uploads/profiles/' . $post['profile_picture']
                : '../assets/default.jpg',
            'like_count' => (int)$post['like_count'],
            'comment_count' => (int)$post['comment_count'],
            'user_liked' => (bool)$post['user_liked'],
            'is_announcement' => (bool)$post['is_announcement'],
            'is_shared' => $isShared,
            'ai_status' => $post['ai_status'],
            'ai_score' => $post['ai_score'],
            'ai_reason' => $post['ai_reason'],
            'original' => null
        ];

        if ($isShared) {
            // Build original post image path
            $origImage = null;
            if (!empty($post['orig_file_url'])) {
                $origImage = preg_match('#^https?://#i', $post['orig_file_url'])
                    ? $post['orig_file_url']
                    : '../../uploads/posts/' . $post['orig_file_url'];
            }

            $item['original'] = [
                'post_id' => $post['orig_post_id'],
                'user_id' => $post['orig_user_id'],
                'content' => $post['orig_content'],
                'full_name' => trim(($post['orig_first_name'] ?? '') . ' ' . ($post['orig_last_name'] ?? '')),
                'profile_picture' => !empty($post['orig_profile_picture']) 
                    ? '../../uploads/profiles/' . $post['orig_profile_picture']
                    : '../assets/default.jpg',
                'created_at' => $post['orig_created_at'] ? date('M d, Y H:i', strtotime($post['orig_created_at'])) : '',
                'image' => $origImage
            ];
        }

        $formatted[] = $item;
    }

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
    echo json_encode(['success' => false, 'error' => 'Failed to load posts']);
}