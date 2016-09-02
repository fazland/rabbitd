#!/usr/bin/env php
<?php

declare(ticks = 1);

use Fazland\Rabbitd\Application;

set_time_limit(0);
cli_set_process_title('rabbitd');

require realpath(__DIR__).'/vendor/autoload.php';

$application = new Application();
$application->run();
