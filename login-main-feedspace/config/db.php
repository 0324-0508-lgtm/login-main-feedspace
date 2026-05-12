<?php
$host = 'localhost';
$user = 'root';
$password = '';
$database = 'db_feedspace';

$conn = mysqli_connect($host, $user, $password, $database);
if (!$conn) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Database connection failed: ' . mysqli_connect_error()]);
    exit();
}
mysqli_set_charset($conn, 'utf8mb4');
