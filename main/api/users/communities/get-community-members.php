<?php
// Show MEMBERS of ONE community
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$host='localhost'; $dbname='db_feedspace'; $user='root'; $pass='';

// mysqli connection
$conn = mysqli_connect($host, $user, $pass, $dbname);
if (!$conn) {
    http_response_code(500);
    exit(json_encode(['success' => false, 'message' => 'DB Error: ' . mysqli_connect_error()]));
}

// Set charset
mysqli_set_charset($conn, 'utf8mb4');

$id = $_GET['id'] ?? 0;

// Get members (prepared statement)
$stmt = mysqli_prepare($conn, "
    SELECT u.id, u.username, u.profile_picture, cm.joined_at
    FROM community_members cm
    JOIN users u ON cm.user_id = u.id
    WHERE cm.community_id = ?
    ORDER BY cm.joined_at DESC
    LIMIT 50
");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$membersResult = mysqli_stmt_get_result($stmt);
$members = [];
while ($row = mysqli_fetch_assoc($membersResult)) {
    $members[] = $row;
}
mysqli_stmt_close($stmt);

// Get total count (prepared statement - fixed SQL injection!)
$countStmt = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM community_members WHERE community_id = ?");
mysqli_stmt_bind_param($countStmt, 'i', $id);
mysqli_stmt_execute($countStmt);
$countResult = mysqli_stmt_get_result($countStmt);
$countRow = mysqli_fetch_assoc($countResult);
$total = $countRow['total'];
mysqli_stmt_close($countStmt);

// Close connection
mysqli_close($conn);

echo json_encode([
    'success' => true,
    'communityId' => $id,
    'members' => $members,
    'total' => (int)$total
]);
?>