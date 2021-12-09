<?php

$config = require '.php-cs-fixer.dist.php';

$config->setRules(array_merge($config->getRules(), [
    '@PSR12' => true,
    // '@Symfony' => true,
    // '@PhpCsFixer' => true,
]));

return $config;