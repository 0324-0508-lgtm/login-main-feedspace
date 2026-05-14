<?php
header("Content-Type: application/json");

require_once __DIR__ . '/../config/db.php';

$data = json_decode(file_get_contents("php://input"), true);

// fallback if not JSON
if (!$data) {
    $data = $_POST;
}

$email = trim($data['email'] ?? '');
$password = trim($data['password'] ?? '');

if (empty($email) || empty($password)) {
    echo json_encode([
        "success" => false,
        "message" => "Email or password is empty",
        "debug_email" => $email
    ]);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode([
        "success" => false,
        "message" => "User not found",
        "debug_email" => $email
    ]);
    exit;
}

if (!password_verify($password, $user['password_hash'])) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid credentials"
    ]);
    exit;
}

echo json_encode([
    "success" => true,
    "message" => "Login successful",
    "user" => [
        "user_id" => $user['user_id'],
        "name" => $user['first_name'] . ' ' . $user['last_name'],
        "email" => $user['email'],
        "role" => $user['role']
    ]
]);