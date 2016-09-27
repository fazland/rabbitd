<?php

namespace Fazland\Rabbitd\EventListener;

use Fazland\Rabbitd\Events\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SignalDispatcher implements EventSubscriberInterface
{
    public function onLoop()
    {
        return pcntl_signal_dispatch();
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            Events::EVENT_LOOP => 'onLoop',
            Events::CHILD_EVENT_LOOP => 'onLoop',
        ];
    }
}
