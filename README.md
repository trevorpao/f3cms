f3CMS
======

Small CMS by F3.

## Documentation

### PHP 8.3

```bash
sudo apt install php8.3 php8.3-cli php8.3-fpm php8.3-{common,mysql,gmp,curl,intl,mbstring,xmlrpc,gd,xml,zip,bcmath,imagick} -y

sudo service php8.3-fpm status
sudo service php8.3-fpm restart

sudo vim /etc/php/8.3/fpm/php.ini


rm composer.lock

php8.3 /usr/local/bin/composer instal

composer require bcosca/fatfree-core ikkez/f3-opauth phpoffice/phpspreadsheet tecnickcom/tcpdf mailgun/mailgun-php symfony/http-client nyholm/psr7 \
    aws/aws-sdk-php intervention/image maciejczyzewski/bottomline catfan/medoo twig/twig rakit/validation


```

### Links

* Home page - [https://github.com/trevorpao/f3cms](https://github.com/trevorpao/f3cms)
* Demo page - [https://trevorpao.github.io/f3cms/](https://trevorpao.github.io/f3cms/)

### Dependencies
- [fatfreeframework](https://fatfreeframework.com/3.6/home)

### Installation

At frist, you need to install this [SQL file](https://github.com/trevorpao/f3cms/blob/master/libs/sql/init.sql). 

Then execute following command

    $ git clone https://github.com/trevorpao/f3cms.git ./ 
    $ composer install
    $ cp config.sample.php config.php
    $ vim config.php

Enjoy!

### If No Composer

    $ curl -sS https://getcomposer.org/installer | php
    $ mv composer.phar /usr/local/bin/composer

## Bug tracker

If you find a bug, please report it [here on Github](https://github.com/trevorpao/f3cms/issues)!
