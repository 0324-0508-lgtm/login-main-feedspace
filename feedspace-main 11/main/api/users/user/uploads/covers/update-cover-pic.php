<?php
// Update user cover photo
session_start();
include '../../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

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

// Handle cover photo upload
if (!isset($_FILES['cover_photo']) || $_FILES['cover_photo']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No cover photo uploaded']);
    exit();
}

$file = $_FILES['cover_photo'];
$allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
$max_size = 5 * 1024 * 1024; // 5MB

// Validate file
if (!in_array($file['type'], $allowed_types)) {
    http_response_code(400);
    echo json_encode(['error' => 'Only JPG, PNG, GIF allowed']);
    exit();
}

if ($file['size'] > $max_size) {
    http_response_code(400);
    echo json_encode(['error' => 'Max 5MB']);
    exit();
}

// Create uploads directory
$upload_dir = '../../uploads/covers/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Generate secure filename
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = $user_id . '_cover_' . time() . '.' . $extension;
$filepath = $upload_dir . $filename;

// Move file
if (move_uploaded_file($file['tmp_name'], $filepath)) {
    // Update database
    $stmt = $conn->prepare("UPDATE users SET cover_photo = ? WHERE user_id = ?");
    $stmt->bind_param("ss", $filename, $user_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Cover photo updated!',
            'cover_photo' => "http://localhost/uploads/covers/$filename"
        ]);
    } else {
        unlink($filepath);
        http_response_code(500);
        echo json_encode(['error' => 'Database update failed']);
    }
} else {
    http_response_code(500);
    echo json_encode(['error' => 'File upload failed']);
}
?>