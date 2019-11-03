<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/libs/Autoload.php';
require_once __DIR__ . '/libs/Utils.php';

$f3 = \Base::instance();

// config
require './config.php';

$f3->set('opts', \F3CMS\fOption::load('', 'Preload'));

// Define routes
$f3->route('GET /', function ($f3, $args) {
    echo '**F3CMS CLI**';
});

$f3->route('GET /demo', function ($f3, $args) {
    $logger = new \Log('move.log');
    $worker = new \F3CMS\Worker('\F3CMS\rPost', 'get', $logger);

    $worker->startWorker(
        ['9P1917', '9F0276', '7F7862', '6Y7912', '4Q7199', 'S2346', '32173J', '62173B', '23471Q'], 'Queue'
    );
});

$f3->run();
