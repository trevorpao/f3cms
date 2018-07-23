<?php
$f3->set('AUTOLOAD','./modules/;./libs/;../vendor/;');

$f3->set('vendors','../vendor/');

$f3->set('LOGS', $f3->get('TEMP').'logs/');

$f3->set('UI','./theme/');

$f3->set('TZ','Asia/Taipei');

$f3->set('chkLogin', 0);

$f3->set('DEBUG', 2);

$f3->set('forceHTTPS', 0);

$f3->set('siteBeginDate', 'Mar 31 2099 23:59:00');

$f3->set('abspath', dirname(__FILE__) . '/');

// db setting
$f3->set('db_host','mariadb');
$f3->set('db_name','target_db');
$f3->set('db','mysql:host='. $f3->get('db_host') .';port=3306;dbname='. $f3->get('db_name'));
$f3->set('db_account','root');
$f3->set('db_password','sPes4uBrEcHUq5qE');
$f3->set('tpf','tbl_');

$f3->set('uri','http://f3cms.lo:8080' . $f3->get('BASE'));

$f3->set('site_title', 'Demo');

// for Class:Upload
//thumbnail
$f3->set('post_thn', [260, 196]);
$f3->set('media_thn', [260, 196]);
$f3->set('press_thn', [100, 100]);
$f3->set('adv_thn', [293, 293]);
$f3->set('all_thn', [68, 68]);
// acceptable file type
$f3->set('photo_acceptable', array(
    "image/pjpeg",
    'image/jpeg',
    'image/jpg',
    'image/gif',
    'image/png',
    "image/x-png",
    "application/octet-stream"
));
//file upload max size
$f3->set('maxsize', 20971520);

//EMAIL
$f3->set('smtp_host', 'smtp.gmail.com');
$f3->set('smtp_port', 465);
$f3->set('smtp_account', 'sense.info.co@gmail.com');
$f3->set('smtp_password', 'ilixxmcanfdnsjgl'); //
$f3->set('smtp_name', 'Trevor Pao');
$f3->set('webmaster', 'shuaib25@gmail.com');

if ($_SERVER['SERVER_NAME']!='f3cms.lo' && php_sapi_name() != 'cli') {
    $f3->set('DEBUG', 0);
    $f3->set('forceHTTPS', 1);

    $f3->set('db','mysql:host=f3cms.lo;port=3306;dbname=');
    $f3->set('db_name','target_db');
    $f3->set('db_account','your_account');
    $f3->set('db_password','your_password');
    $f3->set('uri','https://domain.com' . $f3->get('BASE'));
}
