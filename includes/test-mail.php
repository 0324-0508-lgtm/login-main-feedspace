<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {

    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;

    // YOUR EMAIL
    $mail->Username = '0324-0508@lspu.edu.ph';

    // YOUR APP PASSWORD
    $mail->Password = 'qytvmihlagltkegf';

    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;

    $mail->setFrom('yourgmail@gmail.com', 'FeedSpace');
    $mail->addAddress('yourgmail@gmail.com');

    $mail->Subject = 'Test Email';
    $mail->Body = 'PHPMailer is working!';

    $mail->send();

    echo "SUCCESS!";
    
} catch (Exception $e) {

    echo $mail->ErrorInfo;
}