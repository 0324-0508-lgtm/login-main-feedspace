<?php
// ============================================================
//  update-profile.php — Update display name, bio, and images
//
//  Auth: session OR POST user_id (localStorage flow)
//  POST params: user_id, first_name, last_name, bio
//  FILES: profile_picture (optional), cover_photo (optional)
// ============================================================
session_start();

// FIXED: correct path — 6 levels up from profiles/ to project root
require_once __DIR__ . '/../../../../../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

// Auth: accept session OR POST user_id
$user_id = $_SESSION['user_id'] ?? trim($_POST['user_id'] ?? '');

if (empty($user_id)) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Handle file uploads
$profile_picture = handleFileUpload('profile_picture', 'profiles');
$cover_photo     = handleFileUpload('cover_photo', 'covers');

$first_name = trim($_POST['first_name'] ?? '');
$last_name  = trim($_POST['last_name']  ?? '');
$bio        = trim($_POST['bio']        ?? '');

// Validation
if (strlen($first_name) < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'First name is required']);
    exit();
}
if (!empty($bio) && strlen($bio) > 500) {
    http_response_code(400);
    echo json_encode(['error' => 'Bio max 500 characters']);
    exit();
}

// Build update query dynamically
$sql    = 'UPDATE users SET first_name = ?, last_name = ?, bio = ?';
$params = [$first_name, $last_name, $bio];
$types  = 'sss';

if ($profile_picture) {
    $sql     .= ', profile_picture = ?';
    $params[] = $profile_picture;
    $types   .= 's';
}
if ($cover_photo) {
    $sql     .= ', cover_photo = ?';
    $params[] = $cover_photo;
    $types   .= 's';
}

$sql     .= ' WHERE user_id = ?';
$params[] = $user_id;
$types   .= 's';

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    echo json_encode([
        'success'         => true,
        'message'         => 'Profile updated successfully!',
        'profile_picture' => $profile_picture ?: null,
        'cover_photo'     => $cover_photo     ?: null,
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Update failed']);
}

// ── File upload helper ────────────────────────────────────────
function handleFileUpload($field_name, $upload_dir) {
    if (!isset($_FILES[$field_name]) || $_FILES[$field_name]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $file          = $_FILES[$field_name];
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size      = 5 * 1024 * 1024; // 5 MB

    if (!in_array($file['type'], $allowed_types)) {
        return null; // silently skip invalid type
    }
    if ($file['size'] > $max_size) {
        return null; // silently skip oversized file
    }

    // FIXED: upload path relative to THIS file's directory
    // This file is at: main/api/users/user/uploads/profiles/
    // Uploads folder:  main/api/users/user/uploads/{dir}/
    $upload_path = __DIR__ . '/../' . $upload_dir . '/';
    if (!file_exists($upload_path)) {
        mkdir($upload_path, 0755, true);
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename  = uniqid() . '_' . time() . '.' . $extension;
    $full_path = $upload_path . $filename;

    if (move_uploaded_file($file['tmp_name'], $full_path)) {
        return $filename;
    }

    return null;
}
