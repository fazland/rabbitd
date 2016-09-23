<?php

namespace Fazland\Rabbitd\DependencyInjection\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ConnectionCreator implements CompilerPassInterface
{
    /**
     * @inheritDoc
     */
    public function process(ContainerBuilder $container)
    {
        $managerDefinition = $container->getDefinition('connection_manager');
        $connections = $container->getParameter('connections');

        foreach ($connections as $name => $parameters) {
            $managerDefinition->addMethodCall('addConnectionParameters', [$name, $parameters]);
        }
    }
}