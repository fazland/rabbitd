<?php

namespace Fazland\Rabbitd\DependencyInjection\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class EventListenerPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $dispatcher = $container->findDefinition('event_dispatcher');

        foreach ($container->findTaggedServiceIds('event_listener') as $serviceId => $tags) {
            foreach ($tags as $tag) {
                $event = $tag['event'];
                $method = isset($tag['method']) ? $tag['method'] : 'on'.$event;
                $priority = isset($tag['priority']) ? $tag['priority'] : 0;

                $dispatcher->addMethodCall('addListener', [$event, $method, $priority]);
            }
        }

        foreach ($container->findTaggedServiceIds('event_subscriber') as $serviceId => $unused) {
            $dispatcher->addMethodCall('addSubscriber', [new Reference($serviceId)]);
        }
    }
}
