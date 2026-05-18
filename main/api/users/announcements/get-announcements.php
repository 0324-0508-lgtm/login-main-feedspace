<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$host = 'localhost';
$dbname = 'db_feedspace';
$user = 'root';
$pass = '';

$conn = mysqli_connect($host, $user, $pass, $dbname);
if (!$conn) {
    http_response_code(500);
    exit(json_encode(['success' => false, 'message' => 'DB Error: ' . mysqli_connect_error()]));
}
mysqli_set_charset($conn, 'utf8mb4');

// Query posts marked as announcements
$query = "
    SELECT
        p.post_id as announcement_id,
        COALESCE(c.community_name, 'All Members') AS audience,
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

$result = mysqli_query($conn, $query);
$announcements = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $row['is_pinned'] = (bool) $row['is_pinned'];
        
        // Use description as title if title is empty or just whitespace
        $title = trim($row['title']);
        if (empty($title)) {
            // Use first 60 chars of description as title
            $title = substr($row['description'], 0, 60);
            if (strlen($row['description']) > 60) {
                $title .= '...';
            }
        }
        $row['title'] = $title;
        
        // Truncate long descriptions to 500 chars
        if (strlen($row['description']) > 500) {
            $row['description'] = substr($row['description'], 0, 500) . '...';
        }
        
        $announcements[] = $row;
    }
    mysqli_free_result($result);
}

mysqli_close($conn);

echo json_encode([
    'success' => true,
    'announcements' => $announcements
]);
