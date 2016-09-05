<?php

namespace Fazland\Rabbitd\OutputFormatter;

use Symfony\Component\Console\Formatter\OutputFormatter;

abstract class LogFormatter extends OutputFormatter
{
    public function format($message)
    {
        $dt = new \DateTime();
        $message = '['.$dt->format('Y-m-d H:i:s.u').' - '.$this->getName().'] '.$message;

        return parent::format($message);
    }

    abstract public function getName();
}
