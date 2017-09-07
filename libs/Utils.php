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
