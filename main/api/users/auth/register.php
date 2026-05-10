<?php
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

// Basic validation
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

// Normalize student ID to XXXX-XXXX format
$student_id = preg_replace('/[^0-9]/', '', $student_id);
if (strlen($student_id) !== 8) {
    http_response_code(400);
    echo json_encode(['error' => 'Student ID must be 8 digits']);
    exit();
}
$student_id = substr($student_id, 0, 4) . '-' . substr($student_id, 4);

// Check email exists
$check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    http_response_code(409);
    echo json_encode(['error' => 'Email already exists']);
    exit();
}

// Check student ID exists
$check_id = $conn->prepare("SELECT user_id FROM users WHERE user_id = ?");
$check_id->bind_param("s", $student_id);
$check_id->execute();
if ($check_id->get_result()->num_rows > 0) {
    http_response_code(409);
    echo json_encode(['error' => 'Student ID already exists']);
    exit();
}

// Use the provided student ID as the user_id
$user_id = $student_id;

// Insert user
$hashed_password = password_hash($password, PASSWORD_DEFAULT);
$profile_picture = "default.png";
$created_at = date('Y-m-d H:i:s');

$stmt = $conn->prepare("INSERT INTO users (user_id, first_name, last_name, email, password_hash, profile_picture, bio, role, college, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param(
    "ssssssssss",
    $user_id, $first_name, $last_name, $email, $hashed_password,
    $profile_picture, $bio, $role, $college, $created_at
);

if ($stmt->execute()) {
    $_SESSION['user_id'] = $user_id;
    $_SESSION['email'] = $email;

    // Generate OTP for email verification
    $otp = sprintf("%06d", rand(100000, 999999));
    $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    // Delete old OTPs
    $deleteOtp = $conn->prepare("DELETE FROM otp WHERE user_id = ?");
    $deleteOtp->bind_param("s", $user_id);
    $deleteOtp->execute();

    // Insert new OTP
    $otp_stmt = $conn->prepare("INSERT INTO otp (user_id, otp_code, expires_at, created_at) VALUES (?, ?, ?, NOW())");
    $otp_stmt->bind_param("sss", $user_id, $otp, $expires_at);
    $otp_stmt->execute();

    // Send OTP email (optional, SMTP may not be configured)
    @sendEmailOTP($email, $first_name, $otp);

    echo json_encode([
        'success' => true,
        'user_id' => $user_id,
        'message' => 'Account created! Please verify your email.'
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Registration failed: ' . $stmt->error]);
}

// Function to send OTP email
function sendEmailOTP($email, $first_name, $otp) {
    $subject = "Your Verification Code";
    $message = "Hello $first_name!\n\n";
    $message .= "Your verification code is: $otp\n\n";
    $message .= "This code expires in 10 minutes.\n";
    $message .= "If you didn't request this, ignore this email.\n";

    $headers = "From: noreply@yourapp.com\r\n";
    $headers .= "Reply-To: support@yourapp.com\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    @mail($email, $subject, $message, $headers);
}
?>