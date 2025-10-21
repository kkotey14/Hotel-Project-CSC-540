<?php
// lib_mail.php — Mailtrap SMTP via PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/vendor/autoload.php';

function send_mail(string $to, string $subject, string $html): bool
{
    $mail = new PHPMailer(true);

    try {
        // Mailtrap SMTP
        $mail->isSMTP();
        $mail->Host       = 'sandbox.smtp.mailtrap.io';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'be4c62483da3dd';   // your Mailtrap username
        $mail->Password   = 'f77fe1e12b6937';   // your Mailtrap password
        $mail->Port       = 2525;               // safest choice
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->CharSet    = 'UTF-8';

        // Sender
        $mail->setFrom('no-reply@yourhotel.test', 'The Riverside Reservations');

        // Recipient (Mailtrap will catch everything in the inbox)
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        $mail->AltBody = strip_tags(
            preg_replace('/<br\s*\/?>/i', "\n", preg_replace('/<\/p>/i', "\n\n", $html))
        );

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}