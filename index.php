<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/libs/Autoload.php';
require_once __DIR__ . '/libs/Utils.php';

$f3 = \Base::instance();

// config
require './config.php';

// setCORS(['https://loc.f3cms.com:8008', 'http://fe.sense-info.co']); // local BE & FE
if (isset($_SERVER['HTTP_ORIGIN'])) {
    $f3->set('isCORS', 1);
}

if (!is_https() && $f3->get('forceHTTPS') === 1) {
    $redirect = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header('HTTP/1.1 301 Moved Permanently');
    header('Location: ' . $redirect);
    exit();
}

$sess = new \F3CMS\Mession(true, function ($session) {
    $logger = new \Log('session.log');
    if (($ip = $session->ip()) != f3()->get('IP')) {
        $logger->write('user changed IP:' . $ip);
    } else {
        $logger->write('user changed browser/device:' . f3()->get('AGENT'));
    }
});

$f3->set('sess', $sess);

// Define routes
$f3->config('./routes.ini');

if ($f3->get('DEBUG') == 0) {
    $f3->set('ONERROR', function ($f3) {
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
        if ($isAjax) {
            \F3CMS\Reaction::_return(404);
        } else {
            \F3CMS\Outfit::wrapper('error.html', 'Not Found', '/404');
        }
    });
}

$f3->set('opts', \F3CMS\fOption::load('', 'Preload'));

$f3->run();
