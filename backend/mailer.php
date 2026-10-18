<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
function sendAppMail(string $to, string $subject, string $body): bool
{
    try {
        require_once dirname(__DIR__) . '/vendor/autoload.php';
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = (string)env('SMTP_HOST', '');
        $mail->SMTPAuth = true;
        $mail->Username = (string)env('SMTP_USERNAME', '');
        $mail->Password = (string)env('SMTP_PASSWORD', '');
        $security = env('SMTP_ENCRYPTION', 'tls');
        if (!in_array($security, ['tls', 'ssl'], true) || $mail->Host === '') {
            throw new RuntimeException('SMTP configuration is incomplete.');
        }
        $mail->SMTPSecure = $security;
        $mail->Port = (int)env('SMTP_PORT', '587');
        $mail->Timeout = 15;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom((string)env('MAIL_FROM'), (string)env('MAIL_FROM_NAME', 'Oh My Gracious'));
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body = $body;
        return $mail->send();
    } catch (Throwable $e) {
        // Do not log message bodies or tokens.
        error_log('SMTP delivery failed; check server SMTP configuration.');
        return false;
    }
}
