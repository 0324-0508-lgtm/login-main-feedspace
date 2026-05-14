<?php
header("Content-Type: application/json");
require_once __DIR__ . '/../config/db.php';

$data = $_POST; // IMPORTANT (because you use URLSearchParams)

$email = trim($data['email'] ?? '');

if (empty($email)) {
    echo json_encode(["success" => false, "message" => "Email required"]);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode([
        "success" => false,
        "message" => "User not found",
        "debug_email" => $email
    ]);
    exit;
}

$otp = rand(100000, 999999);

// DELETE existing OTP first
$delete = $pdo->prepare("
DELETE FROM otp
WHERE user_id = ? AND type = 'login'
");
$delete->execute([$user['user_id']]);

// INSERT new OTP
$stmt = $pdo->prepare("
INSERT INTO otp (user_id, otp_code, type, expires_at)
VALUES (?, ?, 'login', DATE_ADD(NOW(), INTERVAL 10 MINUTE))
");

$stmt->execute([$user['user_id'], $otp]);

echo json_encode([
    "success" => true,
    "message" => "OTP sent",
    "user_id" => $user['user_id'],
    "otp_code" => $otp
]);