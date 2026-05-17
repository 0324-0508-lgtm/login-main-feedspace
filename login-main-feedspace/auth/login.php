<?php
header("Content-Type: application/json");

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/mailer.php';

$data = json_decode(file_get_contents("php://input"), true);
if (!$data) {
    $data = $_POST;
}

$identifier = trim($data['identifier'] ?? '');
$password = trim($data['password'] ?? '');

if (empty($identifier) || empty($password)) {
    echo json_encode(["success" => false, "message" => "Missing fields"]);
    exit;
}

/* 1. FIND USER */
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR student_id = ?");
$stmt->execute([$identifier, $identifier]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(["success" => false, "message" => "User not found"]);
    exit;
}

/* 2. CHECK PASSWORD */
if (!password_verify($password, $user['password_hash'])) {
    echo json_encode(["success" => false, "message" => "Invalid credentials"]);
    exit;
}

/* 3. GENERATE OTP */
$otp = random_int(100000, 999999);

/* 4. SAVE OTP TO DB */
$stmt = $pdo->prepare(
    "UPDATE users SET otp_code = ?, otp_expiry = DATE_ADD(NOW(), INTERVAL 3 MINUTE) WHERE user_id = ?"
);
$stmt->execute([$otp, $user['user_id']]);

/* 5. SEND EMAIL */
$email = $user['email'];
$sent = sendOtpEmail($user['email'], (string)$otp);

/* 6. RESPONSE */
echo json_encode([
    "success" => true,
    "message" => "OTP sent",
    "user_id" => $user['user_id'],
    "email_sent" => $sent
]);
exit;

