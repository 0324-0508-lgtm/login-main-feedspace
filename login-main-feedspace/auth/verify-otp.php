<?php
header("Content-Type: application/json");

require_once __DIR__ . '/../config/db.php';
$data = json_decode(file_get_contents("php://input"), true);

$user_id = $data['user_id'] ?? '';
$otp     = $data['otp'] ?? '';

$stmt = $pdo->prepare("
SELECT * FROM otp
WHERE user_id = ?
AND otp_code = ?
AND is_used = 0
AND expires_at > NOW()
");

$stmt->execute([$user_id, $otp]);

$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid or expired OTP"
    ]);
    exit;
}

$update = $pdo->prepare("
UPDATE otp
SET is_used = 1
WHERE otp_id = ?
");

$update->execute([$row['otp_id']]);

echo json_encode([
    "success" => true,
    "message" => "OTP verified"
]);
?>