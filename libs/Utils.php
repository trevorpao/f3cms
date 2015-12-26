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
