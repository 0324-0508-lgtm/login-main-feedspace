<?php
// API endpoint to verify OTP for user registration or password reset
session_start();
include '../../config/db.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$user_id = $_POST['user_id'] ?? '';
$otp_code = trim($_POST['otp_code'] ?? '');

if (empty($user_id) || empty($otp_code)) {
    http_response_code(400);
    echo json_encode(['error' => 'user_id and otp_code required']);
    exit();
}

// Check OTP validity
$stmt = $conn->prepare("
    SELECT id, expires_at 
    FROM otp 
    WHERE user_id = ? 
    AND otp_code = ? 
    AND expires_at > NOW() 
    AND is_used = 0
");
$stmt->bind_param("ss", $user_id, $otp_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid or expired OTP']);
    exit();
}

// Mark OTP as used
$otp_id = $result->fetch_assoc()['id'];
$stmt = $conn->prepare("UPDATE otp SET is_used = 1 WHERE id = ?");
$stmt->bind_param('i', $otp_id);
$stmt->execute();
$stmt->close();

// Optional: Update user verification status
$stmt = $conn->prepare("UPDATE users SET is_verified = 1 WHERE user_id = ?");
$stmt->bind_param('s', $user_id);
$stmt->execute();
$stmt->close();

echo json_encode([
    'success' => true,
    'message' => 'OTP verified successfully!',
    'user_id' => $user_id
]);
?>