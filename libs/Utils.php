<?php

/**
 * Utils 檔案提供多種實用的全域函式，
 * 包括資料庫操作、語言檢測、IP 驗證、CORS 設定、JSON 編碼等功能。
 */

/**
 * 獲取 Fat-Free Framework 的 Base 實例。
 *
 * @return Base 實例
 */
function f3()
{
    return Base::instance();
}

/**
 * 獲取資料庫操作的實例。
 *
 * @param bool $force 是否強制重新初始化
 * @return MHelper 資料庫操作實例
 */
function mh($force = false)
{
    if (!f3()->exists('MH') || $force) {
        $mh = F3CMS\MHelper::init($force);
        f3()->set('MH', $mh);
    }

    return f3()->get('MH');
}

/**
 * 獲取 Fat-Free Framework 的 Web 實例。
 *
 * @return Web 實例
 */
function cs()
{
    return Web::instance();
}

function tpf()
{
    return f3()->get('tpf');
}

/**
 * keep output vars
 *
 * @param [type] $key    [description]
 * @param [type] $object [description]
 */
function _dzv($key, $object)
{
    f3()->set('_dzv.' . $key, $object);
}

/**
 * Two-Phase Switcher
 *
 * @param  [string] $default default string
 * @param  [string] $other   other string
 *
 * @return [string] string
 */
function langTPS($default, $other)
{
    return (F3CMS\Module::_lang() == f3()->get('defaultLang')) ? $default : $other;
}

/**
 * 獲取 CSRF 令牌。
 *
 * @return string CSRF 令牌
 */
function getCSRF()
{
    if ('database' == f3()->get('sessionBase')) {
        return f3()->get('sess')->csrf();
    } elseif ('redis' == f3()->get('sessionBase')) {
        return f3()->get('sess')->csrf();
    } else {
        return f3()->CSRF;
    }
}

/**
 * 檢查當前請求是否為 Ajax 請求。
 *
 * @return bool 是否為 Ajax 請求
 */
function isAjax()
{
    return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 'xmlhttprequest' == strtolower($_SERVER['HTTP_X_REQUESTED_WITH'])) || (!empty($_SERVER['HTTP_ACCEPT']) && false !== strpos(strtolower($_SERVER['HTTP_ACCEPT']), 'json'));
}

/**
 * 檢測是否使用 HTTPS。
 *
 * @return bool 是否為 HTTPS
 */
function is_https()
{
    if (isset($_SERVER['HTTPS']) && 'on' === strtolower($_SERVER['HTTPS'])) {
        return true;
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && 'https' === $_SERVER['HTTP_X_FORWARDED_PROTO']) {
        return true;
    } elseif (isset($_SERVER['HTTP_FRONT_END_HTTPS']) && 'on' === $_SERVER['HTTP_FRONT_END_HTTPS']) {
        return true;
    }

    return false;
}

/**
 * 驗證給定的 IP 是否在白名單中。
 * https://stackoverflow.com/a/51524420
 *
 * $whitelist = [
 *     '111.111.111.111',
 *     '112.112.112.112',
 *     '68.71.44.*',
 *     // '*' would allow any ip btw
 * ];
 *
 * @param string $ip        要檢查的 IP
 * @param array  $whitelist IP 白名單
 *
 * @return bool 是否允許
 */
function isAllowedIP($ip, array $whitelist)
{
    $ip        = (string) $ip;
    $whitelist = array_map('trim', $whitelist);

    if (in_array($ip, $whitelist)) {
        // the given ip is found directly on the whitelist --allowed
        return true;
    }
    // go through all whitelisted ips
    foreach ($whitelist as $whitelistedIp) {
        $whitelistedIp = (string) $whitelistedIp;
        // find the wild card * in whitelisted ip (f.e. find position in "127.0.*" or "127*")
        $wildcardPosition = strpos($whitelistedIp, '*');
        if (false === $wildcardPosition) {
            // no wild card in whitelisted ip --continue searching
            continue;
        }
        // cut ip at the position where we got the wild card on the whitelisted ip
        // and add the wold card to get the same pattern
        if (substr($ip, 0, $wildcardPosition) . '*' === $whitelistedIp) {
            // f.e. we got
            //  ip "127.0.0.1"
            //  whitelisted ip "127.0.*"
            // then we compared "127.0.*" with "127.0.*"
            // return success
            return true;
        }
    }

    // return false on default
    return false;
}

