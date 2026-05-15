<?php
// ============================================================
//  update-profile-pic.php — Update profile picture only
//
//  Auth: session OR POST user_id (localStorage flow)
//  FILES: profile_picture (required)
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

if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No profile picture uploaded']);
    exit();
}

$file          = $_FILES['profile_picture'];
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$max_size      = 2 * 1024 * 1024; // 2 MB

if (!in_array($file['type'], $allowed_types)) {
    http_response_code(400);
    echo json_encode(['error' => 'Only JPG, PNG, GIF, WEBP allowed']);
    exit();
}

if ($file['size'] > $max_size) {
    http_response_code(400);
    echo json_encode(['error' => 'Max 2MB']);
    exit();
}

// FIXED: upload dir relative to this file's __DIR__
$upload_dir = __DIR__ . '/../profiles/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$filename  = $user_id . '_' . time() . '.' . $extension;
$filepath  = $upload_dir . $filename;

if (move_uploaded_file($file['tmp_name'], $filepath)) {
    $stmt = $conn->prepare('UPDATE users SET profile_picture = ? WHERE user_id = ?');
    $stmt->bind_param('ss', $filename, $user_id);

    if ($stmt->execute()) {
        echo json_encode([
            'success'         => true,
            'message'         => 'Profile picture updated!',
            'profile_picture' => '/main/api/users/user/uploads/profiles/' . $filename,
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
