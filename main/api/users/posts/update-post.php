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
$content = trim($_POST['content'] ?? '');
$new_image = null;

// Validate
if ($post_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid post ID']);
    exit();
}

if (strlen($content) > 1000) {
    http_response_code(400);
    echo json_encode(['error' => 'Content max 1000 characters']);
    exit();
}

// Verify ownership
$stmt = $conn->prepare("SELECT file_url FROM posts WHERE post_id = ? AND user_id = ?");
$stmt->bind_param("is", $post_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(403);
    echo json_encode(['error' => 'Post not found or not owner']);
    exit();
}

$old_image = $result->fetch_assoc()['file_url'];

// Handle new image
if (!empty($_FILES['image']['name'])) {
    $new_image = handleImageUpload($_FILES['image']);
    if (!$new_image) {
        http_response_code(400);
        echo json_encode(['error' => 'Image upload failed']);
        exit();
    }
}

// Update post
$sql = "UPDATE posts SET content = ?, updated_at = NOW()";
$params = [$content];
$types = "s";

if ($new_image) {
    $sql .= ", file_url = ?";
    $params[] = $new_image;
    $types .= "s";
}

$sql .= " WHERE post_id = ?";
$params[] = $post_id;
$types .= "i";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    // Delete old image if new uploaded
    if ($new_image && $old_image && file_exists("../../uploads/posts/$old_image")) {
        unlink("../../uploads/posts/$old_image");
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Post updated successfully!',
        'post_id' => $post_id,
        'image' => $new_image ? "http://localhost/uploads/posts/$new_image" : null
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Update failed']);
}

function handleImageUpload($file) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024;
    
    if ($file['error'] !== UPLOAD_ERR_OK) return false;
    if (!in_array($file['type'], $allowed_types)) return false;
    if ($file['size'] > $max_size) return false;
    
    $upload_dir = '../../uploads/posts/';
    if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $_SESSION['user_id'] . '_edit_' . time() . '.' . $extension;
    
    return move_uploaded_file($file['tmp_name'], $upload_dir . $filename) ? $filename : false;
}
?>