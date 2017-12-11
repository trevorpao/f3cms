f3CMS
======

Small CMS by F3.


## Documentation

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
