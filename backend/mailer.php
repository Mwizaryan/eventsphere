<?php
/**
 * backend/mailer.php
 * Handles email notifications using PHPMailer (non-Composer).
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

// ─── SMTP CONFIGURATION ───────────────────────────────────────
// Update these with your actual SMTP credentials (e.g., Gmail, SendGrid, etc.)
define('MAIL_HOST',       'smtp.example.com');
define('MAIL_PORT',       587);
define('MAIL_USERNAME',   'your-email@example.com');
define('MAIL_PASSWORD',   'your-password');
define('MAIL_FROM_EMAIL', 'noreply@eventsphere.com');
define('MAIL_FROM_NAME',  'EventSphere');

/**
 * Sends an email via PHPMailer SMTP with STARTTLS.
 */
function sendBookingEmail(string $to_name, string $to_email, string $subject, string $html_body): bool {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = 'UTF-8';

        // Recipients
        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($to_email, $to_name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html_body;
        $mail->AltBody = strip_tags($html_body);

        return $mail->send();
    } catch (Exception $e) {
        error_log("PHPMailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Returns a fully styled HTML email template matching the EventSphere dark theme.
 */
function emailTemplate(string $heading, string $content_html): string {
    return "
    <!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: 'Outfit', -apple-system, BlinkMacSystemFont, sans-serif; background-color: #0d0d14; color: #a0a0b8; margin: 0; padding: 40px 20px; }
            .container { max-width: 600px; margin: 0 auto; background-color: #14141f; border-radius: 16px; overflow: hidden; border: 1px solid rgba(255,255,255,0.08); box-shadow: 0 8px 24px rgba(0,0,0,0.4); }
            .header { background: linear-gradient(135deg, #7c6af7 0%, #c26ef7 100%); padding: 40px 30px; text-align: center; }
            .header h1 { color: #ffffff; margin: 0; font-size: 24px; font-weight: 800; letter-spacing: -0.02em; }
            .content { padding: 40px 30px; line-height: 1.6; }
            .content h2 { color: #ffffff; font-size: 18px; margin-top: 0; }
            .content p { margin-bottom: 20px; }
            .footer { padding: 30px; text-align: center; border-top: 1px solid rgba(255,255,255,0.08); font-size: 13px; color: #8b8ba8; }
            .btn { display: inline-block; background: #7c6af7; color: #ffffff !important; padding: 12px 24px; border-radius: 999px; text-decoration: none; font-weight: 600; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>EventSphere</h1>
            </div>
            <div class='content'>
                <h2>{$heading}</h2>
                {$content_html}
            </div>
            <div class='footer'>
                &copy; " . date('Y') . " EventSphere. All rights reserved.<br>
                Premium Event Resort & Booking Services
            </div>
        </div>
    </body>
    </html>
    ";
}
