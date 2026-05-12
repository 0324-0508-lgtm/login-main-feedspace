<?php
// API endpoint to send OTP for user registration or password reset
session_start();
include '../../config/db.php';

header('Content-Type: application/json; charset=utf-8');
// Only allow POST requests
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}
// Get email and user_id from POST data
$email = trim($_POST['email'] ?? '');
$user_id = $_POST['user_id'] ?? '';
// Validate input
if (empty($email) || empty($user_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Email and user_id required']);
    exit();
}

// Check if user exists with given email and user_id
$stmt = $conn->prepare("SELECT first_name FROM users WHERE user_id = ? AND email = ?");
$stmt->bind_param("ss", $user_id, $email);
$stmt->execute();
$result = $stmt->get_result();
// If user not found, return error
if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
    exit();
}
// Get user's first name for personalized email
$user = $result->fetch_assoc();
$first_name = $user['first_name'];

// Generate OTP
$otp = sprintf("%06d", rand(100000, 999999));
$expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));

// Delete old OTPs
$conn->query("DELETE FROM otp WHERE user_id = " . $conn->real_escape_string($user_id));

// Insert new OTP
$stmt = $conn->prepare("INSERT INTO otp (user_id, otp_code, expires_at, created_at) VALUES (?, ?, ?, NOW())");
$stmt->bind_param("sss", $user_id, $otp, $expires_at);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to generate OTP']);
    exit();
}

// Send plain text email
sendEmailOTP($email, $first_name, $otp);

echo json_encode([
    'success' => true,
    'message' => 'OTP sent successfully!',
    'expires_in' => '10 minutes'
]);
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
    
    mail($email, $subject, $message, $headers);
}
?>