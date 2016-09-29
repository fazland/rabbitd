<?php

namespace Fazland\Rabbitd\EventListener;

use Fazland\Rabbitd\Console\Environment;
use Fazland\Rabbitd\Exception\RestartException;
use Fazland\Rabbitd\Process\CurrentProcess;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleExceptionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessUtils;

class ApplicationRestarter implements EventSubscriberInterface
{
    /**
     * @var CurrentProcess
     */
    private $currentProcess;

    /**
     * @var Environment
     */
    private $environment;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(CurrentProcess $currentProcess, Environment $environment, LoggerInterface $logger)
    {
        $this->currentProcess = $currentProcess;
        $this->environment = $environment;
        $this->logger = $logger;
    }

    public function onException(ConsoleExceptionEvent $event)
    {
        if (! $event->getException() instanceof RestartException) {
            return;
        }

        $exec = (new PhpExecutableFinder())->find();

        $cmdline = $this->currentProcess->getArgv();
        array_unshift($cmdline, $exec);

        $cmdline = implode(' ', array_map([ProcessUtils::class, 'escapeArgument'], $cmdline));
        $this->logger->debug('Launching '.$cmdline);

        $commandline = '{ ('.$cmdline.') <&3 3<&- 3>/dev/null & } 3<&0;';
        exec($commandline, $output, $exitCode);

        $time = 5;
        while ($time = sleep($time));

        if ($exitCode !== 0) {
            $text = isset(Process::$exitCodes[$exitCode]) ? Process::$exitCodes[$exitCode] : 'Unknown error';
            $this->logger->critical('Cannot restart process: '.$text.' ('.$exitCode.')');
            $this->logger->critical($output);
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            ConsoleEvents::EXCEPTION => 'onException',
        ];
    }
}
