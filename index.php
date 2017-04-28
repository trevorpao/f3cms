<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/libs/Autoload.php';
require_once __DIR__ . '/libs/Utils.php';

$f3 = \Base::instance();

// config
require('./config.php');

try {
    $db = new \DB\SQL($f3->get('db'), $f3->get('db_account'), $f3->get('db_password'));
} catch (Exception $e) {
    header('Content-Type: text');
    echo 'Database is disconnected.'."\n";
    if ($f3->get('DEBUG') > 1) {
        print_r($e);
    }
    exit;
}

$f3->set('DB', $db);

/* session base on DB */
new \DB\SQL\Session($db, 'sessions', TRUE, function($session) {
    //suspect session
    $logger = new \Log('session.log');
    $f3=\Base::instance();
    if (($ip=$session->ip())!=$f3->get('IP')) {
        $logger->write('user changed IP:'.$ip);
    }
    else {
        $logger->write('user changed browser/device:'.$f3->get('AGENT'));
    }
});

// /* session base on cache */
// $f3->set('CACHE', TRUE);
// new Session();

// Define routes
$f3->config('./routes.ini');

if ($f3->get('DEBUG') == 0) {
    $f3->set('ONERROR',function($f3){
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
        if($isAjax) {
            \F3CMS\Reaction::_return(404);
        }
        else {
            \F3CMS\Outfit::wrapper('error.html', 'Not Found', '/404');
        }
    });
}

// load opauth config (allow token resolve)
$f3->config('./opauth.ini', TRUE);

// init with config
$opauth = OpauthBridge::instance($f3->opauth);

// define login handler
$opauth->onSuccess(function($data){
    header('Content-Type: text');
    echo 'User successfully authenticated.'."\n";
    print_r($data['info']);
});

// define error handler
$opauth->onAbort(function($data){
    header('Content-Type: text');
    echo 'Auth request was canceled.'."\n";
    print_r($data);
});

// $opauth->onSuccess('\F3CMS\rUser->socialLogin');

$f3->set('opts', \F3CMS\fOption::load('', 'Preload'));

$f3->run();
