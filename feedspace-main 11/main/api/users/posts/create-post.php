<?php
// api/users/posts/create-post.php
session_start();
include '../../config/db.php';
include '../../config/ban-check.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Check if banned
if (isUserBanned($_SESSION['user_id'], $conn)) {
    http_response_code(403);
    echo json_encode(['error' => 'Account banned']);
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$content = trim($_POST['content'] ?? '');
$image = null;

// Validation
if (empty($content) && empty($_FILES['image']['name'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Content or image required']);
    exit();
}

if (strlen($content) > 1000) {
    http_response_code(400);
    echo json_encode(['error' => 'Content max 1000 characters']);
    exit();
}

// Handle image
if (!empty($_FILES['image']['name'])) {
    $image = handleImageUpload($_FILES['image']);
    if (!$image) {
        http_response_code(400);
        echo json_encode(['error' => 'Image upload failed']);
        exit();
    }
}

// Insert post
$stmt = $conn->prepare("INSERT INTO posts (user_id, content, image, created_at) VALUES (?, ?, ?, NOW())");
$stmt->bind_param("sss", $user_id, $content, $image);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'post_id' => $conn->insert_id,
        'image' => $image ? "http://localhost/uploads/posts/$image" : null
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create post']);
}

function handleImageUpload($file) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024;
    
    if ($file['error'] !== UPLOAD_ERR_OK) return false;
    if (!in_array($file['type'], $allowed_types)) return false;
    if ($file['size'] > $max_size) return false;
    
    $upload_dir = '../../uploads/posts/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $_SESSION['user_id'] . '_' . time() . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    return move_uploaded_file($file['tmp_name'], $filepath) ? $filename : false;
}
?>