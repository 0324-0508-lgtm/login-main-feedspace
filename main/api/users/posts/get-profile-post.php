<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Error handling - don't let PHP warnings break JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../../../config/db.php';

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$userId = isset($_GET['user_id']) ? $_GET['user_id'] : '';
$limit = 10;
$offset = ($page - 1) * $limit;

if (empty($userId)) {
    echo json_encode(['success' => false, 'error' => 'User ID required']);
    exit;
}

try {
    // Get user's posts with engagement counts
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            u.username,
            u.display_name,
            u.avatar,
            u.college,
            u.verified,
            (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as likes_count,
            (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comments_count,
            (SELECT COUNT(*) FROM shares WHERE post_id = p.id) as shares_count,
            sp.username as shared_by_username,
            sp.display_name as shared_by_display_name
        FROM posts p
        JOIN users u ON p.user_id = u.id
        LEFT JOIN users sp ON p.shared_by = sp.id
        WHERE p.user_id = ?
        ORDER BY p.created_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $stmt->execute([$userId, $limit, $offset]);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count for pagination
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE user_id = ?");
    $countStmt->execute([$userId]);
    $totalPosts = $countStmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'posts' => $posts,
        'total' => (int)$totalPosts,
        'page' => $page,
        'hasMore' => ($offset + count($posts)) < $totalPosts
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>