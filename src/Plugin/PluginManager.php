<?php

namespace Fazland\Rabbitd\Plugin;

use Fazland\Rabbitd\Composer\Composer;
use Fazland\Rabbitd\Util\ClassUtils;
use Psr\Log\LoggerInterface;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Finder\Finder;

class PluginManager
{
    /**
     * @var Composer
     */
    private $composer;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var string
     */
    private $pluginsDir;

    /**
     * @var PluginInterface[]
     */
    private $plugins = [];

    public function __construct(Composer $composer, LoggerInterface $logger, $pluginsDir)
    {
        $this->composer = $composer;
        $this->logger = $logger;
        $this->pluginsDir = $pluginsDir;
    }

    public function addComposerDependencies()
    {
        if (! file_exists($this->pluginsDir)) {
            return;
        }

        $finder = Finder::create()
            ->directories()
            ->in($this->pluginsDir)
            ->depth('== 0');

        foreach ($finder as $directory) {
            $composerPath = $directory->getRealPath().'/composer.json';
            if (! file_exists($composerPath)) {
                continue;
            }

            $this->composer->addPackage($composerPath);
        }
    }

    public function initPlugins()
    {
        $finder = Finder::create()
            ->files()
            ->path('/\.php$/ui')
            ->in($this->pluginsDir)
            ->depth('== 1');

        foreach ($finder as $file) {
            $className = ClassUtils::getClassName($file->getContents());
            if (empty($className)) {
                continue;
            }

            $reflClass = new \ReflectionClass($className);
            if (! $reflClass->implementsInterface(PluginInterface::class)) {
                continue;
            }

            $plugin = $reflClass->newInstance();
            $this->plugins[] = $plugin;
        }
    }

    public function addConfiguration(NodeDefinition $definition)
    {
        foreach ($this->plugins as $plugin) {
            $this->logger->info('Adding plugin configuration for "' . $plugin->getName() . '"...');

            $plugin->addConfiguration($definition);
        }
    }

    public function onStart(ContainerInterface $container)
    {
        foreach ($this->plugins as $plugin) {
            $this->logger->info('Starting plugin "' . $plugin->getName() . '"...');

            $plugin->onStart($container);
        }
    }
}