/**
 * 設定 CORS（跨來源資源共享）。
 *
 * @param array $allowedOrigins 允許的來源陣列
 */
function setCORS($allowedOrigins = [])
{
    $allowedOrigins = array_merge($allowedOrigins, ['http://127.0.0.1', 'http://localhost']);

    if (isset($_SERVER['HTTP_ORIGIN'])) {
        f3()->set('isCORS', 1);
        if (
            in_array($_SERVER['HTTP_ORIGIN'], $allowedOrigins)  // for localhost testing
             || 'demo.sense-info.co' == $_SERVER['SERVER_NAME'] // for demosite testing
        ) {
            f3()->copy('HEADERS.Origin', 'CORS.origin');
            // f3()->set('CORS.origin', $_SERVER['HTTP_ORIGIN']);
            f3()->set('CORS.credentials', true);
            f3()->set('CORS.headers', 'X-Requested-With, X-Requested-Token, Mobile-Token, Content-Type, Content-Range, Content-Disposition, Origin, Accept');
            f3()->set('CORS.expose', true);
            f3()->set('CORS.ttl', 86400);
        }
    } else {
        f3()->set('isCORS', 0);
    }
}

function fQuery()
{
    exit(mh()->last());
}

/**
 * @param $flag
 * @param $debug
 */
function rtTrace($flag = '', $debug = 0)
{
    if (f3()->get('DEBUG') >= $debug) {
        $logger = new Log('sql_trace.log');
        if ('' != $flag) {
            $logger->write($flag);
        }
        $logger->write(mh()->last());
    }
}

/**
 * @param $str
 */
function decodeUnicode($str)
{
    return preg_replace_callback('/\\\\u([0-9a-f]{4})/i', create_function('$matches', 'return mb_convert_encoding(pack("H*", $matches[1]), "UTF-8", "UCS-2BE");'), $str);
}

// REMOVE ascii in XML
/**
 * @param $string
 */
function removeASCII($string)
{
    // https://stackoverflow.com/a/1176923
    $ascii = '/[\x00-\x1F\x7F\xA0]/u';

    return preg_replace($ascii, '', $string);
}

// REMOVE chinese char
/**
 * @param $string
 */
function removeZH($string)
{
    $ascii = '/[\x00-\x1F\x80-\xFF]/';

    return preg_replace($ascii, '', $string);
}

function containsCJK($string)
{
    // 定義包含中、日、韓文字符的Unicode範圍的正則表達式
    $cjkPattern = '/[\x{4E00}-\x{9FFF}\x{3040}-\x{30FF}\x{AC00}-\x{D7AF}]/u';

    // 使用preg_match來檢測字串中是否含有中、日、韓文字元
    return preg_match($cjkPattern, $string);
}

/**
 * @param $str
 * @param $mode
 */
function chkRegisterID($str, $mode = 'strict')
{
    if ('strict' == $mode) {
        return preg_match('/^[A]-\d{4}-[a-z]-\d{3}$/i', $str, $output);
    } else {
        return preg_match('/^[A]-\d{4}-[a-z]-\d{3}\D{0,4}$/i', $str, $output);
    }
}

/**
 * @param $authority
 */
function canDo($authority = '')
{
    return F3CMS\fRole::hasAuth(F3CMS\fStaff::_current('priv'), $authority);
}

/**
 * @param $authority
 */
function chkAuth($authority = '')
{
    F3CMS\kStaff::_chkLogin();

    if ('' != $authority && !canDo($authority)) {
        F3CMS\Reaction::_return(8009);
    }
}

/**
 * @param $priv
 * @param $auth
 */
function hasAuth($priv = 0, $auth = '')
{
    return F3CMS\fRole::hasAuth($priv, $auth);
}

/**
 * @param       $topic
 * @param array $detail
 */
function systemAlert($topic, $detail = [])
{
    f3()->set('now', date('Y-m-d H:i:s'));
    f3()->set('here', gethostname());
    f3()->set('detail', jsonEncode($detail));

    F3CMS\Sender::sendmail(
        f3()->get('site_title') . ' ' . $topic,
        F3CMS\Sender::renderBody('system-alert'),
        f3()->get('webmaster')
    );
}

/**
 * 檢查磁碟空間使用情況，並在超過 90% 時發送警告。
 */
