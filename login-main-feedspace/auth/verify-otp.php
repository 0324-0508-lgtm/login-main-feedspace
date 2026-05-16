<?php
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

$stmt = $pdo->prepare("
SELECT * FROM otp
WHERE user_id = ? AND otp_code = ? AND type = 'login'
ORDER BY expires_at DESC
LIMIT 1
");

$stmt->execute([$user_id, $otp_code]);
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
$del = $pdo->prepare("DELETE FROM otp WHERE user_id = ? AND type = 'login'");
$del->execute([$user_id]);

echo json_encode([
    "success" => true,
    "message" => "OTP verified"
]);