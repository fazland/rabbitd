<?php

namespace Fazland\Rabbitd\Plugin;

use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class AbstractPlugin implements PluginInterface
{
    /**
     * @inheritDoc
     */
    public function addConfiguration(NodeDefinition $root)
    {
    }

    /**
     * @inheritDoc
     */
    public function onStart(ContainerInterface $container)
    {
    }
}
