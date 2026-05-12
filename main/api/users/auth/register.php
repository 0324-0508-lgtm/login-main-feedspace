<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

session_start();
include '../../../../config/db.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$first_name = trim($_POST['first_name'] ?? '');
$last_name  = trim($_POST['last_name'] ?? '');
$student_id = trim($_POST['student_id'] ?? '');
$email      = trim($_POST['email'] ?? '');
$password   = $_POST['password'] ?? '';
$bio        = trim($_POST['bio'] ?? '');
$role       = trim($_POST['role'] ?? '');
$college    = trim($_POST['college'] ?? '');

if (empty($first_name) || empty($last_name) || empty($student_id) || empty($email) || empty($password) || empty($role) || empty($college)) {
    http_response_code(400);
    echo json_encode(['error' => 'All fields required']);
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Valid email required']);
    exit();
}

$student_id = preg_replace('/[^0-9]/', '', $student_id);
if (strlen($student_id) !== 8) {
    http_response_code(400);
    echo json_encode(['error' => 'Student ID must be 8 digits']);
    exit();
}
$student_id = substr($student_id, 0, 4) . '-' . substr($student_id, 4);

$check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    http_response_code(409);
    echo json_encode(['error' => 'Email already exists']);
    exit();
}

$check_id = $conn->prepare("SELECT user_id FROM users WHERE user_id = ?");
$check_id->bind_param("s", $student_id);
$check_id->execute();
if ($check_id->get_result()->num_rows > 0) {
    http_response_code(409);
    echo json_encode(['error' => 'Student ID already exists']);
    exit();
}

$user_id = $student_id;
$hashed_password = password_hash($password, PASSWORD_DEFAULT);
$profile_picture = "default.png";
$created_at = date('Y-m-d H:i:s');

$stmt = $conn->prepare("INSERT INTO users (user_id, first_name, last_name, email, password_hash, profile_picture, bio, role, college, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssssssss", $user_id, $first_name, $last_name, $email, $hashed_password, $profile_picture, $bio, $role, $college, $created_at);

if ($stmt->execute()) {
    $_SESSION['user_id'] = $user_id;
    $_SESSION['email'] = $email;

    $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    $deleteOtp = $conn->prepare("DELETE FROM otp WHERE user_id = ?");
    $deleteOtp->bind_param("s", $user_id);
    $deleteOtp->execute();

    $otp_stmt = $conn->prepare("INSERT INTO otp (user_id, otp_code, type, expires_at, created_at) VALUES (?, ?, 'login', ?, NOW())");
    $otp_stmt->bind_param("sss", $user_id, $otp, $expires_at);
    $otp_stmt->execute();

    $emailSent = sendEmailOTP($email, $first_name, $otp);

    echo json_encode([
        'success'  => true,
        'user_id'  => $user_id,
        'otp_code' => $otp, // remove in production
        'message'  => 'Account created! Please verify your email.'
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Registration failed: ' . $stmt->error]);
}

function sendEmailOTP($email, $first_name, $otp) {
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
        $mail->addAddress($email, $first_name);
        $mail->isHTML(true);
        $mail->Subject = 'Your FeedSpace Verification Code';
        $mail->Body    = "
            <div style='font-family: sans-serif; max-width: 480px; margin: auto;'>
                <h2 style='color: #2a75ff;'>FeedSpace Verification</h2>
                <p>Hi {$first_name},</p>
                <p>Your one-time verification code is:</p>
                <div style='font-size: 36px; font-weight: 800; letter-spacing: 8px; color: #4a77ff; margin: 20px 0;'>
                    {$otp}
                </div>
                <p>This code expires in <strong>10 minutes</strong>.</p>
                <p style='color: #888; font-size: 13px;'>If you didn't request this, ignore this email.</p>
            </div>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Mail error: ' . $mail->ErrorInfo);
        return false;
    }
}
?>