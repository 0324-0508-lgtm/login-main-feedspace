<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Find db.php
$paths = [
    __DIR__ . '/../config/db.php',
    __DIR__ . '/../../config/db.php',
    __DIR__ . '/../../../config/db.php',
];

$dbPath = null;
foreach ($paths as $path) {
    if (file_exists($path)) {
        $dbPath = $path;
        break;
    }
}

if (!$dbPath) {
    echo json_encode(['success' => false, 'error' => 'db.php not found']);
    exit;
}

require_once $dbPath;

// Support both $conn and $pdo variable names from db.php
$db = $conn ?? $pdo ?? null;
if (!$db) {
    echo json_encode(['success' => false, 'error' => 'No DB connection']);
    exit;
}

// Get POST data (supports both JSON and form data)
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$user_id = $input['user_id'] ?? '';
$otp_code = $input['otp_code'] ?? '';
$mode = $input['mode'] ?? 'login';

if (empty($user_id) || empty($otp_code)) {
    echo json_encode(['success' => false, 'error' => 'Missing user_id or otp_code']);
    exit;
}

try {
    // Verify OTP using CORRECT table name `otp` and column `is_used`
    $stmt = $db->prepare("SELECT * FROM otp WHERE user_id = ? AND otp_code = ? AND type = ? AND expires_at > NOW() AND is_used = 0 ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$user_id, $otp_code, $mode]);
    $otp = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$otp) {
        echo json_encode(['success' => false, 'error' => 'Invalid or expired OTP']);
        exit;
    }

    // Mark OTP as used
    $update = $db->prepare("UPDATE otp SET is_used = 1 WHERE otp_id = ?");
    $update->execute([$otp['otp_id']]);

    // Set session
    $_SESSION['user_id'] = $user_id;

    echo json_encode(['success' => true, 'message' => 'Login successful', 'user_id' => $user_id]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}