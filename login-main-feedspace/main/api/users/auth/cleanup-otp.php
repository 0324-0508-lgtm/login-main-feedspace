<?php
include '../../../../config/db.php';

date_default_timezone_set('Asia/Manila');

// Delete expired OTPs
$conn->query("
    DELETE FROM otp 
    WHERE expires_at < NOW()
");

// Optional: also delete used OTPs older than 10 minutes
$conn->query("
    DELETE FROM otp 
    WHERE is_used = 1 
    AND created_at < (NOW() - INTERVAL 10 MINUTE)
");

echo "OTP cleanup done";