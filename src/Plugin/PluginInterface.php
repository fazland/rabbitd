<?php

namespace Fazland\Rabbitd\Plugin;

use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

interface PluginInterface
{
    /**
     * Get the plugin name
     *
     * @return string
     */
    public function getName();

    /**
     * Add configuration parameters if necessary
     *
     * @param NodeDefinition $root
     *
     * @return void
     */
    public function addConfiguration(NodeDefinition $root);

    /**
     * Called BEFORE the application starts.
     * You can register here DI compiler passes, overrides or event listeners
     *
     * @param ContainerInterface $container
     *
     * @return void
     */
    public function onStart(ContainerInterface $container);
}