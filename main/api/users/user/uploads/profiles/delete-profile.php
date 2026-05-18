<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/ban-check.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Not logged in"]);
    exit;
}

$user_id = $_SESSION['user_id'];

if (isUserBanned($user_id, $conn)) {
    http_response_code(403);
    echo json_encode(["error" => "You are banned"]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$first_name = trim($data['first_name'] ?? '');
$last_name = trim($data['last_name'] ?? '');
$bio = trim($data['bio'] ?? '');
$college = $data['college'] ?? '';

$valid_colleges = [
    'College of Computer Studies',
    'College of Arts and Sciences',
    'College of Business Administration and Accountancy',
    'College of Engineering',
    'College of Criminal Justice Education',
    'College of Teacher Education',
    'College of Industrial Technology',
    'College of International Hospitality and Tourism Management'
];

if (empty($first_name) || empty($last_name)) {
    http_response_code(400);
    echo json_encode(["error" => "First and last name required"]);
    exit;
}

if ($college && !in_array($college, $valid_colleges)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid college"]);
    exit;
}

$stmt = $conn->prepare("
    UPDATE users 
    SET first_name = ?, last_name = ?, bio = ?, college = ? 
    WHERE user_id = ?
");
$stmt->bind_param("sssss", $first_name, $last_name, $bio, $college, $user_id);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Profile updated"]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Update failed"]);
}
?>