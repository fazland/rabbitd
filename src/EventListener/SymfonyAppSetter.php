<?php

namespace Fazland\Rabbitd\EventListener;

use Fazland\Rabbitd\Events\ChildStartEvent;
use Fazland\Rabbitd\Events\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SymfonyAppSetter implements EventSubscriberInterface
{
    /**
     * @var string
     */
    private $symfonyApp;

    public function __construct($symfonyApp)
    {
        $this->symfonyApp = $symfonyApp;
    }

    public function onChildStart(ChildStartEvent $event)
    {
        $child = $event->getChild();

        $child->getQueue()->setSymfonyConsoleApp($this->symfonyApp);
    }

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents()
    {
        return [
            Events::CHILD_START => ['onChildStart']
        ];
    }
}