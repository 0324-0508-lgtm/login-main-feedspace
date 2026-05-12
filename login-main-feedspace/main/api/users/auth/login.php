<?php
session_start();
include '../../../../config/db.php';
require_once __DIR__ . '/vendor/autoload.php';

date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json');

use PHPMailer\PHPMailer\PHPMailer;

$identifier = trim($_POST['identifier'] ?? '');
$password = $_POST['password'] ?? '';

if (!$identifier || !$password) {
    echo json_encode(['success' => false, 'error' => 'Missing credentials']);
    exit;
}

/* 1. GET USER */
$stmt = $conn->prepare("SELECT * FROM users WHERE email = ? OR user_id = ?");
$stmt->bind_param("ss", $identifier, $identifier);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit;
}

/* 2. VERIFY PASSWORD */
if (!password_verify($password, $user['password_hash'])) {
    echo json_encode(['success' => false, 'error' => 'Incorrect password']);
    exit;
}

/* 3. DELETE OLD OTPs (IMPORTANT FIX) */
$del = $conn->prepare("DELETE FROM otp WHERE user_id = ? AND type = 'login'");
$del->bind_param("s", $user['user_id']);
$del->execute();

/* 4. GENERATE OTP */
$otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expires = date('Y-m-d H:i:s', strtotime('+3 minutes'));

/* 5. INSERT OTP */
$ins = $conn->prepare("
    INSERT INTO otp (user_id, otp_code, type, expires_at, is_used)
    VALUES (?, ?, 'login', ?, 0)
");
$ins->bind_param("sss", $user['user_id'], $otp, $expires);
$ins->execute();

/* 6. SEND EMAIL */
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = $_ENV['MAIL_HOST'];
    $mail->SMTPAuth = true;
    $mail->Username = $_ENV['MAIL_USERNAME'];
    $mail->Password = $_ENV['MAIL_PASSWORD'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = $_ENV['MAIL_PORT'];

    $mail->setFrom($_ENV['MAIL_USERNAME'], $_ENV['MAIL_FROM_NAME']);
    $mail->addAddress($user['email']);

    $mail->isHTML(true);
    $mail->Subject = "Your OTP Code";
    $mail->Body = "
        <h2>OTP Login</h2>
        <p>Your code is:</p>
        <h1>{$otp}</h1>
        <p>Expires in 3 minutes</p>
    ";

    $mail->send();

    echo json_encode([
        'success' => true,
        'user_id' => $user['user_id']
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Email failed']);
}