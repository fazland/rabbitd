<?php

namespace Fazland\Rabbitd\Events;

use Fazland\Rabbitd\Child;
use Symfony\Component\EventDispatcher\Event;

class ChildStartEvent extends Event
{
    /**
     * @var Child
     */
    private $child;

    public function __construct(Child $child)
    {
        $this->child = $child;
    }

    /**
     * @return Child
     */
    public function getChild()
    {
        return $this->child;
    }
}