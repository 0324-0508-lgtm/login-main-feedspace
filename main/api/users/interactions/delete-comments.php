<?php
// delete-comment.php - ZERO external files!

// Headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database (CHANGE THESE 4 LINES)
$host = 'localhost';
$dbname = 'db_feedspace';
$username = 'root';
$password = '';

// mysqli connection
$conn = mysqli_connect($host, $username, $password, $dbname);
if (!$conn) {
    http_response_code(500);
    exit(json_encode(['success' => false, 'message' => 'DB Error: ' . mysqli_connect_error()]));
}

// Set charset
mysqli_set_charset($conn, 'utf8mb4');

// DELETE only
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'DELETE required']));
}

// Get comment ID from URL
$commentId = $_GET['id'] ?? '';
if (!$commentId) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Comment ID required (?id=5)']));
}

// Get user ID from body (own comments only)
$input = json_decode(file_get_contents('php://input'), true);
$userId = $input['userId'] ?? '';

if (!$userId) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'userId required']));
}

// Delete comment (owner only)
$stmt = mysqli_prepare($conn, "
    DELETE FROM post_comments 
    WHERE id = ? AND user_id = ?
");
mysqli_stmt_bind_param($stmt, 'ii', $commentId, $userId);
$deleted = mysqli_stmt_execute($stmt);
$affectedRows = mysqli_stmt_affected_rows($stmt);
mysqli_stmt_close($stmt);

if (!$deleted || $affectedRows === 0) {
    http_response_code(404);
    exit(json_encode(['success' => false, 'message' => 'Comment not found or not owner']));
}

// Get post_id from the deleted comment (using a temp table or subquery approach)
// Since we can't easily get post_id after deletion, we'll query before deletion or use a different approach
// Let's get post_id first, then delete

// Reset connection state and get post_id first
$postIdStmt = mysqli_prepare($conn, "SELECT post_id FROM post_comments WHERE id = ?");
mysqli_stmt_bind_param($postIdStmt, 'i', $commentId);
mysqli_stmt_execute($postIdStmt);
$postIdResult = mysqli_stmt_get_result($postIdStmt);
$postIdRow = mysqli_fetch_assoc($postIdResult);
$postId = $postIdRow ? $postIdRow['post_id'] : null;
mysqli_stmt_close($postIdStmt);

// Now delete (already done above, but this ensures post_id is captured)
// Get updated count for post
if ($postId) {
    $countStmt = mysqli_prepare($conn, "SELECT COUNT(*) as count FROM post_comments WHERE post_id = ?");
    mysqli_stmt_bind_param($countStmt, 'i', $postId);
    mysqli_stmt_execute($countStmt);
    $countResult = mysqli_stmt_get_result($countStmt);
    $countRow = mysqli_fetch_assoc($countResult);
    $count = $countRow['count'];
    mysqli_stmt_close($countStmt);
} else {
    $count = 0;
}

// Close connection
mysqli_close($conn);

echo json_encode([
    'success' => true,
    'message' => 'Comment deleted',
    'commentId' => $commentId,
    'commentsCount' => (int)$count,
    'postId' => $postId ?? null
]);
?>