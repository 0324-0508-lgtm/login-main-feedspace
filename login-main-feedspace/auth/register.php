<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/otp-generator.php';

$data = $_POST;

$first_name = trim($data['first_name'] ?? '');
$last_name  = trim($data['last_name'] ?? '');
$email      = trim($data['email'] ?? '');
$password   = trim($data['password'] ?? '');
$college    = trim($data['college'] ?? '');

if (!$first_name || !$last_name || !$email || !$password) {
    echo json_encode([
        "success" => false,
        "message" => "All fields are required"
    ]);
    exit;
}

$stmt = $pdo->prepare("SELECT email FROM users WHERE email = ?");
$stmt->execute([$email]);

if ($stmt->fetch()) {
    echo json_encode([
        "success" => false,
        "message" => "Email already exists"
    ]);
    exit;
}

$user_id = trim($data['student_id'] ?? '');

// Keep user_id exactly as user input (e.g. 0324-0708).

$password_hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("
INSERT INTO users
(user_id, first_name, last_name, email, password_hash, college)
VALUES (?, ?, ?, ?, ?, ?)
");

$stmt->execute([
    $user_id,
    $first_name,
    $last_name,
    $email,
    $password_hash,
    $college
]);

$otp = generateOTP();

$stmt = $pdo->prepare("
INSERT INTO otp
(user_id, otp_code, type, expires_at)
VALUES (?, ?, 'register', DATE_ADD(NOW(), INTERVAL 10 MINUTE))
");

$stmt->execute([$user_id, $otp]);

echo json_encode([
    "success" => true,
    "message" => "Registered successfully",
    "otp" => $otp,
    "user_id" => $user_id
]);
?>