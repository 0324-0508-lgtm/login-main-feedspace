<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();
include '../../../../config/db.php';
require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Accept either user_id or email
$user_id = trim($_POST['user_id'] ?? '');
$email   = trim($_POST['email'] ?? '');

if (empty($user_id) && empty($email)) {
    echo json_encode(['success' => false, 'error' => 'Missing user_id or email']);
    exit();
}

// Get user by user_id or email
if (!empty($user_id)) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->bind_param("s", $user_id);
} else {
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
}

$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit();
}

// Generate OTP
$otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expires_at = date('Y-m-d H:i:s', strtotime('+3 minutes'));

// Save OTP to DB
$otpStmt = $conn->prepare("
    INSERT INTO otp (user_id, otp_code, type, expires_at, is_used)
    VALUES (?, ?, 'login', ?, 0)
    ON DUPLICATE KEY UPDATE 
        otp_code = VALUES(otp_code), 
        expires_at = VALUES(expires_at), 
        is_used = 0
");
$otpStmt->bind_param("sss", $user['user_id'], $otp, $expires_at);
$otpStmt->execute();

// Send email
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = $_ENV['MAIL_HOST'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $_ENV['MAIL_USERNAME'];
    $mail->Password   = $_ENV['MAIL_PASSWORD'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = $_ENV['MAIL_PORT'];

    $mail->setFrom($_ENV['MAIL_USERNAME'], $_ENV['MAIL_FROM_NAME']);
    $mail->addAddress($user['email'], $user['first_name']);

    $mail->isHTML(true);
    $mail->Subject = 'Your FeedSpace Verification Code';
    $mail->Body    = "
        <div style='font-family: sans-serif; max-width: 480px; margin: auto;'>
            <h2 style='color: #7c3aed;'>FeedSpace Verification</h2>
            <p>Hi {$user['first_name']},</p>
            <p>Your one-time login code is:</p>
            <div style='font-size: 36px; font-weight: 800; letter-spacing: 8px; color: #7c3aed; margin: 20px 0;'>
                {$otp}
            </div>
            <p>This code expires in <strong>3 minutes</strong>.</p>
            <p style='color: #888; font-size: 13px;'>If you didn't request this, ignore this email.</p>
        </div>
    ";

    $mail->send();

    echo json_encode([
        'success' => true,
        'user_id' => $user['user_id'],
        'message' => 'OTP sent to your email'
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Email failed: ' . $mail->ErrorInfo]);
}
?>