<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
} else {
    error_log('Composer autoload missing. Run: composer install');
    return false; // or throw
}

// If Composer autoload isn't present, fail gracefully.


function sendOtpEmail(string $toEmail, string $otpCode): bool
{
    $mailConfig = require __DIR__ . '/../config/mail.php';

    $toEmail = trim($toEmail);

    if ($toEmail === '' || $otpCode === '') {
        return false;
    }

    try {
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        // Capture SMTP debug output into a variable so API can show it if needed
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = function($str, $level) {
            error_log("PHPMailer[$level] $str");
        };
        $mail->Host = $mailConfig['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $mailConfig['smtp_username'];
        $mail->Password = $mailConfig['smtp_password'];

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int)($mailConfig['smtp_port'] ?? 587);

        $mail->CharSet = 'UTF-8';

        $mail->setFrom(
            $mailConfig['from_email'],
            $mailConfig['from_name'] ?? 'FeedSpace'
        );

        $mail->addAddress($toEmail);

        $mail->isHTML(false);
        $mail->Subject = 'Your FeedSpace OTP Code';
        $mail->Body = "Your OTP code is: {$otpCode}\n\nThis code will expire in 10 minutes.";

        return $mail->send();

    } catch (\Throwable $e) {
        error_log('sendOtpEmail failed: ' . $e->getMessage());
        return false;
    }
}

