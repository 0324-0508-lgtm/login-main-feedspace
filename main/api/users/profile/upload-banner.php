<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}
$userId = $_SESSION['user_id'];

if (empty($_FILES['banner']) || $_FILES['banner']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
    exit;
}

$uploadDir = '../../uploads/banners/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$ext = pathinfo($_FILES['banner']['name'], PATHINFO_EXTENSION);
$filename = $userId . '_banner_' . time() . '.' . $ext;

if (move_uploaded_file($_FILES['banner']['tmp_name'], $uploadDir . $filename)) {
    echo json_encode(['success' => true, 'url' => '../uploads/banners/' . $filename, 'message' => 'Banner uploaded']);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to save file']);
}