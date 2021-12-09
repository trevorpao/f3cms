<?php

$config = require '.php-cs-fixer.dist.php';

$config->setRules(array_merge($config->getRules(), [
]));

return $config;
