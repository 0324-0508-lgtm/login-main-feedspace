<?php
header("Content-Type: application/json");

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/mailer.php';

$data = $_POST; // IMPORTANT (because you use URLSearchParams)
$email = trim($data['email'] ?? '');

if (empty($email)) {
    echo json_encode(["success" => false, "message" => "Email required"]);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
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
$delete = $conn->prepare("DELETE FROM otp WHERE user_id = ? AND type = 'login'");
$delete->execute([$user['user_id']]);

// INSERT new OTP with 3-minute expiry
$stmt = $conn->prepare("INSERT INTO otp (user_id, otp_code, type, expires_at)
VALUES (?, ?, 'login', DATE_ADD(NOW(), INTERVAL 3 MINUTE))");
$stmt->execute([$user['user_id'], $otp]);

// Send OTP via email
$sent = sendOtpEmail($user['email'], (string)$otp);

if (!$sent) {
    echo json_encode([
        "success" => false,
        "message" => "OTP generated but email failed to send",
        "debug_email" => $user['email'],
        "hint" => "Check SMTP credentials/TLS in config/mail.php and confirm vendor/autoload.php exists."
    ]);
    exit;
}

echo json_encode([
    "success" => true,
    "message" => "OTP sent",
    "user_id" => $user['user_id'],
    "otp_code" => $otp
]);

