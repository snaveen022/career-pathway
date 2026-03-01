<?php
require __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Simple mail helper
 * Returns: ['success' => bool, 'error' => string]
 */
function sendMail(string $to, string $subject, string $message): array
{
    $mail = new PHPMailer(true);

    try {
        // DEBUG: enable while testing
        // $mail->SMTPDebug  = 2;
        // $mail->Debugoutput = 'html';

        // SMTP settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = '';      // your Gmail
        $mail->Password   = '';      // your *app password* (not normal pwd)
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        // Sender
        $mail->setFrom('', 'Career Pathway');

        // Recipient
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $message;

        if ($mail->send()) {
            return ['success' => true, 'error' => ''];
        }

        return ['success' => false, 'error' => $mail->ErrorInfo];

    } catch (Exception $e) {
        return ['success' => false, 'error' => $mail->ErrorInfo ?: $e->getMessage()];
    }
}
