<?php

namespace Fazland\Rabbitd\Plugin;

use Fazland\Rabbitd\Application\Application;
use Fazland\Rabbitd\Composer\Composer;
use Fazland\Rabbitd\OutputFormatter\LogFormatter;
use Fazland\Rabbitd\Util\ClassUtils;
use Psr\Log\LoggerInterface;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\DependencyInjection\ContainerBuilder;
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

    public function runComposer(InputInterface $input, OutputInterface $output, HelperSet $helperSet)
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

        $this->composer->resolve($input, $output, $helperSet);
    }

    public function initPlugins()
    {
        $finder = Finder::create()
            ->files()
            ->path('/\.php$/ui')
            ->in($this->pluginsDir)
            ->depth('== 1');

        $warned = false;

        foreach ($finder as $file) {
            $className = ClassUtils::getClassName($file->getContents());
            if (empty($className)) {
                continue;
            }

            try {
                $reflClass = new \ReflectionClass($className);
            } catch (\ReflectionException $e) {
                // Autoload not ok. User should run the update-plugins command
                if (! $warned) {
                    $this->logger->warning('Exception while loading "'.$className.'". Probably you should run the "update-plugins" command');
                    $warned = true;
                }

                continue;
            }

            if (! $reflClass->implementsInterface(PluginInterface::class)) {
                continue;
            }

            $plugin = $reflClass->newInstance();
            $this->plugins[] = $plugin;
        }
    }

    public function registerCommands(Application $application)
    {
        foreach ($this->plugins as $plugin) {
            $plugin->registerCommands($application);
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

            $container->setParameter(sprintf('rabbitd.plugins.%s.root_dir', $plugin->getName()), $plugin->getPath());

            $formatterId = sprintf('rabbitd.plugins.%s.formatter', $plugin->getName());
            $outputId = sprintf('rabbitd.plugins.%s.output', $plugin->getName());

            $container->register($formatterId, LogFormatter::class)
                ->setArguments(['plugins - '.$plugin->getName()]);

            $container->register($outputId, StreamOutput::class)
                ->setFactory([new Reference('application.output_factory'), 'factory'])
                ->setArguments([
                    $container->getParameter('log_file'),
                ])
                ->addMethodCall('setFormatter', [new Reference($formatterId)]);

            $container->register(sprintf('rabbitd.plugins.%s.logger', $plugin->getName()), ConsoleLogger::class)
                ->setArguments([
                    new Reference($outputId),
                    [],
                    [
                        'warning' => 'comment',
                    ],
                ]);

            $plugin->onStart($container);
        }
    }

    public function getPrependedConfig(array $config)
    {
        foreach ($this->plugins as $plugin) {
            $config = array_merge_recursive($plugin->prependConfiguration($config), $config);
        }

        return $config;
    }
}
