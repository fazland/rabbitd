<?php

namespace Fazland\Rabbitd\EventListener;

use Fazland\Rabbitd\Events\Events;
use Fazland\Rabbitd\Master;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MasterLoopChecker implements EventSubscriberInterface
{
    /**
     * @var bool
     */
    public static $check = false;

    /**
     * @var Master
     */
    private $master;

    /**
     * @var int
     */
    private $loopCount = 0;

    public function __construct(Master $master)
    {
        $this->master = $master;
    }

    public function onLoop()
    {
        if ($this->loopCount++ % 10 == 0 || self::$check) {
            $this->master->sanityCheck();
            self::$check = false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            Events::EVENT_LOOP => 'onLoop',
        ];
    }
}
