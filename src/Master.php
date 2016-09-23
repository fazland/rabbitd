<?php

namespace Fazland\Rabbitd;

use Fazland\Rabbitd\Config\MasterConfig;
use Fazland\Rabbitd\Config\QueueConfig;
use Fazland\Rabbitd\Events\Events;
use Fazland\Rabbitd\Exception\RestartException;
use Fazland\Rabbitd\OutputFormatter\MasterFormatter;
use Fazland\Rabbitd\Process\CurrentProcess;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class Master implements ContainerAwareInterface
{
    use ContainerAwareTrait;

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

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(EventDispatcherInterface $eventDispatcher, LoggerInterface $logger)
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;

        $this->children = [];
    }

    public function run()
    {
        $this->daemonize();
        $this->eventDispatcher->dispatch(Events::START);

        list($handler, ) = set_error_handler('var_dump');
        restore_error_handler();

        $handler->setDefaultLogger($this->logger, E_ALL, true);

        $this->running = true;

        $this->installSignalHandlers();
        $this->initQueues();

        $i = 0;
        while ($this->running) {
            if ($i++ % 10 == 0) {
                $this->sanityCheck();
            }

            pcntl_signal_dispatch();
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

        $this->eventDispatcher->dispatch(Events::STOP);

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

        foreach ($this->children as $name => $child) {
            if (! $child->getProcess()->isAlive()) {
                $this->logger->debug('Child "'.$name.'" is dead. Restarting...');

                $this->container->get('application.children_factory')->restartChild($name, $child);
            }
        }
    }

    private function daemonize()
    {
        $currentProcess = $this->container->get('process');

        // Double fork magic, to prevent daemon to acquire a tty
        if ($pid = $currentProcess->fork()) {
            exit;
        }

        $currentProcess->setSid();

        if ($pid = $currentProcess->fork()) {
            exit;
        }
    }

    private function initQueues()
    {
        $queues = $this->container->getParameter('queues');
        foreach ($queues as $name => $options) {
            for ($i = 0; $i < $options['worker']['processes']; ++$i) {
                $childName = $name.' #'.$i;
                $child = $this->container->get('application.children_factory')
                            ->createChild($childName, $options);

                $this->children[$name] = $child;
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
