<?php

declare (ticks = 1);

if (class_exists('Phar')) {
    $dir = dirname(\Phar::running());
}

if (empty($dir)) {
    $dir = realpath(__DIR__);
}

if (file_exists($dir.'/vendor/autoload.php')) {
    $loader = require $dir.'/vendor/autoload.php';
} else {
    $loader = require __DIR__.'/vendor/autoload.php';
}

use Fazland\Rabbitd\Application;
use Fazland\Rabbitd\Console\Environment;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Debug\ErrorHandler;

set_time_limit(0);

if (! function_exists('pcntl_fork')) {
    throw new \RuntimeException('pcntl_* functions are not available, cannot continue');
}

$output = new StreamOutput(fopen('php://stdout', 'ab'), Output::VERBOSITY_VERY_VERBOSE);
$logger = new ConsoleLogger($output, [], [LogLevel::WARNING => 'comment']);

$errorHandler = ErrorHandler::register();
$errorHandler->throwAt(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED & ~E_USER_WARNING, true);
$errorHandler->setDefaultLogger($logger, E_ALL, true);

$environment = Environment::createFromGlobal();
$definition = new InputDefinition([
    new InputOption('config', 'c', InputOption::VALUE_REQUIRED, 'Set configuration file', $environment->get('CONF_DIR', posix_getcwd().'/conf/rabbitd.yml')),
]);

$application = new Application($environment, $loader);
$application->setLogger($logger);
$application->start(new ArgvInput(null, $definition), $output);
