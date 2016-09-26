<?php

namespace Fazland\Rabbitd\Plugin;

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
     * Called BEFORE the application starts.
     * You can register here DI compiler passes, overrides or event listeners
     *
     * @param ContainerInterface $container
     *
     * @return void
     */
    public function onStart(ContainerInterface $container);
}