<?php

namespace Fazland\Rabbitd\OutputFormatter;

class ChildFormatter extends LogFormatter
{
    private $name;

    public function __construct($name)
    {
        $this->name = $name;

        parent::__construct();
    }

    public function getName()
    {
        return $this->name;
    }
}