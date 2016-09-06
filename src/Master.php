<?php

namespace Fazland\Rabbitd;

use Fazland\Rabbitd\Config\MasterConfig;
use Fazland\Rabbitd\Config\QueueConfig;
use Fazland\Rabbitd\Exception\RestartException;
use Fazland\Rabbitd\OutputFormatter\MasterFormatter;
use Fazland\Rabbitd\Process\CurrentProcess;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\Output;

class Master
{
    /**
     * @var MasterConfig
     */
    private $config;

    /**
     * @var Output
     */
    private $output;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var bool
     */
    private $running;

    /**
     * @var bool
     */
    private $restart;

    /**
     * @var Child[]
     */
    private $children;

    /**
     * @var CurrentProcess
     */
    private $currentProcess;

    public function __construct(MasterConfig $config, Output $output, CurrentProcess $currentProcess)
    {
        $this->config = $config;

        $this->output = $output;
        $this->output->setFormatter(new MasterFormatter());

        $this->currentProcess = $currentProcess;
    }

    public function run()
    {
        $this->running = true;
        $this->logger = new ConsoleLogger($this->output, [], [LogLevel::WARNING => 'comment']);

        $processUser = posix_getpwuid(posix_getuid());
        $this->logger->debug("Currently executing as '{user}'", ['user' => $processUser['name']]);

        $this->installSignalHandlers();
        $this->initQueues();

        $i = 0;
        while ($this->running) {
            if ($i++ % 10 == 0) {
                $this->sanityCheck();
            }

            sleep(1);
        }

        $alive = true;

        while ($alive) {
            $alive = false;
            foreach ($this->children as $child) {
                $alive = $alive || $child->getProcess()->isAlive();
            }

            sleep(1);
        }

        @unlink($this->config['pid_file']);

        if ($this->restart) {
            throw new RestartException();
        }
    }

    public function fork()
    {
        return $this->currentProcess->fork();
    }

    public function sanityCheck()
    {
        if (! $this->running) {
            return;
        }

        foreach ($this->children as $child) {
            if (! $child->getProcess()->isAlive()) {
                $this->logger->debug('Child "'.$child->getName().'" is dead. Restarting...');
                $child->restart($this);
            }
        }
    }

    private function initQueues()
    {
        foreach ($this->config['queues'] as $name => $options) {
            $config = new QueueConfig($options, $this->config['symfony.app']);

            for ($i = 0; $i < $config['processes']; ++$i) {
                $this->children[] = new Child($name.' #'.$i, $config, clone $this->output, $this);
            }
        }
    }

    private function installSignalHandlers()
    {
        $this->logger->debug('Installing signal handlers');

        $handler = function ($signo) {
            if (! $this->running) {
                return;
            }

            $this->running = false;
            $this->logger->info('Received '.($signo === SIGTERM ? 'TERM' : 'HUP').' signal. Stopping loop, process will shutdown after the current job has finished');
            $this->restart = $signo === SIGHUP;

            pcntl_signal(SIGCHLD, SIG_DFL);
            $this->signalTermination();
        };

        pcntl_signal(SIGTERM, $handler);
        pcntl_signal(SIGHUP, $handler);

        pcntl_signal(SIGCHLD, [$this, 'sanityCheck']);
    }

    private function signalTermination()
    {
        foreach ($this->children as $child) {
            $child->getProcess()->kill(SIGTERM);
        }
    }
}
