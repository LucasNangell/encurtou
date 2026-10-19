<?php
/**
 * Envio de e-mail SMTP com fallback seguro e observabilidade.
 */
require_once __DIR__ . '/config_runtime.php';

$GLOBALS['SMTP_LAST_ERROR'] = '';

function smtp_set_last_error(string $error): void
{
    $GLOBALS['SMTP_LAST_ERROR'] = $error;
}

function smtp_last_error(): string
{
    return (string)($GLOBALS['SMTP_LAST_ERROR'] ?? '');
}

function loadPhpMailerClasses(): bool
{
    if (class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
        return true;
    }

    $paths = [
        __DIR__ . '/PHPMailer/Exception.php',
        __DIR__ . '/PHPMailer/PHPMailer.php',
        __DIR__ . '/PHPMailer/SMTP.php',
    ];
    $allFound = true;
    foreach ($paths as $filePath) {
        if (!file_exists($filePath)) {
            $allFound = false;
            break;
        }
    }

    if ($allFound) {
        require_once $paths[0];
        require_once $paths[1];
        require_once $paths[2];
    } else {
        $autoload = __DIR__ . '/vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
        }
    }

    return class_exists(\PHPMailer\PHPMailer\PHPMailer::class);
}

function send_native_mail_fallback(string $to, string $subject, string $htmlMessage, string $smtpUser = ''): bool
{
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $from = !empty($smtpUser) ? $smtpUser : 'suporte@encurtou.pro';
    $headers .= "From: Encurtou.pro <" . $from . ">\r\n";
    $headers .= "Reply-To: " . $from . "\r\n";
    return mail($to, $subject, $htmlMessage, $headers);
}

function send_smtp_mail($to, $subject, $htmlMessage, $altMessage = '') {
    smtp_set_last_error('');

    $smtpHost = $_SERVER['SMTP_HOST'] ?? getenv('SMTP_HOST') ?: 'localhost';
    $smtpPort = (int)($_SERVER['SMTP_PORT'] ?? getenv('SMTP_PORT') ?: 465);
    $smtpSecure = strtolower((string)($_SERVER['SMTP_SECURE'] ?? getenv('SMTP_SECURE') ?: 'ssl')); // ssl ou tls
    $smtpUser = $_SERVER['SMTP_USER'] ?? getenv('SMTP_USER') ?: '';
    $smtpPass = $_SERVER['SMTP_PASS'] ?? getenv('SMTP_PASS') ?: '';
    
    // Se não tiver usuário SMTP configurado, tenta o mail nativo do PHP (fallback).
    if (empty($smtpUser) || empty($smtpPass)) {
        $sent = send_native_mail_fallback((string)$to, (string)$subject, (string)$htmlMessage, (string)$smtpUser);
        if (!$sent) {
            smtp_set_last_error('mail_fallback_failed');
        }
        return $sent;
    }

    if (!loadPhpMailerClasses()) {
        error_log('SMTP warning: PHPMailer não encontrado no deploy. Tentando mail() fallback.');
        $sent = send_native_mail_fallback((string)$to, (string)$subject, (string)$htmlMessage, (string)$smtpUser);
        if (!$sent) {
            smtp_set_last_error('phpmailer_not_found_and_mail_fallback_failed');
        }
        return $sent;
    }

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPass;

        if ($smtpSecure === 'ssl' || $smtpPort === 465) {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;
        } else {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = ($smtpPort > 0) ? $smtpPort : 587;
        }

        $mail->setFrom($smtpUser, 'Encurtou');
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlMessage;
        $mail->AltBody = !empty($altMessage)
            ? $altMessage
            : strip_tags(str_replace(['<br>', '<br/>'], "\n", $htmlMessage));

        $mail->send();
        return true;
    } catch (\Throwable $e) {
        $msg = $e->getMessage();
        error_log("Erro SMTP/PHPMailer: {$msg}. Tentando mail() fallback.");
        $sent = send_native_mail_fallback((string)$to, (string)$subject, (string)$htmlMessage, (string)$smtpUser);
        if ($sent) {
            smtp_set_last_error('smtp_failed_but_mail_fallback_sent');
            return true;
        }
        smtp_set_last_error('smtp_send_failed_and_mail_fallback_failed');
        error_log("Erro SMTP/PHPMailer: {$msg}");
        return false;
    }
}
