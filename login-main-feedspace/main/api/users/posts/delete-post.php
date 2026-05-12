<?php
session_start();
include '../../config/db.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$post_id = intval($_POST['post_id'] ?? 0);

if ($post_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid post ID']);
    exit();
}

// Verify ownership
$stmt = $conn->prepare("SELECT image FROM posts WHERE id = ? AND user_id = ?");
$stmt->bind_param("is", $post_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(403);
    echo json_encode(['error' => 'Post not found or not owner']);
    exit();
}

$post = $result->fetch_assoc();
$image_path = $post['image'] ? '../../uploads/posts/' . $post['image'] : null;

// Transaction for safety
$conn->begin_transaction();

try {
    // Delete related data
    $conn->prepare("DELETE FROM post_likes WHERE post_id = ?")->execute([$post_id]);
    $conn->prepare("DELETE FROM shares WHERE post_id = ?")->execute([$post_id]);
    $conn->prepare("DELETE FROM comments WHERE post_id = ?")->execute([$post_id]);
    
    // Delete post
    $conn->prepare("DELETE FROM posts WHERE id = ?")->execute([$post_id]);
    
    // Delete image file
    if ($image_path && file_exists($image_path)) {
        unlink($image_path);
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Post deleted successfully!',
        'post_id' => $post_id
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['error' => 'Delete failed: ' . $e->getMessage()]);
}
?>