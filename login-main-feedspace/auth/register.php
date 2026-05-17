<?php

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);


require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/otp-generator.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $data = $_POST;


$first_name = trim($data['first_name'] ?? '');
$last_name  = trim($data['last_name'] ?? '');
$email      = trim($data['email'] ?? '');
$college    = trim($data['college'] ?? '');

if (!$first_name || !$last_name || !$email) {
    echo json_encode([
        'success' => false,
        'message' => 'All required fields are required'
    ]);
    exit;
}


$stmt = $pdo->prepare('SELECT email FROM users WHERE email = ?');
$stmt->execute([$email]);

if ($stmt->fetch()) {
    echo json_encode([
        'success' => false,
        'message' => 'Email already exists'
    ]);
    exit;
}

$user_id = trim($data['student_id'] ?? '');

// passwordless registration: keep DB constraint satisfied
// (password_hash column is NOT NULL)
$password_hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);


$stmt = $pdo->prepare(
    "INSERT INTO users
(user_id, first_name, last_name, email, password_hash, college)
VALUES (?, ?, ?, ?, ?, ?)"
);

$stmt->execute([
    $user_id,
    $first_name,
    $last_name,
    $email,
    $password_hash,
    $college
]);

$otp = generateOTP();

// Send OTP via email (if mail is configured)
// IMPORTANT: Do not block registration on mail failures.
// If vendor/SMTP is misconfigured, user/otp can still be created.
try {
    require_once __DIR__ . '/../includes/mailer.php';
    $sent = sendOtpEmail($email, (string)$otp);
} catch (Throwable $mailErr) {
    $sent = false;
}


// Save OTP (3-minute expiry)
$stmt = $pdo->prepare(
    "INSERT INTO otp
(user_id, otp_code, type, expires_at)
VALUES (?, ?, 'register', DATE_ADD(NOW(), INTERVAL 3 MINUTE))"
);

$stmt->execute([$user_id, $otp]);

echo json_encode([
    'success' => true,
    'message' => 'Registered successfully',
    'otp' => $otp,
    'user_id' => $user_id
]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Registration failed',
        'error' => $e->getMessage()
    ]);
}


