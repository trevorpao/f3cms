<?php

// This file contains utility functions for the application.
// It includes methods for database access, language detection, and security handling.

/**
 * Returns the Base instance of the Fat-Free Framework.
 *
 * @return object The Base instance.
 */
function f3()
{
    return Base::instance();
}

/**
 * Retrieves the database instance.
 *
 * @param bool $force Whether to force a new instance (default: false).
 * @return object The database instance.
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
 * Retrieves the Fat-Free Web instance for client-side operations.
 *
 * @return object The Web instance.
 */
function cs()
{
    return Web::instance();
}

/**
 * Retrieves the template prefix.
 *
 * @return string The template prefix.
 */
function tpf()
{
    return f3()->get('tpf');
}

/**
 * Stores a variable in the Fat-Free Framework's hive.
 *
 * @param string $key    The key to store the variable under.
 * @param mixed  $object The variable to store.
 */
function _dzv($key, $object)
{
    f3()->set('_dzv.' . $key, $object);
}

/**
 * Switches between two strings based on the current language.
 *
 * @param string $default The default string.
 * @param string $other   The alternative string.
 * @return string The selected string based on the current language.
 */
function langTPS($default, $other)
{
    return (F3CMS\Module::_lang() == f3()->get('defaultLang')) ? $default : $other;
}

/**
 * Checks if the current connection is HTTPS.
 *
 * @return bool True if the connection is HTTPS, false otherwise.
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
 * Returns if the given ip is on the given whitelist.
 * https://stackoverflow.com/a/51524420
 *
 * $whitelist = [
 *     '111.111.111.111',
 *     '112.112.112.112',
 *     '68.71.44.*',
 *     // '*' would allow any ip btw
 * ];
 *
 * @param string $ip        the ip to check
 * @param array  $whitelist The ip whitelist. An array of strings.
 *
 * @return bool True if the IP is allowed, false otherwise.
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
 * Detects the browser's preferred language.
 *
 * @param string $default The default language (default: 'en').
 * @return string The detected language.
 */
function detectBrowserLang($default = 'en')
{
    $rtn = $default;

    switch (strtolower(substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2))) {
        case 'zh':
        case 'cn':
        case 'tw':
        case 'hk':
            $rtn = 'tw';
            break;
    }

    return $rtn;
}

/**
 * Sets Cross-Origin Resource Sharing (CORS) headers.
 *
 * @param array $allowedOrigins The list of allowed origins.
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
 * Logs runtime trace information for debugging purposes.
 *
 * @param string $flag  A flag to identify the trace.
 * @param int    $debug The debug level (default: 0).
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
 * Decodes a Unicode string.
 *
 * @param string $str The Unicode string to decode.
 * @return string The decoded string.
 */
function decodeUnicode($str)
{
    // Use an anonymous function to replace Unicode escape sequences with their UTF-8 equivalents.
    return preg_replace_callback('/\\u([0-9a-f]{4})/i', function ($matches) {
        return mb_convert_encoding(pack('H*', $matches[1]), 'UTF-8', 'UCS-2BE');
    }, $str);
}

// REMOVE ascii in XML
/**
 * Removes ASCII characters from a string.
 *
 * @param string $string The input string.
 * @return string The string without ASCII characters.
 */
function removeASCII($string)
{
    // https://stackoverflow.com/a/1176923
    $ascii = '/[\x00-\x1F\x7F\xA0]/u';

    return preg_replace($ascii, '', $string);
}

// REMOVE chinese char
/**
 * Removes Chinese characters from a string.
 *
 * @param string $string The input string.
 * @return string The string without Chinese characters.
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
 * Validates a registration ID.
 *
 * @param string $str  The registration ID to validate.
 * @param string $mode The validation mode (default: 'strict').
 * @return bool True if the ID is valid, false otherwise.
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
 * Checks if the current user has the required authority.
 *
 * @param string $authority The required authority.
 * @return bool True if the user has the authority, false otherwise.
 */
function chkAuth($authority = '')
{
    F3CMS\kStaff::_chkLogin();

    if ('' != $authority && !canDo($authority)) {
        F3CMS\Reaction::_return(8009);
    }
}

/**
 * for human json encode
 */
function hJsonEncode($obj)
{
    return htmlentities(jsonEncode($obj));
}

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
 * Encodes an object to JSON format.
 *
 * @param mixed $obj    The object to encode.
 * @param bool  $pretty Whether to format the JSON output (default: false).
 * @return string The JSON-encoded string.
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
 * Decodes a JSON string.
 *
 * @param string $str The JSON string to decode.
 * @return mixed The decoded object or array.
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
 * Generates a secure random string.
 *
 * @param int    $length     The length of the string.
 * @param string $characters The characters to use (default: alphanumeric).
 * @return string The generated random string.
 */
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
 * Generates a UUID.
 *
 * @return string The generated UUID.
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
