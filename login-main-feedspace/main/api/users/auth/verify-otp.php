<?php
session_start();
include '../../../../config/db.php';

date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json');

$user_id = trim($_POST['user_id'] ?? '');
$otp_code = trim($_POST['otp_code'] ?? '');

if (!$user_id || !$otp_code) {
    echo json_encode(['success' => false, 'error' => 'Missing fields']);
    exit;
}

/* 1. FIND VALID OTP */
$stmt = $conn->prepare("
    SELECT * FROM otp
    WHERE user_id = ?
    AND otp_code = ?
    AND type = 'login'
    AND is_used = 0
    AND expires_at > NOW()
    ORDER BY otp_id DESC
    LIMIT 1
");
$stmt->bind_param("ss", $user_id, $otp_code);
$stmt->execute();
$otp = $stmt->get_result()->fetch_assoc();

if (!$otp) {
    echo json_encode(['success' => false, 'error' => 'Invalid or expired OTP']);
    exit;
}

/* 2. MARK AS USED */
$upd = $conn->prepare("UPDATE otp SET is_used = 1 WHERE otp_id = ?");
$upd->bind_param("i", $otp['otp_id']);
$upd->execute();

/* 3. CLEAN OLD OTPs */
$del = $conn->prepare("DELETE FROM otp WHERE user_id = ? AND type = 'login'");
$del->bind_param("s", $user_id);
$del->execute();

/* 4. LOGIN USER */
$_SESSION['user_id'] = $user_id;

echo json_encode(['success' => true, 'user_id' => $user_id]);