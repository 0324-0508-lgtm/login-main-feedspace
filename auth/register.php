<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/otp-generator.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $data = $_POST;

    $first_name = trim($data['first_name'] ?? '');
    $last_name  = trim($data['last_name'] ?? '');
    $email      = trim($data['email'] ?? '');
    $college    = trim($data['college'] ?? '');
    $user_id    = trim($data['student_id'] ?? '');  // Get student_id from form

    // Validate all required fields
    if (!$first_name || !$last_name || !$email || !$user_id) {
        echo json_encode([
            'success' => false,
            'message' => 'All fields are required including Student ID'
        ]);
        exit;
    }

    // Check if email already exists
    $stmt = $conn->prepare('SELECT email FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo json_encode([
            'success' => false,
            'message' => 'Email already exists'
        ]);
        exit;
    }

    // Check if user_id (student_id) already exists
    $stmt = $conn->prepare('SELECT user_id FROM users WHERE user_id = ?');
    $stmt->execute([$user_id]);
    if ($stmt->fetch()) {
        echo json_encode([
            'success' => false,
            'message' => 'Student ID already exists'
        ]);
        exit;
    }

    // Generate password hash
    $password_hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

    // Insert user
    $stmt = $conn->prepare(
        "INSERT INTO users (user_id, first_name, last_name, email, password_hash, college)
         VALUES (?, ?, ?, ?, ?, ?)"
    );

    $stmt->execute([
        $user_id,
        $first_name,
        $last_name,
        $email,
        $password_hash,
        $college
    ]);

    // Generate OTP
    $otp = generateOTP();

    // Send OTP via email
    try {
        require_once __DIR__ . '/../includes/mailer.php';
        $sent = sendOtpEmail($email, (string)$otp);
    } catch (Throwable $mailErr) {
        $sent = false;
    }

    // Save OTP
    $stmt = $conn->prepare(
        "INSERT INTO otp (user_id, otp_code, type, expires_at)
         VALUES (?, ?, 'register', DATE_ADD(NOW(), INTERVAL 3 MINUTE))"
    );
    $stmt->execute([$user_id, $otp]);

    echo json_encode([
        'success' => true,
        'message' => 'Registered successfully',
        'otp' => $otp,
        'user_id' => $user_id
    ]);

} catch (PDOException $e) {
    // Database-specific errors (like integrity constraints)
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Registration failed',
        'error' => $e->getMessage()
    ]);
}