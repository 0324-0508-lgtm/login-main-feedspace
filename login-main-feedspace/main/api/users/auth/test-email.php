<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = $_ENV['MAIL_HOST'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $_ENV['MAIL_USERNAME'];
    $mail->Password   = $_ENV['MAIL_PASSWORD'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = $_ENV['MAIL_PORT'];

    $mail->setFrom($_ENV['MAIL_USERNAME'], $_ENV['MAIL_FROM_NAME']);
    $mail->addAddress($_ENV['MAIL_USERNAME']); // sends to yourself

    $mail->isHTML(true);
    $mail->Subject = 'FeedSpace Test Email';
    $mail->Body    = '<h2>It works!</h2><p>PHPMailer is configured correctly.</p>';

    $mail->send();
    echo "Email sent successfully! Check your inbox.";

} catch (Exception $e) {
    echo "Email failed: " . $mail->ErrorInfo;
}