<?php
// API endpoint to handle forgot password requests
session_start();
include '../../config/db.php';

header('Content-Type: application/json; charset=utf-8');
// Only allow POST requests
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}
// Get email from POST data
$email = trim($_POST['email'] ?? '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Valid email required']);
    exit();
}

// Check user exists
$stmt = $conn->prepare("SELECT user_id, first_name FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Email not found']);
    exit();
}

$user = $result->fetch_assoc();
$user_id = $user['user_id'];
$first_name = $user['first_name'];

// Generate 6-digit reset token
do {
    $reset_token = str_pad(rand(0, 999999), 6, "0", STR_PAD_LEFT);
    
    $check = $conn->prepare("SELECT id FROM password_reset WHERE token = ?");
    $check->bind_param("s", $reset_token);
    $check->execute();
} while ($check->get_result()->num_rows > 0);

$expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

// Delete old tokens
$conn->query("DELETE FROM password_reset WHERE user_id = " . $conn->real_escape_string($user_id));

// Insert new token
$stmt = $conn->prepare("INSERT INTO password_reset (user_id, token, expires_at) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $user_id, $reset_token, $expires_at);
$stmt->execute();

sendResetEmail($email, $first_name, $reset_token, $user_id);

echo json_encode([
    'success' => true,
    'message' => 'Password reset link sent!',
    'expires_in' => '1 hour'
]);

function sendResetEmail($email, $first_name, $token, $user_id) {
    $reset_link = "http://localhost/reset-password.php?token=$token&user_id=$user_id";
    $subject = "Reset Your Password";
    $message = "Hello $first_name!\n\n";
    $message .= "Click to reset password: $reset_link\n\n";
    $message .= "Token: $token\n";
    $message .= "Expires in 1 hour.\n\n";
    $message .= "If you didn't request, ignore this.\n";
    
    $headers = "From: noreply@yourapp.com\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    mail($email, $subject, $message, $headers);
}
?>