#!/usr/bin/env php
<?php

if (ini_get('phar.readonly')) {
    echo "PHAR readonly flag is set in PHP.ini. Please disable it!\n";
    die;
}

require __DIR__.'/vendor/autoload.php';

$compiler = new \Fazland\Rabbitd\Compiler();
$compiler->compile();
