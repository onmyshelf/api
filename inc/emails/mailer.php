<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer
{
    /**
     * Get SMTP parameters
     * @return array
     */
    public static function getConfig()
    {
        $config = [
            'smtp_server' => false,
            'email_from' => '',
        ];
        
        if (defined('SMTP_SERVER') && SMTP_SERVER !== '') {
            $config['smtp_server'] = true;
        }
        if (defined('EMAIL_FROM') && EMAIL_FROM !== '') {
            $config['email_from'] = EMAIL_FROM;
        }

        return $config;
    }


    /**
     * Send email
     * @param  string $dest    Email destination
     * @param  string $subject Subject
     * @param  string $message Message
     * @return void
     */
    public static function send($dest, $subject, $message)
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
        $mail->Body = $message . self::footer();

        // text-only email: preserve end of lines but deletes HTML tags
        $mail->AltBody = strip_tags(preg_replace(["<br\s*/>", "<p>", "</p>"], ["\n", "\n", "\n"], $message));

        // send email
        try {
            $mail->send();
            Logger::debug("An email was sent to $dest");
            return true;
        } catch (Exception $e) {
            Logger::error("Failed to send email to $dest:\n$e");
        }

        return false;
    }


    /**
     * Generate email footer content
     *
     * @return string
     */
    private static function footer()
    {
        $oms_url = Config::getHomeUrl();
        $footer = "<p>--<br />Your OnMyShelf application";

        if ($oms_url)
            $footer .= "<br /><a href='$oms_url'>$oms_url</a>";

        $footer .= "</p>";

        return $footer;
    }
}
