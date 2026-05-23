<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

try {
    $query = "
        SELECT
            p.post_id as announcement_id,
            COALESCE(c.name, 'All Members') AS audience,
            'normal' as priority,
            p.status,
            0 as is_pinned,
            p.created_at,
            NULL as expires_at,
            p.content AS description,
            CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as title
        FROM posts p
        LEFT JOIN communities c ON p.community_id = c.community_id
        LEFT JOIN users u ON p.user_id = u.user_id
        WHERE p.is_announcement = 1
          AND p.is_deleted = 0
          AND p.status IN ('pending', 'approved')
        ORDER BY p.created_at DESC
    ";
    
    $stmt = $conn->prepare($query);  // ← ADD THIS LINE!
    $stmt->execute();
    $announcements = [];
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['is_pinned'] = (bool) $row['is_pinned'];
        
        $title = trim($row['title']);
        if (empty($title)) {
            $title = substr($row['description'], 0, 60);
            if (strlen($row['description']) > 60) {
                $title .= '...';
            }
        }
        $row['title'] = $title;
        
        if (strlen($row['description']) > 500) {
            $row['description'] = substr($row['description'], 0, 500) . '...';
        }
        
        $announcements[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'announcements' => $announcements
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}