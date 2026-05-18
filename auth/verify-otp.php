<?php
session_start(); 
header("Content-Type: application/json");
require_once __DIR__ . '/../config/db.php';

$data = $_POST;

$user_id = trim($data['user_id'] ?? '');
$otp_code = trim($data['otp_code'] ?? '');

if (!$user_id || !$otp_code) {
    echo json_encode([
        "success" => false,
        "message" => "Missing OTP or user ID"
    ]);
    exit;
}

$mode = trim($data['mode'] ?? 'login');
if ($mode === '' || $mode === 'null' || $mode === null) $mode = 'login';

// Normalize OTP/code
$otp_code = preg_replace('/\D/', '', $otp_code);


$stmt = $conn->prepare("
SELECT * FROM otp
WHERE user_id = ? AND otp_code = ? AND type = ?
ORDER BY expires_at DESC
LIMIT 1
");

$stmt->execute([$user_id, $otp_code, $mode]);
$otp = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$otp) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid OTP"
    ]);
    exit;
}

if (strtotime($otp['expires_at']) < time()) {
    echo json_encode([
        "success" => false,
        "message" => "OTP expired"
    ]);
    exit;
}
// optional: delete OTP after success
$_SESSION['user_id'] = $user_id;
$_SESSION['logged_in'] = true;

echo json_encode([
    "success" => true,
    "message" => "OTP verified"
]);