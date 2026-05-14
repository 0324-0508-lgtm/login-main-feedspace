<?php
header("Content-Type: application/json");

require_once __DIR__ . '/../config/db.php';
require "includes/otp-generator.php";

$data = json_decode(file_get_contents("php://input"), true);

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

$user_id = strtoupper(substr(md5(uniqid()), 0, 9));

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