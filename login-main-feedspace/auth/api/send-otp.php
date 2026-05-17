<?php
header("Content-Type: application/json");

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/mailer.php';
require_once __DIR__ . '/../../includes/otp-generator.php';

$data = $_POST;

$user_id = trim($data['user_id'] ?? '');

if (!$user_id) {
    echo json_encode([
        'success' => false,
        'error' => 'Missing user_id'
    ]);
    exit;
}

// Generate a new 6-digit login OTP
$otp = generateOTP();

// Delete existing login OTPs for this user (avoid ambiguity)
$delete = $pdo->prepare("DELETE FROM otp WHERE user_id = ? AND type = 'login'");
$delete->execute([$user_id]);

// Insert OTP with 3-minute expiry
$insert = $pdo->prepare("
    INSERT INTO otp (user_id, otp_code, type, expires_at)
    VALUES (?, ?, 'login', DATE_ADD(NOW(), INTERVAL 3 MINUTE))
");
$insert->execute([$user_id, $otp]);

// Fetch user email
$stmt = $pdo->prepare("SELECT email FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || empty($user['email'])) {
    echo json_encode([
        'success' => false,
        'error' => 'User email not found'
    ]);
    exit;
}

$sent = sendOtpEmail($user['email'], (string)$otp);

if (!$sent) {
    echo json_encode([
        'success' => false,
        'error' => 'OTP generated but email failed to send'
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'OTP resent',
    'user_id' => $user_id
]);

