<?php

declare(ticks=1);
define('AMQP_WITHOUT_SIGNALS', true);

require __DIR__.'/vendor/autoload.php';

if (class_exists('Phar')) {
    $dir = dirname(\Phar::running(false));
}

if (empty($dir)) {
    $dir = posix_getcwd();
}

$dir = realpath($dir);

if (file_exists($dir.'/vendor/autoload.php')) {
    $loader = require $dir.'/vendor/autoload.php';
}

use Fazland\Rabbitd\Application\Application;
use Fazland\Rabbitd\Application\Kernel;
use Fazland\Rabbitd\Console\Environment;
use Symfony\Component\Debug\ErrorHandler;

set_time_limit(0);

if (! function_exists('pcntl_fork')) {
    throw new \RuntimeException('pcntl_* functions are not available, cannot continue');
}

$errorHandler = ErrorHandler::register();
$errorHandler->throwAt(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED & ~E_USER_WARNING, true);

$environment = Environment::createFromGlobal();

$kernel = new Kernel();
$kernel->getContainer()->set('environment', $environment);

$application = new Application($kernel);
$application->run();
