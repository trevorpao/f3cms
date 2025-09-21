<?php

namespace F3CMS;

class rWebhook extends Reaction
{
    /**
     * @param $f3
     * @param $args
     */
    public function do_heartbeat($f3, $args)
    {
        $req = self::_getReq();

        if (empty($_SERVER['HTTP_AUTHORIZATION'])) {
            return self::_return(8004);
        }

        // if the Authorization is not equal to the secret key, then it is invalid
        $authorization = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
        if ($authorization !== f3()->get('webhook.secret')) {
            return self::_return(8201, ['msg' => 'Bearer authorization Failed']);
        }

        return self::_return(1, ['service_mail' => fOption::get('service_mail')]);
    }

    /**
     * @param $f3
     * @param $args
     */
    public function do_getCode($f3, $args)
    {
        $req = self::_req();

        $logger = new \Log('wh_get_code.log');
        $logger->write(jsonEncode($req));

        $msg = Validation::msg($req, kWebhook::rule('getCode'));

        if ('' == $msg) {
            $msg = 'Success!';
        }

        if (is_array($msg)) {
            $msg = implode(', ', $msg);
        }

        $cwm = new CWMhelper();

        $json = $cwm->_decrypt($req['payload']);

        $rows = jsonDecode($json);

        if (!is_array($rows)) {
            $logger->write($rows); // not json
            f3()->status(400);
        }

        $rtn = \__::pluck($rows, 'invNum');

        fWebhook::insert('CMoney', 'getCode',
            jsonEncode($rows),
            jsonEncode(['message' => $msg])
        );

        if ('Success!' != $msg) {
            f3()->status(404);
        }

        $data = kInvoice::formatSum($rows);

        if (!empty($data)) {
            fInvoice::autoInsert($data);
        }

        self::_rtn([
            'code'    => 200,
            'msg'     => 'success',
            'payload' => $cwm->_encrypt($rtn),
        ]);
    }

    /**
     * handle angular post data
     *
     * @return array - post data
     */
    public static function _req()
    {
        $rtn = [];

        $str = f3()->get('BODY');
        if (empty($str)) {
            $str = file_get_contents('php://input');
        }

        $rtn = json_decode($str, true);
        if (!(JSON_ERROR_NONE == json_last_error())) {
            parse_str($str, $rtn);
        }

        if (empty($rtn)) {
            $rtn = f3()->get('REQUEST');
        }

        return $rtn;
    }

    /**
     * new return mode
     *
     * @param mixed $code - whether sucess or error code
     * @param array $data - the data need to return
     *
     * @return array
     */
    private static function _rtn($return = ['message' => 'Success!'])
    {
        header('Content-Type: application/json; charset=utf-8');
        exit(json_encode($return));
    }
}
