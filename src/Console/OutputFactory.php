<?php

namespace Fazland\Rabbitd\Console;

use Symfony\Component\Console\Output\StreamOutput;

class OutputFactory
{
    private $verbosity;

    public function __construct($verbosity)
    {
        $this->verbosity = $verbosity;
    }

    public function factory($logFile)
    {
        return new StreamOutput(fopen($logFile, 'ab'), $this->verbosity);
    }
}
