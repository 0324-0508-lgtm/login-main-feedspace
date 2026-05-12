<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();
include '../../../../config/db.php';
require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$user_id = trim($_POST['user_id'] ?? '');
$email   = trim($_POST['email'] ?? '');

if (!$user_id && !$email) {
    echo json_encode(['success' => false, 'error' => 'Missing user_id or email']);
    exit();
}

/* ---------------- FETCH USER ---------------- */
if ($user_id) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->bind_param("s", $user_id);
} else {
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
}

$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit();
}

$user_id = $user['user_id'];

/* ---------------- DELETE OLD OTP ---------------- */
$delete = $conn->prepare("
    DELETE FROM otp
    WHERE user_id = ?
    AND type = 'login'
");

if (!$delete) {
    echo json_encode(['success' => false, 'error' => $conn->error]);
    exit();
}

$delete->bind_param("s", $user_id);
$delete->execute();

/* ---------------- GENERATE OTP ---------------- */
$otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expires_at = date('Y-m-d H:i:s', strtotime('+3 minutes'));

/* ---------------- INSERT OTP ---------------- */
$otpStmt = $conn->prepare("
    INSERT INTO otp (user_id, otp_code, type, expires_at, is_used)
    VALUES (?, ?, 'login', ?, 0)
");

if (!$otpStmt) {
    echo json_encode(['success' => false, 'error' => $conn->error]);
    exit();
}

$otpStmt->bind_param("sss", $user_id, $otp, $expires_at);

if (!$otpStmt->execute()) {
    echo json_encode(['success' => false, 'error' => 'Failed to save OTP']);
    exit();
}

/* ---------------- SEND EMAIL ---------------- */
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

    $mail->Body = "
        <div style='font-family: sans-serif; max-width: 480px; margin: auto;'>
            <h2 style='color: #4141ff;'>FeedSpace Verification</h2>
            <p>Hi {$user['first_name']},</p>
            <p>Your OTP code is:</p>
            <div style='font-size: 36px; font-weight: 800; letter-spacing: 8px; color: #589eff; margin: 20px 0;'>
                {$otp}
            </div>
            <p>Expires in <strong>3 minutes</strong>.</p>
        </div>
    ";

    $mail->send();

    echo json_encode([
        'success' => true,
        'user_id' => $user_id,
        'message' => 'OTP sent'
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $mail->ErrorInfo]);
}
?>