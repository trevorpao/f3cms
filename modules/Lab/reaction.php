<?php

namespace F3CMS;

// use GuzzleHttp\Client;
// use GuzzleHttp\Exception\ClientException;

class rLab extends Reaction
{
    /**
     * @param $f3
     * @param $args
     */
    public function do_info($f3, $args)
    {
        echo '<meta charset="utf-8"><h2>Lab</h2> <pre>';

        if (!kStaff::_isLogin()) {
            exit;
        }

        echo 'Info-' . f3()->get('site_title') . '-' . gethostname() . ' as ' . $f3->get('APP_ENV') . PHP_EOL;
        echo date('Y/m/d H:i:s') . ' - 本機' . (($f3->get('crontabhost') != gethostname()) ? '不能' : '可') . '執行排程' . PHP_EOL;
        echo 'from: ' . f3()->IP . PHP_EOL . PHP_EOL . PHP_EOL;

        // /api/lab/info?isAllowedIP=1
        if (!empty($_GET['isAllowedIP'])) {
            self::test('isAllowedIP');
        }

        // /api/lab/info?batchDraft=1
        if (!empty($_GET['batchDraft'])) {
            self::test('batchDraft');
        }

        // /api/lab/info?batchAnswer=1
        if (!empty($_GET['batchAnswer'])) {
            self::test('batchAnswer');
        }

        if (!empty($_GET['repeat'])) {
            echo '<script type="text/javascript"> setTimeout(function () { location.reload(true); }, 3000); </script>';
        }
    }

    /**
     * @param $type
     */
    public static function test($type)
    {
        switch ($type) {
            case 'isAllowedIP':
                $whitelist = fOption::get('ip_whitelist');

                if (!empty($whitelist)) {
                    $whitelist = explode(PHP_EOL, $whitelist);
                } else {
                    $whitelist = ['*'];
                }

                if (!isAllowedIP(f3()->IP, $whitelist)) {
                    echo PHP_EOL . 'RTN_WRONGIP';
                } else {
                    echo PHP_EOL . 'RTN_SAFEIP';
                }
                break;
            case 'batchDraft':
                fDraft::cronjob(1);
                break;
            case 'batchAnswer':
                fDraft::cronAnswer(1);
                break;
            case 'getHashCost':
                echo 'Appropriate Cost Found: ' . kLab::getHashCost() . PHP_EOL; // 50 milliseconds
                break;
            default:
                // code...
                break;
        }
    }
}
