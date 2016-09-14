<?php

declare (ticks = 1);
require __DIR__.'/vendor/autoload.php';

use Fazland\Rabbitd\Application;

set_time_limit(0);

$application = new Application();
$application->run();
