<?php
// unlike.php - ZERO external files needed!

// Built-in CORS + JSON headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database connection (CHANGE THESE 4 LINES)
$host = 'localhost';
$dbname = 'db_feedspace';  // Your DB name
$username = 'root';         // Your DB user
$password = '';             // Your DB password

// mysqli connection
$conn = mysqli_connect($host, $username, $password, $dbname);
if (!$conn) {
    http_response_code(500);
    exit(json_encode(['success' => false, 'message' => 'DB Error: ' . mysqli_connect_error()]));
}

// Set charset
mysqli_set_charset($conn, 'utf8mb4');

// Get post ID from URL (?id=1)
$postId = $_GET['id'] ?? null;
if (!$postId) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Missing post ID (?id=1)']));
}

// Get user ID from JSON body
$input = json_decode(file_get_contents('php://input'), true);
$userId = $input['userId'] ?? null;
if (!$userId) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Missing userId in body']));
}

// DELETE like
$stmt = mysqli_prepare($conn, "DELETE FROM post_likes WHERE post_id = ? AND user_id = ?");
mysqli_stmt_bind_param($stmt, 'ii', $postId, $userId);
$deleted = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

// Get new count
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) as count FROM post_likes WHERE post_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $postId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$countRow = mysqli_fetch_assoc($result);
$count = $countRow['count'];
mysqli_stmt_close($stmt);

// Close connection
mysqli_close($conn);

echo json_encode([
    'success' => true,
    'likesCount' => (int)$count,
    'deleted' => $deleted,
    'postId' => $postId,
    'userId' => $userId
]);
?>