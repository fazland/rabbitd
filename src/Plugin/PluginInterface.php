<?php

namespace Fazland\Rabbitd\Plugin;

use Fazland\Rabbitd\Application\Application;
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

    /**
     * Finds and registers Commands.
     * @param Application $application
     */
    public function registerCommands(Application $application);

    /**
     * Get the plugin root path
     *
     * @return string
     */
    public function getPath();

    /**
     * Get configuration values to be prepended when
     * processing configuration file
     *
     * @param array $configuration Current configuration array (not validated!)
     *
     * @return array
     */
    public function prependConfiguration(array $configuration);
}
