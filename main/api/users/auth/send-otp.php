<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/db.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

header('Content-Type: application/json');

$user_id = $_POST['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'error' => 'Missing user_id']);
    exit;
}

// Get user from DB
$stmt = $pdo->prepare("SELECT email, first_name FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit;
}

// Generate OTP
$otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expires_at = date('Y-m-d H:i:s', strtotime('+3 minutes'));

// Save OTP to your existing `otp` table
$stmt = $pdo->prepare("
    INSERT INTO otp (user_id, otp_code, type, expires_at, is_used)
    VALUES (?, ?, 'login', ?, 0)
    ON DUPLICATE KEY UPDATE 
        otp_code = VALUES(otp_code), 
        expires_at = VALUES(expires_at), 
        is_used = 0
");
$stmt->execute([$user_id, $otp, $expires_at]);

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
    echo json_encode(['success' => true, 'message' => 'OTP sent to your email']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Email failed: ' . $mail->ErrorInfo]);
}