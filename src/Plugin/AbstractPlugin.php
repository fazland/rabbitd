<?php

namespace Fazland\Rabbitd\Plugin;

use Fazland\Rabbitd\Application\Application;
use Fazland\Rabbitd\Application\ContainerLoaderBuilderTrait;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Finder\Finder;

abstract class AbstractPlugin implements PluginInterface
{
    use ContainerLoaderBuilderTrait;

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

    /**
     * {@inheritdoc}
     */
    public function registerCommands(Application $application)
    {
        if (!is_dir($dir = $this->getPath().'/Command')) {
            return;
        }

        $finder = new Finder();
        $finder->files()->name('*Command.php')->in($dir);

        $application->processCommandFiles($finder, $this->getNamespace().'\\Command');
    }

    /**
     * {@inheritdoc}
     */
    public function prependConfiguration(array $configuration)
    {
        return [];
    }

    public function getPath()
    {
        $reflectionClass = new \ReflectionClass($this);

        return dirname($reflectionClass->getFileName());
    }

    /**
     * Gets the plugin namespace.
     *
     * @return string The plugin namespace
     */
    public function getNamespace()
    {
        $class = get_class($this);

        return substr($class, 0, strrpos($class, '\\'));
    }
}
