<?php
// API endpoint to update user profile (name, bio, profile pic, cover photo)
session_start();
include '../../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

// Verify logged in
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

// Handle file uploads
$profile_picture = handleFileUpload('profile_picture', 'profiles');
$cover_photo = handleFileUpload('cover_photo', 'covers');

$first_name = trim($_POST['first_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$bio = trim($_POST['bio'] ?? '');

// Validation
if (strlen($first_name) < 2 || strlen($last_name) < 2) {
    http_response_code(400);
    echo json_encode(['error' => 'Name must be 2+ characters']);
    exit();
}
// Bio is optional but max 500 chars
if (!empty($bio) && strlen($bio) > 500) {
    http_response_code(400);
    echo json_encode(['error' => 'Bio max 500 characters']);
    exit();
}

// Build update query
$sql = "UPDATE users SET first_name = ?, last_name = ?, bio = ?";
$params = [$first_name, $last_name, $bio];
$types = "sss";
// Only update images if new ones were uploaded
if ($profile_picture) {
    $sql .= ", profile_picture = ?";
    $params[] = $profile_picture;
    $types .= "s";
}
// Only update cover photo if new one was uploaded
if ($cover_photo) {
    $sql .= ", cover_photo = ?";
    $params[] = $cover_photo;
    $types .= "s";
}
// Finalize query
$sql .= " WHERE user_id = ?";
$params[] = $user_id;
$types .= "s";
// Execute update
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
// Return success response
if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Profile updated successfully!',
        'profile_picture' => $profile_picture ?: null,
        'cover_photo' => $cover_photo ?: null
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Update failed']);
}

// ========================================
// FILE UPLOAD HANDLER
// ========================================
// Handles file uploads for profile pictures and cover photos
function handleFileUpload($field_name, $upload_dir) {
    if (!isset($_FILES[$field_name]) || $_FILES[$field_name]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    // Validate and save file, return filename or null on failure
    $file = $_FILES[$field_name];
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    // Validate
    if (!in_array($file['type'], $allowed_types)) {
        throw new Exception("Invalid file type. Only JPG, PNG, GIF allowed.");
    }
    // Check file size
    if ($file['size'] > $max_size) {
        throw new Exception("File too large. Max 5MB.");
    }
    
    // Create upload dir
    $upload_path = "../../uploads/$upload_dir/";
    if (!file_exists($upload_path)) {
        mkdir($upload_path, 0777, true);
    }
    
    // Generate filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $full_path = $upload_path . $filename;
    
    // Move file
    if (move_uploaded_file($file['tmp_name'], $full_path)) {
        return $filename;
    }
    
    return null;
}
?>