function chkDiskSpace()
{
    try {
        $diskTotalSpace = @disk_total_space(f3()->get('baseDisk'));
        $diskFreeSpace  = @disk_free_space(f3()->get('baseDisk'));

        $canUseSpace = round(100 - ($diskFreeSpace / $diskTotalSpace) * 100, 2);

        if ($canUseSpace > 90) {
            systemAlert('空間用量警示', [
                'diskTotalSpace' => $diskTotalSpace,
                'canUseSpace'    => $canUseSpace,
            ]);
        }
    } catch (Exception $e) {
        return;
    }
}

/**
 * for human json encode
 */
function hJsonEncode($obj)
{
    return htmlentities(jsonEncode($obj));
}

/**
 * for human json encode
 */
function jsonEncode($obj, $pretty = false)
{
    if ($pretty) {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;
    } else {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    }

    return json_encode($obj, $flags);
}

/**
 * @param $str
 *
 * @return mixed
 */
function jsonDecode($str)
{
    $rtn = json_decode($str, true);

    switch (json_last_error()) {
        case JSON_ERROR_NONE:
            break;
        case JSON_ERROR_DEPTH:
            $rtn = 'Maximum stack depth exceeded';
            break;
        case JSON_ERROR_STATE_MISMATCH:
            $rtn = 'Underflow or the modes mismatch';
            break;
        case JSON_ERROR_CTRL_CHAR:
            $rtn = 'Unexpected control character found';
            break;
        case JSON_ERROR_SYNTAX:
            $rtn = 'Syntax error, malformed JSON';
            break;
        case JSON_ERROR_UTF8:
            $rtn = 'Malformed UTF-8 characters, possibly incorrectly encoded';
            break;
        default:
            $rtn = 'Unknown error';
            break;
    }

    return $rtn;
}

/**
 * @param $idx
 * @param $val
 */
function safeCookie($idx, $val = '')
{
    setCookieV2($idx, $val);
}

/**
 * @param $idx
 * @param $val
 */
function unsafeCookie($idx, $val = '')
{
    setCookieV2($idx, $val, false);
}

/**
 * @return mixed
 */
function getCaller()
{
    $trace  = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    $caller = $trace[1]['class']; // 1 or 2
    $caller = str_replace(['F3CMS\\', '\\'], ['', ''], $caller);
    $caller = preg_split('/(?<=[fork])(?=[A-Z])/', $caller);
    $caller = F3CMS_Autoloader::getType()[$caller[0]];

    return $caller;
}

function safeCount($ary)
{
    return (!is_countable($ary)) ? 0 : count($ary);
}

/**
 * @param $idx
 * @param $val
 */
function setCookieV2($idx, $val = '', $httponly = true)
{
    $opt = [
        'Expires'  => time() + (86400 * f3()->get('token_expired')),
        'Secure'   => true,      // or false
        'Httponly' => $httponly, // or false
        'Path'     => '/',
        'Domain'   => (('loc.f3cms.com:4433' !== $_SERVER['HTTP_HOST']) ? f3()->get('JAR.domain') : 'loc.f3cms.com'), // leading dot for compatibility or use subdomain
        'Samesite' => 'Lax',                                                                                          // None || Lax || Strict
    ];

    if (empty($val)) {
        $val = '';
    }

    try {
        setcookie($idx, $val, $opt['Expires'], $opt['Path'], $opt['Domain'], $opt['Secure'], $opt['Httponly']);
        f3()->set('SESSION.' . $idx, $val);
    } catch (Exception $e) {
        exit('too late for cookie.');
    }
}

/**
 * @param $idx
 */
function getCookie($idx)
{
    $idx = str_replace('\'', '"', $idx);

    if (f3()->exists('COOKIE.' . $idx)) {
        return f3()->get('COOKIE.' . $idx);
    } elseif (f3()->exists('SESSION.' . $idx)) {
        return f3()->get('SESSION.' . $idx);
    } else {
        return null;
    }
}

function secure_random_string($length, $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ')
{
    $charactersLength = strlen($characters);
    $randomString     = '';
    for ($i = 0; $i < $length; ++$i) {
        $randomIndex = random_int(0, $charactersLength - 1);
        $randomString .= $characters[$randomIndex];
    }

    return $randomString;
}

/**
 * @return mixed
 */
function uuid()
{
    $chars = secure_random_string(40, '0123456789abcdefghijklmnopqrstuvwxyz');

    return substr($chars, 0, 8) . '-'
    . substr($chars, 8, 4) . '-'
    . substr($chars, 12, 4) . '-'
    . substr($chars, 16, 4) . '-'
    . substr($chars, 20, 12);
}
