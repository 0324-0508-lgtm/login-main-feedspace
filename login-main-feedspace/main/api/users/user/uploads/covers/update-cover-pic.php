<?php
// ============================================================
//  update-cover-pic.php — Update cover/banner photo only
//
//  Auth: session OR POST user_id (localStorage flow)
//  FILES: cover_photo (required)
// ============================================================
session_start();

// FIXED: correct path — 6 levels up from covers/ to project root
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

if (!isset($_FILES['cover_photo']) || $_FILES['cover_photo']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No cover photo uploaded']);
    exit();
}

$file          = $_FILES['cover_photo'];
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$max_size      = 5 * 1024 * 1024; // 5 MB

if (!in_array($file['type'], $allowed_types)) {
    http_response_code(400);
    echo json_encode(['error' => 'Only JPG, PNG, GIF, WEBP allowed']);
    exit();
}

if ($file['size'] > $max_size) {
    http_response_code(400);
    echo json_encode(['error' => 'Max 5MB']);
    exit();
}

// FIXED: upload dir relative to this file's __DIR__
// This file is at: main/api/users/user/uploads/covers/
$upload_dir = __DIR__ . '/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$filename  = $user_id . '_cover_' . time() . '.' . $extension;
$filepath  = $upload_dir . $filename;

if (move_uploaded_file($file['tmp_name'], $filepath)) {
    $stmt = $conn->prepare('UPDATE users SET cover_photo = ? WHERE user_id = ?');
    $stmt->bind_param('ss', $filename, $user_id);

    if ($stmt->execute()) {
        echo json_encode([
            'success'     => true,
            'message'     => 'Cover photo updated!',
            'cover_photo' => '/main/api/users/user/uploads/covers/' . $filename,
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
