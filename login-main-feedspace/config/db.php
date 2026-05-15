<?php
// ============================================================
//  config/db.php — Dual database connection for FeedSpace
//  Provides:
//   $pdo  — PDO connection (used by auth/ files)
//   $conn — mysqli connection (used by main/api/ files)
// ============================================================

$host   = 'localhost';
$dbname = 'db_feedspace';
$user   = 'root';
$password = '';

// ── PDO connection (auth files) ──────────────────────────────
try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $user,
        $password
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'error' => 'Database connection failed: ' . $e->getMessage()
    ]);
    exit();
}

// ── MySQLi connection (main/api files) ───────────────────────
$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'error' => 'Database connection failed: ' . $conn->connect_error
    ]);
    exit();
}

$conn->set_charset('utf8mb4');
