<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../lib/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../lib/PHPMailer/SMTP.php';
require_once __DIR__ . '/../lib/PHPMailer/Exception.php';

// SMTP Settings
define('SMTP_HOST', 'mxe97d.netcup.net');
define('SMTP_PORT', 465);
define('SMTP_USER', 'clean-up.notice@defecttracker.uk');
define('SMTP_PASS', 'Subaru5554346');
define('SMTP_SECURE', 'ssl'); // SSL for port 465

define('MAIL_FROM', 'clean-up.notice@defecttracker.uk');
define('MAIL_FROM_NAME', 'McGoff - Clean-up Notice Notification');

function send_mail($toEmails, string $subject, string $htmlBody, array $attachments = []): array {
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
