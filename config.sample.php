<?php
$f3->set('AUTOLOAD', './modules/;./libs/;../vendor/;');

$f3->set('vendors', '../vendor/');

$f3->set('PACKAGE', 'F3CMS');

$f3->set('LOGS', $f3->get('TEMP') . 'logs/');

$f3->set('UI', './theme/');

$f3->set('TZ', 'Asia/Taipei');

$f3->set('chkLogin', 0);

$f3->set('DEBUG', 2);

$f3->set('forceHTTPS', 0);

$f3->set('siteBeginDate', 'Mar 31 2099 23:59:00'); // remove this after online

$f3->set('abspath', dirname(__FILE__) . '/');

$f3->set('fe_version', '180807001');

$f3->set('cache.post', 5);
$f3->set('cache.press', 5); // 0 : only use published static file

// db setting
$f3->set('db_host', 'mariadb');
$f3->set('db_name', 'target_db');
$f3->set('db', 'mysql:host=' . $f3->get('db_host') . ';port=3306;dbname=' . $f3->get('db_name'));
$f3->set('db_account', 'root');
$f3->set('db_password', 'sPes4uBrEcHUq5qE');
$f3->set('tpf', 'tbl_');

$f3->set('uri', 'https://loc.f3cms.com:4433' . $f3->get('BASE'));

$f3->set('site_title', 'Demo');

$f3->set('theme', 'default');

$f3->set('defaultLang', 'tw');

$f3->set('acceptLang', ['tw', 'en']);

// for Class:Upload
//thumbnail
$f3->set('post_thn', [260, 196]);
$f3->set('author_thn', [400, 300]);
$f3->set('default_thn', [300, 300]);
$f3->set('adv_thn', [293, 293]);
$f3->set('all_thn', [128, 128]);
// acceptable file type
$f3->set('photo_acceptable', [
    'image/pjpeg',
    'image/jpeg',
    'image/jpg',
    'image/gif',
    'image/png',
    'image/x-png',
    'application/octet-stream'
]);
//file upload max size
$f3->set('maxsize', 20971520);

//EMAIL
$f3->set('smtp_host', 'smtp.gmail.com');
$f3->set('smtp_port', 465);
$f3->set('smtp_account', 'your_account');
$f3->set('smtp_password', 'your_password'); //
$f3->set('smtp_name', 'your_account');
$f3->set('webmaster', 'your_email');

if ($_SERVER['SERVER_NAME'] != 'loc.f3cms.com' && php_sapi_name() != 'cli') {
    $f3->set('DEBUG', 0);
    $f3->set('forceHTTPS', 1);

    $f3->set('db_host', 'localhost');
    $f3->set('db_name', 'target_db');
    $f3->set('db', 'mysql:host=' . $f3->get('db_host') . ';port=3306;dbname=' . $f3->get('db_name'));
    $f3->set('db_account', 'your_account');
    $f3->set('db_password', 'your_password');
    $f3->set('uri', 'https://domain.com' . $f3->get('BASE'));
}
