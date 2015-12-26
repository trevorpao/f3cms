<?php
$f3->set('AUTOLOAD','./modules/;./app/helpers/;./vendors/;');

$f3->set('vendors','./vendors/');

$f3->set('LOGS', $f3->get('TEMP').'logs/');

$f3->set('UI','./outfit/');

$f3->set('TZ','Asia/Taipei');

$f3->set('chkLogin', 0);

$f3->set('DEBUG', 3);

// db setting
$f3->set('db','mysql:host=localhost;port=3306;dbname=target_db');
$f3->set('db_account','root');
$f3->set('db_password','');
$f3->set('tpf','tbl_');

$f3->set('uri','http://127.0.0.1' . $f3->get('BASE'));

// for Class:Upload
//thumbnail
$f3->set('post_thn', [260, 196]);
$f3->set('media_thn', [260, 196]);
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
$f3->set('smtp_account', 'sense.info.co@gmail.com');
$f3->set('smtp_password', 'ilixxmcanfdnsjgl'); //
$f3->set('smtp_name', 'Trevor Pao');
$f3->set('inquiry_receiver', 'shuaib25@gmail.com');

if ($_SERVER['SERVER_NAME']!='127.0.0.1') {
    $f3->set('DEBUG', 0);
    $f3->set('db','mysql:host=localhost;port=3306;dbname=target_db');
    $f3->set('db_account','your_account');
    $f3->set('db_password','your_password');
    $f3->set('uri','http://domain.com' . $f3->get('BASE'));
}
