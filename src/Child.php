<?php

namespace Fazland\Rabbitd;

use Fazland\Rabbitd\Config\QueueConfig;
use Fazland\Rabbitd\OutputFormatter\ChildFormatter;
use Fazland\Rabbitd\Process\CurrentProcess;
use Fazland\Rabbitd\Process\Process;
use Fazland\Rabbitd\Queue\AmqpLibQueue;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;

class Child
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var CurrentProcess|Process
     */
    private $process;

    /**
     * @var AmqpLibQueue
     */
    private $queue;

    /**
     * @var QueueConfig
     */
    private $config;

    /**
     * @var int
     */
    private $restarts = 0;

    public function __construct($name, QueueConfig $config, OutputInterface $output, Master $master)
    {
        $this->name = $name;
        $this->output = $output;

        $this->outputFormatter = new ChildFormatter($name);
        $this->output->setFormatter($this->outputFormatter);
        $this->logger = new ConsoleLogger($this->output, [], [LogLevel::WARNING => 'comment']);
        $this->config = $config;

        if (0 === $this->fork($master)) {
            $this->run();
        }
    }

    public function run()
    {
        $this->process->setProcessTitle('rabbitd ('.$this->name.')');
        $this->logger->info('Starting...');

        $this->queue = new AmqpLibQueue($this->logger,
            $this->config['rabbitmq.hostname'],
            $this->config['rabbitmq.port'],
            $this->config['rabbitmq.username'],
            $this->config['rabbitmq.password'],
            $this->config['queue.name']);
        $this->queue->setSymfonyConsoleApp($this->config['symfony.app']);

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
     * @return Process
     */
    public function getProcess()
    {
        return $this->process;
    }

    public function restart(Master $master = null)
    {
        if ($this->process instanceof CurrentProcess) {
            die;
        }

        if (++$this->restarts % 3) {
            // Prevent consuming all the system resources in case of queue connection error
            // @todo Think about something better!
            sleep(10);
        }

        if ($this->process->isAlive()) {
            $this->process->kill(SIGTERM);
        }

        if (0 === $this->fork($master)) {
            $this->run();
        }
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    private function fork(Master $master)
    {
        if ($pid = $master->fork()) {
            $this->process = new Process($pid);
        } else {
            $this->process = new CurrentProcess();

            pcntl_signal(SIGTERM, function () {
                $this->queue->stopLoop();
            });
            pcntl_signal(SIGHUP, SIG_DFL);
            pcntl_signal(SIGCHLD, SIG_DFL);

            $this->process
                ->setUser($this->config['worker.user'])
                ->setGroup($this->config['worker.group']);
        }

        return $pid;
    }
}
