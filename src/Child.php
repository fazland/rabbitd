<?php

namespace Fazland\Rabbitd;

use Fazland\Rabbitd\Connection\ConnectionManager;
use Fazland\Rabbitd\Events\ChildStartEvent;
use Fazland\Rabbitd\Events\Events;
use Fazland\Rabbitd\Process\Process;
use Fazland\Rabbitd\Queue\AmqpLibQueue;
use Fazland\Rabbitd\Queue\QueueInterface;
use Fazland\Rabbitd\Util\ErrorHandlerUtil;
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
     * @var QueueInterface
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

    /**
     * @var bool
     */
    private $running = true;

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
        ErrorHandlerUtil::setLogger($this->logger);

        $connection = $this->connectionManager->getConnection($this->options['connection']);
        if ($connection->isConnected()) {
            $connection->reconnect();
        }

        $this->queue = new AmqpLibQueue($this->logger, $connection, $this->options['queue_name'], $this->eventDispatcher);

        if (!empty($this->options['exchange'])) {
            $this->queue->setExchange($this->options['exchange']['name'], $this->options['exchange']['type']);
        }

        $this->eventDispatcher->dispatch(Events::CHILD_START, new ChildStartEvent($this));
        $this->logger->info('Started. Waiting for jobs...');

        while ($this->running) {
            $this->queue->runLoop();
            $this->eventDispatcher->dispatch(Events::CHILD_EVENT_LOOP);
            $this->logger->debug('Loop event...');
        }

        $this->logger->info('Dying...');
        die;
    }

    /**
     * @return QueueInterface
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
            $this->running = false;
        });
        pcntl_signal(SIGHUP, SIG_DFL);
        pcntl_signal(SIGCHLD, SIG_DFL);
    }
}
