<?php

namespace Fazland\Rabbitd;

use Fazland\Rabbitd\Events\Events;
use Fazland\Rabbitd\Exception\RestartException;
use Fazland\Rabbitd\Util\ErrorHandlerUtil;
use Psr\Log\LoggerInterface;
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
        $this->container->get('logger')->setLogger($this->container->get('application.stream_logger'));
        $this->logger->info('Starting...');

        $this->eventDispatcher->dispatch(Events::START);
        ErrorHandlerUtil::setLogger($this->logger);

        $this->running = true;

        $this->installSignalHandlers();
        $this->initQueues();

        while ($this->running) {
            $this->eventDispatcher->dispatch(Events::EVENT_LOOP);

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
        $this->logger->info('Ended #'.$this->container->get('process')->getPid());

        if ($this->restart) {
            throw new RestartException();
        }
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
        $childrenFactory = $this->container->get('application.children_factory');

        foreach ($queues as $name => $options) {
            for ($i = 0; $i < $options['worker']['processes']; ++$i) {
                $childName = $name.' #'.$i;
                $child = $childrenFactory->createChild($childName, $options);

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
