<?php

namespace Fazland\Rabbitd;

use Fazland\Rabbitd\Connection\ConnectionManager;
use Fazland\Rabbitd\Events\ChildStartEvent;
use Fazland\Rabbitd\Events\Events;
use Fazland\Rabbitd\Process\Process;
use Fazland\Rabbitd\Queue\AmqpLibQueue;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class Child
{
    /**
     * @var int
     */
    public $restarts = 0;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Process
     */
    private $process;

    /**
     * @var AmqpLibQueue
     */
    private $queue;

    /**
     * @var array
     */
    private $options;

    /**
     * @var ConnectionManager
     */
    private $connectionManager;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(LoggerInterface $logger, ConnectionManager $connectionManager, EventDispatcherInterface $eventDispatcher, array $options)
    {
        $this->logger = $logger;
        $this->connectionManager = $connectionManager;
        $this->eventDispatcher = $eventDispatcher;
        $this->options = $options;
    }

    public function run()
    {
        $this->logger->info('Starting...');

        list($handler, ) = set_error_handler('var_dump');
        restore_error_handler();

        $handler->setDefaultLogger($this->logger, E_ALL, true);

        $connection = $this->connectionManager->getConnection($this->options['connection']);
        $this->queue = new AmqpLibQueue($this->logger, $connection, $this->options['queue_name']);

        $this->eventDispatcher->dispatch(Events::CHILD_START, new ChildStartEvent($this));

        try {
            $this->queue->runLoop();
        } catch (\Exception $e) {
            $this->logger->critical('Uncaugth exception '.get_class($e).': '.$e->getMessage());
            $this->logger->critical($e->getTraceAsString());
        }

        $this->logger->info('Dying...');
        die;
    }

    /**
     * @return AmqpLibQueue
     */
    public function getQueue()
    {
        return $this->queue;
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @return Process
     */
    public function getProcess()
    {
        return $this->process;
    }

    public function setProcess(Process $process)
    {
        $this->process = $process;
    }

    public function installSignalHandlers()
    {
        pcntl_signal(SIGTERM, function () {
            $this->queue->stopLoop();
        });
        pcntl_signal(SIGHUP, SIG_DFL);
        pcntl_signal(SIGCHLD, SIG_DFL);
    }
}
