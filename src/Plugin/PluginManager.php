<?php

namespace Fazland\Rabbitd\Plugin;

use Fazland\Rabbitd\Composer\Composer;
use Fazland\Rabbitd\OutputFormatter\LogFormatter;
use Fazland\Rabbitd\Util\ClassUtils;
use Psr\Log\LoggerInterface;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
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
            $this->logger->info('Adding plugin configuration for "'.$plugin->getName().'"...');

            $plugin->addConfiguration($definition);
        }
    }

    public function onStart(ContainerBuilder $container)
    {
        foreach ($this->plugins as $plugin) {
            $this->logger->info('Starting plugin "'.$plugin->getName().'"...');

            $formatterId = sprintf('rabbitd.plugins.%s.formatter', $plugin->getName());
            $formatter = new Definition(LogFormatter::class);
            $formatter->setArguments([
                'plugins - '.$plugin->getName(),
            ]);

            $outputId = sprintf('rabbitd.plugins.%s.output', $plugin->getName());
            $output = new Definition(StreamOutput::class);
            $output->setFactory([new Reference('application.output_factory'), 'factory']);
            $output->setArguments([
                $container->getParameter('log_file'),
            ]);
            $output->addMethodCall('setFormatter', [new Reference($formatterId)]);

            $logger = new Definition(ConsoleLogger::class);
            $logger->setArguments([
                new Reference($outputId),
                [],
                [
                    'warning' => 'comment',
                ],
            ]);

            $container->setDefinition($formatterId, $formatter);
            $container->setDefinition($outputId, $output);
            $container->setDefinition(sprintf('rabbitd.plugins.%s.logger', $plugin->getName()), $logger);

            $plugin->onStart($container);
        }
    }
}
