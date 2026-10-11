<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../lib/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../lib/PHPMailer/SMTP.php';
require_once __DIR__ . '/../lib/PHPMailer/Exception.php';

// Credentials are deployment-owned and must never be committed.
$noticeMailPrivate = __DIR__ . '/mail.local.php';
$noticeMailConfig = is_file($noticeMailPrivate) ? require $noticeMailPrivate : [];
if (!is_array($noticeMailConfig)) $noticeMailConfig = [];
foreach (['SMTP_HOST','SMTP_PORT','SMTP_USER','SMTP_PASS','SMTP_SECURE','MAIL_FROM','MAIL_FROM_NAME'] as $noticeMailField) {
    if (!defined($noticeMailField)) define($noticeMailField, $noticeMailConfig[$noticeMailField] ?? (getenv($noticeMailField) ?: ''));
}
define('NOTICES_MAIL_ENABLED', ($noticeMailConfig['enabled'] ?? true) === true);

function send_mail($toEmails, string $subject, string $htmlBody, array $attachments = []): array {
    if (!NOTICES_MAIL_ENABLED || SMTP_HOST === '' || SMTP_USER === '' || SMTP_PASS === '' || MAIL_FROM === '') return ['ok' => false, 'error' => 'Email is not configured for this instance.'];
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = SMTP_PORT;

        // Enable debug output
        $mail->SMTPDebug = 0;                  // 0 = off, 1 = client, 2 = full
        $mail->Debugoutput = 'html';           // Outputs to HTML

        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);

        $toEmails = is_array($toEmails) ? $toEmails : explode(',', $toEmails);
        foreach ($toEmails as $addr) {
            $addr = trim($addr);
            if ($addr !== '') {
                $mail->addAddress($addr);
            }
        }

        $mail->isHTML(true);
		$mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);

        foreach ($attachments as $att) {
            if (!empty($att['path']) && is_readable($att['path'])) {
                $mail->addAttachment($att['path'], $att['name'] ?? basename($att['path']));
            }
        }

        $mail->send();
        return ['ok' => true, 'error' => null];

    } catch (Exception $e) {
        return ['ok' => false, 'error' => $mail->ErrorInfo ?: $e->getMessage()];
    }
}

