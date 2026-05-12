<?php
session_start();
include '../../config/db.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$first_name = trim($_POST['first_name'] ?? '');
$last_name  = trim($_POST['last_name'] ?? '');
$email      = trim($_POST['email'] ?? '');
$password   = $_POST['password'] ?? '';
$bio        = trim($_POST['bio'] ?? '');

// Basic validation
if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(['error' => 'All fields required']);
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Valid email required']);
    exit();
}

// Check email exists
$check = $conn->prepare("SELECT id FROM users WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    http_response_code(409);
    echo json_encode(['error' => 'Email already exists']);
    exit();
}

// Generate 8-digit user_id (XXXX-XXXX)
do {
    $part1 = str_pad(rand(1000, 9999), 4, "0", STR_PAD_LEFT);
    $part2 = str_pad(rand(1000, 9999), 4, "0", STR_PAD_LEFT);
    $user_id = $part1 . "-" . $part2;

    // Check uniqueness
    $check_id = $conn->prepare("SELECT id FROM users WHERE user_id = ?");
    $check_id->bind_param("s", $user_id);
    $check_id->execute();
} while ($check_id->get_result()->num_rows > 0);

// Insert user
$hashed_password = password_hash($password, PASSWORD_DEFAULT);
$profile_picture = "default.png";
$created_at = date('Y-m-d H:i:s');

$stmt = $conn->prepare("INSERT INTO users (user_id, first_name, last_name, email, password_hash, profile_picture, bio, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param(
    "sssssssi",
    $user_id, $first_name, $last_name, $email, $hashed_password,
    $profile_picture, $bio, $created_at
);

if ($stmt->execute()) {
    $_SESSION['user_id'] = $user_id;
    echo json_encode([
        'success' => true,
        'user_id' => $user_id
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Registration failed']);
}
?>