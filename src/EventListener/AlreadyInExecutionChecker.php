<?php

namespace Fazland\Rabbitd\EventListener;

use Fazland\Rabbitd\Events\Events;
use Fazland\Rabbitd\Process\CurrentProcess;
use Fazland\Rabbitd\Util\Silencer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AlreadyInExecutionChecker implements EventSubscriberInterface
{
    /**
     * @var string
     */
    private $pidFile;

    /**
     * @var CurrentProcess
     */
    private $currentProcess;

    public function __construct($pidFile, CurrentProcess $currentProcess)
    {
        $this->pidFile = $pidFile;
        $this->currentProcess = $currentProcess;
    }

    public function onPreStart()
    {
        $pid = file_exists($this->pidFile) ? (int)file_get_contents($this->pidFile) : null;

        if (! $pid) {
            return;
        }

        if (posix_kill($pid, 0)) {
            throw new \RuntimeException("Rabbitd is already running with PID #$pid");
        }
    }

    public function onStart()
    {
        file_put_contents($this->pidFile, $this->currentProcess->getPid());
    }

    public function onStop()
    {
        Silencer::call('unlink', $this->pidFile);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            Events::PRE_START => ['onPreStart', 255],
            Events::START => ['onStart', 100],
            Events::STOP => ['onStop'],
        ];
    }
}
