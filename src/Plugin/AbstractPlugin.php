<?php

namespace Fazland\Rabbitd\Plugin;

use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

abstract class AbstractPlugin implements PluginInterface
{
    /**
     * {@inheritdoc}
     */
    public function addConfiguration(NodeDefinition $root)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function onStart(ContainerBuilder $container)
    {
    }
}
