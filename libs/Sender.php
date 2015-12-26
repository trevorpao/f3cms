<?php
namespace F3CMS;

class Sender extends Helper
{

    /**
     * adapter for SMTP
     *
     * @param string $subject
     * @param string $content
     * @param email  $receiver
     *
     * @return string done or error message
     */
    static function sendmail($subject, $content, $receiver)
    {
        $f3 = f3();

        $now = date('Y-m-d H:i:s');

        $smtp = new \SMTP($f3->get('smtp_host'), $f3->get('smtp_port'), 'SSL', $f3->get('smtp_account'), $f3->get('smtp_password'));

        $smtp->set('From', '"' . $f3->get('smtp_name') . '" <' . $f3->get('smtp_account') . '>');
        $smtp->set('To', '<' . $receiver . '>');
        $smtp->set('Subject', $subject);
        $smtp->set('Errors-to', '<' . $f3->get('smtp_account') . '>');
        $smtp->set('Content-Type', 'text/html');

        $sent = $smtp->send($content, TRUE);

        $mylog = $smtp->log();

        if ($sent) {
            return 'Done';
        }
        else {
            return $mylog;
        }
    }

    /**
     * mail
     *
     * @param string $subject - subject
     * @param string $message - message
     * @param string $email   - email
     *
     * @return none
     */
    static function mail($subject, $message, $email = "")
    {
        $f3 = f3();

        $to_address = ($email == "") ? $f3->get('inquiry_receiver') : $email;

        $from_address = $f3->get('smtp_account');
        $subject = "=?UTF-8?B?" . base64_encode($subject) . "?=";
        $from_name = "=?UTF-8?B?" . base64_encode($f3->get('smtp_name')) . "?=";
        $headers = "Content-Type: text/html; charset=\"utf8\" Content-Transfer-Encoding: 8bit \r\n";
        $headers.= "MIME-Version: 1.0\r\n";
        $headers.= "From:" . $from_address . "(" . $from_name . ")\r\n";
        if ($to_address != $from_address) {
            $headers.= "bcc:" . $f3->get('inquiry_receiver') . "\r\n";
        }
        mail($to_address, $subject, $message, $headers);
        return 'Done';
    }
}
