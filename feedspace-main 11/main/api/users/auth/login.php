<?php
//login user
// This file processes the login form submission. It checks the provided user_id and password against the database, and if valid, it sets session variables to keep the user logged in.
session_start();
include '../../config/db.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$user_id = trim($_POST['user_id'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($user_id) || empty($password)) {
    http_response_code(400);
    echo json_encode(['error' => 'User ID and password required']);
    exit();
}

//search by user_id (string)
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("s", $user_id);
$stmt->execute();
// Get result and fetch user data
$result = $stmt->get_result();
$user = $result->fetch_assoc();
// If user not found, return error
if (!$user) {
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
    exit();
}
// Verify password
if (password_verify($password, $user['password_hash'])) {
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['first_name'] = $user['first_name'];
    $_SESSION['last_name'] = $user['last_name'];

    echo json_encode(['success' => true, 'message' => 'Login successful!']);
} else {
    http_response_code(401);
    echo json_encode(['error' => 'Password incorrect']);
}
?>