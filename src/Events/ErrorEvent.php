<?php

namespace Fazland\Rabbitd\Events;

use Symfony\Component\EventDispatcher\Event;

class ErrorEvent extends Event
{
    /**
     * @var \Throwable
     */
    private $throwable;

    public function __construct($throwable)
    {
        $this->throwable = $throwable;
    }

    /**
     * @return \Throwable
     */
    public function getThrowable()
    {
        return $this->throwable;
    }
}