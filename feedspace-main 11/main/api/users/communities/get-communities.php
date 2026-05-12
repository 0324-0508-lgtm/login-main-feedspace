<?php
// List ALL communities + member counts
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// DB (EDIT THESE 4 LINES)
$host='localhost'; $dbname='db_feedspace'; $user='root'; $pass='';

// mysqli connection
$conn = mysqli_connect($host, $user, $pass, $dbname);
if (!$conn) {
    http_response_code(500);
    exit(json_encode(['success' => false, 'message' => 'DB Error: ' . mysqli_connect_error()]));
}

// Set charset
mysqli_set_charset($conn, 'utf8mb4');

$stmt = mysqli_query($conn, "
    SELECT c.id, c.name, c.description,
    COUNT(cm.user_id) as member_count
    FROM communities c 
    LEFT JOIN community_members cm ON c.id = cm.community_id
    GROUP BY c.id
    ORDER BY member_count DESC
");

$communities = [];
if ($stmt) {
    while ($row = mysqli_fetch_assoc($stmt)) {
        $communities[] = $row;
    }
    mysqli_free_result($stmt);
}

mysqli_close($conn);

echo json_encode([
    'success' => true,
    'communities' => $communities
]);
?>