<?php
session_start();
require_once __DIR__ . '/../../../../config/db.php';
header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? '';

if (empty($user_id)) {
    $user_id = trim($_POST['user_id'] ?? '');
}

if (empty($user_id)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$post_id = intval($input['post_id'] ?? $_POST['post_id'] ?? 0);
$text = trim($input['text'] ?? $_POST['text'] ?? '');

if ($post_id <= 0 || empty($text)) {
    echo json_encode(['success' => false, 'message' => 'Invalid post ID or empty comment']);
    exit();
}

// Check if post exists and is not deleted
$check_stmt = $conn->prepare("SELECT post_id FROM posts WHERE post_id = ? AND is_deleted = 0");
$check_stmt->bind_param("i", $post_id);
$check_stmt->execute();
if ($check_stmt->get_result()->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Post not found']);
    exit();
}
$check_stmt->close();

// Insert comment (adjust table name if needed)
$stmt = $conn->prepare("
    INSERT INTO comments (post_id, user_id, content, created_at) 
    VALUES (?, ?, ?, NOW())
");

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit();
}

$stmt->bind_param("iis", $post_id, $user_id, $text);

if ($stmt->execute()) {
    $comment_id = $conn->insert_id;
    $stmt->close();
    
    // Get user info
    $user_stmt = $conn->prepare("SELECT first_name, last_name, profile_picture FROM users WHERE user_id = ?");
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user_info = $user_stmt->get_result()->fetch_assoc();
    $user_stmt->close();
    
    // Get updated comment count
    $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM comments WHERE post_id = ?");
    $count_stmt->bind_param("i", $post_id);
    $count_stmt->execute();
    $count_data = $count_stmt->get_result()->fetch_assoc();
    $count_stmt->close();
    
    $full_name = trim(($user_info['first_name'] ?? '') . ' ' . ($user_info['last_name'] ?? ''));
    if (empty($full_name)) {
        $full_name = 'User';
    }
    
    $avatar = !empty($user_info['profile_picture']) 
        ? "http://localhost/uploads/profiles/" . $user_info['profile_picture']
        : "http://localhost/assets/default.png";
    
    echo json_encode([
        'success' => true,
        'comment_id' => $comment_id,
        'text' => htmlspecialchars($text),
        'author' => htmlspecialchars($full_name),
        'avatar' => $avatar,
        'comment_count' => $count_data['count'],
        'moderation_status' => 'approved'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to add comment: ' . $stmt->error
    ]);
    $stmt->close();
}
?>