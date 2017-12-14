<?php

function f3() {
    return \Base::instance();
}

function db() {
    if (!f3()->exists('DB')) {
        $db = new \DB\SQL(f3()->get('db'), f3()->get('db_account'), f3()->get('db_password'));
        f3()->set('DB', $db);
    }

    return f3()->get('DB');
}

function tpf() {
    return f3()->get('tpf');
}

function is_https()
{
    if (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) === 'on') {
        return true;
    } else if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        return true;
    } else if (isset($_SERVER['HTTP_FRONT_END_HTTPS']) && $_SERVER['HTTP_FRONT_END_HTTPS'] === 'on') {
        return true;
    }
    return false;
}



function detectBrowserLang($default = 'en')
{
    $rtn = $default;

    switch (strtolower(substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2))){
        case 'zh':
        case 'cn':
        case 'tw':
        case 'hk':
            $rtn = 'tw';
            break;
    }

    return $rtn;
}

function setCORS($allowedOrigins = array())
{
    $allowedOrigins = array_merge($allowedOrigins, array('http://127.0.0.1', 'http://localhost'));

    if (isset($_SERVER['HTTP_ORIGIN'])) {
        f3()->set('isCORS', 1);
        if (
            in_array($_SERVER['HTTP_ORIGIN'], $allowedOrigins) // for localhost testing
            || $_SERVER['SERVER_NAME'] == 'demo.sense-info.co' // for demosite testing
        ) {
            f3()->set('CORS.origin', $_SERVER['HTTP_ORIGIN']);
            f3()->set('CORS.credentials', true);
            f3()->set('CORS.headers', 'X-Requested-With, X-Requested-Token, Content-Type, Origin, Accept');
            // f3()->set('CORS.expose', 'true');
            f3()->set('CORS.ttl', '86400');
        }
    }
    else {
        f3()->set('isCORS', 0);
    }
}

