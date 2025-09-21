<?php

namespace F3CMS;

use Mailgun\Mailgun;

class Sender extends Helper
{
    public const MAILGUN_KEY    = 'key-';
    public const MAILGUN_DOMAIN = 'domain.com';

    // TODO: add bcc mode

    /**
     * @param $subject
     * @param $content
     * @param $receiver
     * @param $service
     *
     * @return string done
     */
    public static function mail($subject, $content, $receiver = '', $service = 'smtp')
    {
        $method = 'by' . ucfirst($service);
        $rtn    = null;

        if (method_exists(__CLASS__, $method)) {
            if (strlen($content) < 20) { // TODO: add file check
                $content = self::renderBody($content);
            }
            $rtn = call_user_func_array([Sender::class, $method], [$subject, $content, $receiver]);
        }

        return $rtn;
    }

    /**
     * Alias of mail
     *
     * @param $subject
     * @param $content
     * @param $receiver
     * @param $service
     *
     * @return string done
     */
    public static function sendmail($subject, $content, $receiver = '', $service = 'smtp')
    {
        return self::mail($subject, $content, $receiver, $service);
    }

    /**
     * adapter for SMTP
     *
     * @param string $subject
     * @param string $content
     * @param email  $receiver
     *
     * @return string done or error message
     */
    public static function bySmtp($subject, $content, $receiver = '')
    {
        $to_address = ('' == $receiver) ? f3()->get('webmaster') : $receiver;

        $smtp = new \SMTP(f3()->get('smtp_host'), f3()->get('smtp_port'), 'SSL', f3()->get('smtp_account'), f3()->get('smtp_password'));

        $subject   = self::encode($subject);
        $from_name = self::encode(f3()->get('smtp_name'));

        $smtp->set('From', '"' . $from_name . '" <' . f3()->get('smtp_from') . '>');

        $toAry = (false === strpos($to_address, ';')) ? explode(',', $to_address) : explode(';', $to_address);
        $cc    = '';
        foreach ($toAry as $idx => $val) {
            if (0 == $idx) {
                $smtp->set('To', '<' . trim($val) . '>');
            } else {
                $cc .= '<' . trim($val) . '>,';
            }
        }

        if (1 == count($toAry)) {
            if ($to_address != f3()->get('webmaster')) {
                $smtp->set('Bcc', '<' . f3()->get('webmaster') . '>');
            }
        } else {
            $smtp->set('Cc', $cc);
        }

        $smtp->set('Content-Type', 'text/html;  charset=UTF-8');
        $smtp->set('Subject', $subject);
        $smtp->set('Errors-to', '<' . f3()->get('smtp_from') . '>');

        $sent = $smtp->send($content, true);

        self::log($smtp->log());

        return 'Done';
    }

    /**
     * by php mail
     *
     * @param string $subject
     * @param string $content
     * @param email  $receiver
     *
     * @return none
     */
    public static function byMail($subject, $content, $receiver = '')
    {
        $to_address   = ('' == $receiver) ? f3()->get('webmaster') : $receiver;
        $to_address   = str_replace(';', ',', $to_address);

        $from_address = f3()->get('smtp_from');
        $subject      = self::encode($subject);
        $from_name    = self::encode(f3()->get('smtp_name'));

        $headers = 'Content-Type: text/html; charset="utf8" Content-Transfer-Encoding: 8bit ' . PHP_EOL;
        $headers .= 'MIME-Version: 1.0' . PHP_EOL;
        $headers .= 'From:' . $from_address . '(' . $from_name . ')' . PHP_EOL;

        if ($to_address != $from_address) {
            $headers .= 'bcc:' . f3()->get('webmaster') . PHP_EOL;
        }
        mail($to_address, $subject, $content, $headers);

        return 'Done';
    }

    /**
     * byMailgun
     *
     * @param string $subject
     * @param string $content
     * @param email  $receiver
     *
     * @return none
     */
    public static function byMailgun($subject, $content, $receiver = '')
    {
        $to_address = ('' == $receiver) ? f3()->get('webmaster') : $receiver;
        $opts       = [
            'from'    => f3()->get('smtp_name') . '<' . f3()->get('webmaster') . '>',
            'to'      => $to_address,
            'subject' => $subject,
            'html'    => $content,
        ];

        if ($to_address != $from_address) {
            $opts['bcc'] = f3()->get('webmaster');
        }

        $mgClient = new Mailgun(self::MAILGUN_KEY);
        $result   = $mgClient->sendMessage(self::MAILGUN_DOMAIN, $opts);

        self::log($result);

        return 'Done';
    }

    /**
     * @param $tmplname
     *
     * @return mixed
     */
    public static function renderBody($tmplname)
    {
        $tp = Outfit::_origin();

        return $tp->render('mail/' . $tmplname . '.html');
    }

    /**
     * @param $txt
     */
    private static function log($txt)
    {
        $logger = new \Log('smtp.log');
        $logger->write($txt);
    }

    /**
     * @param $str
     */
    private static function encode($str)
    {
        return '=?UTF-8?B?' . base64_encode($str) . '?=';
    }
}
