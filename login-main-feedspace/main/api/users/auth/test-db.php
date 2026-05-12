<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/db.php';

echo "DB connected successfully!";

// Test if otp table exists
$stmt = $pdo->query("SELECT COUNT(*) FROM otp");
echo " | OTP table found!";