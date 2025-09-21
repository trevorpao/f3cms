<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/libs/Autoload.php';
require_once __DIR__ . '/libs/Utils.php';

$f3 = \Base::instance();

// config
require './config.php';

$logger = new \Log('crontab.log');
$f3->set('cliLogger', $logger);

$logger->write('Info - 新排程');

if ($f3->get('crontabhost') != gethostname()) {
    $logger->write('Error - 本機不能執行排程');
    exit();
}

$f3->set('opts', \F3CMS\fOption::load('', 'Preload'));

// Define routes
$f3->route('GET /', function ($f3, $args) {
    echo PHP_EOL.'**'. $f3->get('site_title') .' CLI**'.PHP_EOL.PHP_EOL;
});

$f3->route('GET /@freq/@tally', '\F3CMS\rCrontab->do_job');
