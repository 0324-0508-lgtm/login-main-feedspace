<?php
// test-db.php
$start = microtime(true);

echo "Testing database connection...\n";

// Include your database connection file (adjust path as needed)
require_once 'config/database.php'; // or wherever $conn is defined

$conn_time = microtime(true) - $start;
echo "Connection loaded in " . round($conn_time, 3) . "s\n";

// Test a simple query
$test_start = microtime(true);
$result = $conn->query("SELECT 1");
$query_time = microtime(true) - $test_start;

if ($result) {
    echo "Simple query OK in " . round($query_time, 3) . "s\n";
} else {
    echo "Query failed: " . $conn->error . "\n";
}

// Test the exact ban query
$ban_start = microtime(true);
$stmt = $conn->prepare("SELECT id FROM user_bans WHERE user_id = ? AND (expires_at > NOW() OR expires_at IS NULL) LIMIT 1");
$stmt->bind_param("s", "test_user");
$stmt->execute();
$ban_time = microtime(true) - $ban_start;

echo "Ban query OK in " . round($ban_time, 3) . "s\n";
echo "Total time: " . round(microtime(true) - $start, 3) . "s\n";
?>