<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

session_start();

$user_id  = $_POST['user_id']  ?? null;
$otp_code = $_POST['otp_code'] ?? null;

if (!$user_id || !$otp_code) {
    echo json_encode(['success' => false, 'error' => 'Missing fields']);
    exit;
}

// Check OTP using your actual column names
$stmt = $pdo->prepare("
    SELECT * FROM otp
    WHERE user_id = ? 
    AND otp_code = ? 
    AND type = 'login'
    AND is_used = 0 
    AND expires_at > NOW()
    ORDER BY created_at DESC
    LIMIT 1
");
$stmt->execute([$user_id, $otp_code]);
$otp = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$otp) {
    echo json_encode(['success' => false, 'error' => 'Invalid or expired code']);
    exit;
}

// Mark as used using your actual column names
$pdo->prepare("
    UPDATE otp SET is_used = 1 WHERE otp_id = ?
")->execute([$otp['otp_id']]);

// Log user in
$_SESSION['user_id'] = $user_id;

echo json_encode(['success' => true]);