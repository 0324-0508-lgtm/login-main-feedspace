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
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = function ($str, $level) {
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

        $mail->isHTML(true);
$mail->Subject = 'Your FeedSpace OTP Verification Code';

$mail->Body = "
<!DOCTYPE html>
<html>
<head>
  <meta charset='UTF-8'>
  <style>
    body {
      margin: 0;
      padding: 0;
      background-color: #f4f7fb;
      font-family: Arial, sans-serif;
    }

    .container {
      max-width: 600px;
      margin: 40px auto;
      background: #ffffff;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }

    .header {
      background: linear-gradient(135deg, #0051e8, #4dabf8);
      color: white;
      text-align: center;
      padding: 30px 20px;
    }

    .header h1 {
      margin: 0;
      font-size: 28px;
    }

    .content {
      padding: 40px 30px;
      color: #333;
      text-align: center;
    }

    .otp-box {
      display: inline-block;
      margin: 25px 0;
      padding: 18px 35px;
      background: #f3f4f6;
      border-radius: 10px;
      font-size: 36px;
      font-weight: bold;
      letter-spacing: 8px;
      color: #1e38b9;
    }

    .message {
      font-size: 16px;
      line-height: 1.6;
      color: #555;
    }

    .warning {
      margin-top: 20px;
      color: #dc2626;
      font-size: 14px;
    }

    .footer {
      background: #f9fafb;
      text-align: center;
      padding: 20px;
      font-size: 13px;
      color: #888;
    }
  </style>
</head>

<body>
  <div class='container'>

    <div class='header'>
      <h1>FeedSpace</h1>
    </div>

    <div class='content'>
      <h2>Email Verification</h2>

      <p class='message'>
        Use the verification code below to continue your login or registration.
      </p>

      <div class='otp-box'>{$otpCode}</div>

      <p class='message'>
        This OTP code will expire in <strong>3 minutes</strong>.
      </p>

      <p class='warning'>
        Do not share this code with anyone.
      </p>
    </div>

    <div class='footer'>
      © " . date('Y') . " FeedSpace. All rights reserved.
    </div>

  </div>
</body>
</html>
";

$mail->AltBody = "Your FeedSpace OTP Code is: {$otpCode}. This code expires in 3 minutes.";

        return $mail->send();
    } catch (\Throwable $e) {
        error_log('sendOtpEmail failed: ' . $e->getMessage());
        return false;
    }
}

