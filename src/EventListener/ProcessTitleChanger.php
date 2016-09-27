<?php

namespace Fazland\Rabbitd\EventListener;

use Fazland\Rabbitd\Events\Events;
use Fazland\Rabbitd\Process\CurrentProcess;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProcessTitleChanger implements EventSubscriberInterface
{
    /**
     * @var CurrentProcess
     */
    private $currentProcess;

    public function __construct(CurrentProcess $currentProcess)
    {
        $this->currentProcess = $currentProcess;
    }

    public function onStart()
    {
        $this->changeTitle('master');
    }

    private function changeTitle($name)
    {
        $this->currentProcess->setProcessTitle('rabbitd ('.$name.')');
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            Events::START => ['onStart'],
        ];
    }
}
