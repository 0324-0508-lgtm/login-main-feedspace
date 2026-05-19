<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}
$userId = $_SESSION['user_id'];

if (empty($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
    exit;
}

$uploadDir = '../../uploads/profile/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
$filename = $userId . '_' . time() . '.' . $ext;

if (move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadDir . $filename)) {
    $stmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE user_id = ?");
    $stmt->execute([$filename, $userId]);
    echo json_encode(['success' => true, 'url' => '../uploads/profile/' . $filename, 'message' => 'Avatar uploaded']);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to save file']);
}