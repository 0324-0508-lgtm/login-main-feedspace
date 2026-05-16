<?php
// add-comment.php - ZERO external files!

// Headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
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

// POST only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'POST required']));
}

// Get data
$input = json_decode(file_get_contents('php://input'), true);
$postId = $_GET['id'] ?? $input['postId'] ?? '';
$userId = $input['userId'] ?? '';
$text = trim($input['text'] ?? '');

if (!$postId || !$userId || !$text || strlen($text) < 1 || strlen($text) > 500) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'postId, userId, and text (1-500 chars) required']));
}

// Add comment
$stmt = mysqli_prepare($conn, "
    INSERT INTO post_comments (post_id, user_id, text, created_at) 
    VALUES (?, ?, ?, NOW())
");
mysqli_stmt_bind_param($stmt, 'iis', $postId, $userId, $text);
$success = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

// Get new comment
$commentStmt = mysqli_prepare($conn, "
    SELECT id, post_id, user_id, text, created_at 
    FROM post_comments 
    WHERE post_id = ? AND user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 1
");
mysqli_stmt_bind_param($commentStmt, 'ii', $postId, $userId);
mysqli_stmt_execute($commentStmt);
$commentResult = mysqli_stmt_get_result($commentStmt);
$comment = mysqli_fetch_assoc($commentResult);
mysqli_stmt_close($commentStmt);

// Get comment count
$countStmt = mysqli_prepare($conn, "SELECT COUNT(*) as count FROM post_comments WHERE post_id = ?");
mysqli_stmt_bind_param($countStmt, 'i', $postId);
mysqli_stmt_execute($countStmt);
$countResult = mysqli_stmt_get_result($countStmt);
$countRow = mysqli_fetch_assoc($countResult);
$count = $countRow['count'];
mysqli_stmt_close($countStmt);

// Close connection
mysqli_close($conn);

echo json_encode([
    'success' => true,
    'message' => 'Comment added',
    'comment' => $comment,
    'commentsCount' => (int)$count,
    'postId' => $postId
]);
?>