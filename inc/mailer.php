<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer
{
    /**
     * Sent email
     * @param  string $message Message
     * @param  string $prefix  Prefix (inserted in start line)
     * @param  string $context Context (append at the end)
     * @return void
     */
    public static function send($dest, $message, $subject='')
    {
        require_once 'PHPMailer/src/Exception.php';
        require_once 'PHPMailer/src/PHPMailer.php';
        require_once 'PHPMailer/src/SMTP.php';

        $mail = new PHPMailer(true);
        
        // SMTP server
        if (defined('SMTP_SERVER') && SMTP_SERVER !== '') {
            $mail->isSMTP();
            $mail->Host = SMTP_SERVER;
        }

        // SMTP port
        if (!defined('SMTP_PORT')) {
            define('SMTP_PORT', 465);
        }
        $mail->Port = SMTP_PORT;
        
        // SMTP security
        switch (SMTP_PORT) {
          case 465:
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            break;
          
          default:
            break;
        }

        // SMTP authentication
        if (defined('SMTP_USER') && SMTP_USER !== '') {
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER;
        }
        if (defined('SMTP_PASSWORD') && SMTP_PASSWORD !== '') {
            $mail->Password = SMTP_PASSWORD;
        }

        // email from address
        if (!defined('EMAIL_FROM')) {
            define('EMAIL_FROM', 'noreply@onmyshelf.app');
        }
        $mail->setFrom(EMAIL_FROM, 'OnMyShelf');

        // email destination
        $mail->addAddress($dest);

        // email content
        $mail->isHTML(true);
        $mail->Subject = "[OnMyShelf] $subject";
        $mail->Body = $message;
        $mail->AltBody = strip_tags($message);

        try {
            $mail->send();
            Logger::debug("Email sent to $dest");
            return true;
        } catch (Exception $e) {
            Logger::error("Failed to send email to $dest:\n$e");
        }

        return false;
    }
}
