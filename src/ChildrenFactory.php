<?php

namespace Fazland\Rabbitd;

use Fazland\Rabbitd\Connection\ConnectionManager;
use Fazland\Rabbitd\Console\OutputFactory;
use Fazland\Rabbitd\OutputFormatter\LogFormatter;
use Fazland\Rabbitd\Process\CurrentProcess;
use Fazland\Rabbitd\Process\Process;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ChildrenFactory
{
    /**
     * @var ConnectionManager
     */
    private $connectionManager;

    /**
     * @var OutputFactory
     */
    private $outputFactory;

    /**
     * @var string
     */
    private $logFile;

    /**
     * @var CurrentProcess
     */
    private $currentProcess;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(ConnectionManager $connectionManager, OutputFactory $outputFactory, $logFile, CurrentProcess $currentProcess, EventDispatcherInterface $eventDispatcher)
    {
        $this->connectionManager = $connectionManager;
        $this->outputFactory = $outputFactory;
        $this->logFile = $logFile;
        $this->currentProcess = $currentProcess;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function createChild($name, array $options)
    {
        $output = $this->outputFactory->factory($this->logFile);
        $output->setFormatter(new LogFormatter($name));
        $logger = new ConsoleLogger($output, [], [LogLevel::WARNING => 'comment']);

        $child = new Child($logger, $this->connectionManager, $this->eventDispatcher, $options);

        $this->restartChild($name, $child);

        return $child;
    }

    public function restartChild($name, Child $child)
    {
        if (++$child->restarts % 3 == 0) {
            // Prevent consuming all the system resources in case of queue connection error
            // @todo Think about something better!
            sleep(10);
        }

        if (null !== $child->getProcess() && $child->getProcess()->isAlive()) {
            $child->getProcess()->kill(SIGTERM);
        }

        $options = $child->getOptions();
        if ($pid = $this->currentProcess->fork()) {
            $child->setProcess(new Process($pid));
        } else {
            $this->currentProcess->setProcessTitle('rabbitd ('.$name.')');
            $this->currentProcess
                ->setUser($options['worker']['user'])
                ->setGroup($options['worker']['group']);

            $child->setProcess($this->currentProcess);
            $child->installSignalHandlers();

            $child->run();
        }
    }
}
