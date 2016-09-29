<?php

namespace Fazland\Rabbitd\DependencyInjection\CompilerPass;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\Debug\TraceableEventDispatcher;
use Symfony\Component\Stopwatch\Stopwatch;

class TraceableEventDispatcherSetterPass implements CompilerPassInterface
{
    /**
     * @inheritDoc
     */
    public function process(ContainerBuilder $container)
    {
        $verbosity = $container->getParameter('verbosity');

        if ($verbosity !== 'debug' && $verbosity !== OutputInterface::VERBOSITY_DEBUG) {
            return;
        }

        $container->register('application.debug.event_dispatcher_stopwatch', Stopwatch::class);

        $container->register('application.debug.traceable_event_dispatcher', TraceableEventDispatcher::class)
            ->setDecoratedService('event_dispatcher', 'application.debug.traceable_event_dispatcher.inner')
            ->addArgument(new Reference('application.debug.traceable_event_dispatcher.inner'))
            ->addArgument(new Reference('application.debug.event_dispatcher_stopwatch'))
            ->addArgument(new Reference('logger'))
        ;
    }
}
