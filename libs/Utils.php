<?php

function f3()
{
    return \Base::instance();
}

/**
 * get db instance
 *
 * @return db obj
 */
function mh()
{
    if (!f3()->exists('MH')) {
        $mh = \F3CMS\MHelper::init();
        f3()->set('MH', $mh);
    }

    return f3()->get('MH');
}

function tpf()
{
    return f3()->get('tpf');
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
    return (\F3CMS\Module::_lang() == f3()->get('defaultLang')) ? $default : $other;
}

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
 * @param $default
 *
 * @return mixed
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
 * @param array $allowedOrigins
 */
function setCORS($allowedOrigins = [])
{
    $allowedOrigins = array_merge($allowedOrigins, ['http://127.0.0.1', 'http://localhost']);

    if (isset($_SERVER['HTTP_ORIGIN'])) {
        f3()->set('isCORS', 1);
        if (
            in_array($_SERVER['HTTP_ORIGIN'], $allowedOrigins) // for localhost testing
             || 'demo.sense-info.co' == $_SERVER['SERVER_NAME'] // for demosite testing
        ) {
            f3()->set('CORS.origin', $_SERVER['HTTP_ORIGIN']);
            f3()->set('CORS.credentials', true);
            f3()->set('CORS.headers', 'X-Requested-With, X-Requested-Token, Content-Type, Origin, Accept');
            // f3()->set('CORS.expose', 'true');
            f3()->set('CORS.ttl', '86400');
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
 * @param $str
 */
function decodeUnicode($str)
{
    return preg_replace_callback('/\\\\u([0-9a-f]{4})/i', create_function('$matches', 'return mb_convert_encoding(pack("H*", $matches[1]), "UTF-8", "UCS-2BE");'), $str);
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
