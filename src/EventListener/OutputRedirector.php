<?php

namespace Fazland\Rabbitd\EventListener;

use Fazland\Rabbitd\Events\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OutputRedirector implements EventSubscriberInterface
{
    /**
     * @var string
     */
    private $logFile;

    /**
     * @var int
     */
    private $verbosity;

    public function __construct($logFile, $verbosity)
    {
        $this->logFile = $logFile;
        $this->verbosity = $verbosity;
    }

    public function onStart()
    {
        global $STDIN, $STDOUT, $STDERR;

        fclose(STDIN);
        $STDIN = fopen('/dev/null', 'r');

        if (null === $this->logFile || '-' === $this->logFile) {
            return;
        }

        fclose(STDOUT);
        $handle = fopen($this->logFile, 'ab');       // This will be the new stdout since 1 is the lowest free file descriptor
        $STDOUT = $handle;

        fclose(STDERR);
        fopen($this->logFile, 'ab');
        $STDERR = $STDOUT;
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
