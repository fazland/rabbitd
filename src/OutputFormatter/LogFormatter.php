<?php

namespace Fazland\Rabbitd\OutputFormatter;

use Symfony\Component\Console\Formatter\OutputFormatter;

class LogFormatter extends OutputFormatter
{
    /**
     * @var string
     */
    private $name;

    public function __construct($name, $decorated = false, $styles = [])
    {
        $this->name = $name;
        parent::__construct($decorated, $styles);
    }

    public function format($message)
    {
        $dt = \DateTime::createFromFormat('U', time());
        $message = '['.$dt->format('Y-m-d H:i:s.u').' - '.$this->name.'] '.$message;

        return parent::format($message);
    }
}
