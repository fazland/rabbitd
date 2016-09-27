<?php

namespace Fazland\Rabbitd\Plugin;

use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

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
     * @param ContainerBuilder $container
     *
     * @return
     */
    public function onStart(ContainerBuilder $container);
}
