<?php

namespace F3CMS;

use Mailgun\Mailgun;

class Sender extends Helper
{
    public const MAILGUN_KEY    = 'key-';
    public const MAILGUN_DOMAIN = 'domain.com';

    /**
     * adapter for SMTP
     *
     * @param string $subject
     * @param string $content
     * @param email  $receiver
     *
     * @return string done or error message
     */
    public static function sendmail($subject, $content, $receiver = '')
    {
        $to_address = ('' == $receiver) ? f3()->get('webmaster') : $receiver;

        $smtp = new \SMTP(f3()->get('smtp_host'), f3()->get('smtp_port'), 'SSL', f3()->get('smtp_account'), f3()->get('smtp_password'));

        $subject   = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $from_name = '=?UTF-8?B?' . base64_encode(f3()->get('smtp_name')) . '?=';

        $smtp->set('From', '"' . $from_name . '" <' . f3()->get('smtp_account') . '>');
        $smtp->set('To', '<' . $to_address . '>');
        $smtp->set('Subject', $subject);
        $smtp->set('Errors-to', '<' . f3()->get('smtp_account') . '>');
        $smtp->set('bcc', '<' . f3()->get('webmaster') . '>');
        $smtp->set('Content-Type', 'text/html;  charset=UTF-8');

        $sent = $smtp->send($content, true);

        $mylog = $smtp->log();

        if ($sent) {
            return 'Done';
        } else {
            return $mylog;
        }
    }

    /**
     * mail
     *
     * @param string $subject
     * @param string $content
     * @param email  $receiver
     *
     * @return none
     */
    public static function mail($subject, $content, $receiver = '')
    {
        $to_address = ('' == $receiver) ? f3()->get('webmaster') : $receiver;

        $from_address = f3()->get('smtp_account');
        $subject      = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $from_name    = '=?UTF-8?B?' . base64_encode(f3()->get('smtp_name')) . '?=';
        $headers      = "Content-Type: text/html; charset=\"utf8\" Content-Transfer-Encoding: 8bit \r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= 'From:' . $from_address . '(' . $from_name . ")\r\n";
        if ($to_address != $from_address) {
            $headers .= 'bcc:' . f3()->get('webmaster') . "\r\n";
        }
        mail($to_address, $subject, $content, $headers);

        return 'Done';
    }

    /**
     * @param $subject
     * @param $body
     * @param $receiver
     *
     * @return mixed
     */
    public static function send($subject, $body, $receiver = '')
    {
        $to_address = ('' == $receiver) ? f3()->get('webmaster') : $receiver;
        $mgClient   = new Mailgun(self::MAILGUN_KEY);

        $result = $mgClient->sendMessage(self::MAILGUN_DOMAIN, [
            'from'    => 'Web Service<' . f3()->get('webmaster') . '>',
            'to'      => $to_address,
            'bcc'     => f3()->get('webmaster'),
            'subject' => $subject,
            'html'    => $body,
        ]);

        return $result;
    }
}
