<?php

namespace Fazland\Rabbitd\DependencyInjection;

use Fazland\Rabbitd\Plugin\PluginManager;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * @var PluginManager
     */
    private $pluginManager;

    /**
     * {@inheritdoc}
     */
    public function __construct(PluginManager $pluginManager)
    {
        $this->pluginManager = $pluginManager;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $root = $treeBuilder->root('configuration');

        $root
            ->children()
                ->scalarNode('log_file')
                    ->info('Log filepath')
                    ->defaultValue('/var/log/rabbitd.log')
                ->end()
                ->scalarNode('pid_file')
                    ->info('PID file')
                    ->defaultValue('/var/run/rabbitd.pid')
                ->end()
                ->scalarNode('verbosity')
                    ->info('Log verbosity. Could be quiet, normal, verbose, very_verbose or debug')
                    ->defaultValue('very_verbose')
                    ->validate()
                    ->ifNotInArray(['quiet', 'normal', 'verbose', 'very_verbose', 'debug'])
                        ->thenInvalid('Invalid verbosity value %s')
                    ->end()
                ->end()
                ->arrayNode('master')
                    ->children()
                        ->scalarNode('user')->defaultValue('nobody')->end()
                        ->scalarNode('group')->defaultValue('nogroup')->end()
                    ->end()
                ->end()
                ->scalarNode('plugins_dir')
                    ->info('Plugins base directory')
                    ->defaultValue('%application.root_dir%/plugins')
                ->end()
            ->end()
        ;

        $this->addConnectionsNode($root);
        $this->addQueuesNode($root);

        $this->pluginManager->addConfiguration($root);

        return $treeBuilder;
    }

    private function addQueuesNode(NodeDefinition $root)
    {
        $root
            ->children()
                ->arrayNode('queues')
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('queue_name')->isRequired()->end()
                            ->scalarNode('connection')->defaultValue('default')->end()
                            ->arrayNode('exchange')
                                ->children()
                                    ->scalarNode('name')->isRequired()->end()
                                    ->scalarNode('type')
                                        ->validate()
                                            ->ifNotInArray(['fanout'])
                                            ->thenInvalid('Exchange type %s is invalid')
                                        ->end()
                                        ->defaultValue('fanout')
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('worker')
                                ->addDefaultsIfNotSet()
                                ->children()
                                    ->integerNode('processes')->defaultValue(1)->end()
                                    ->scalarNode('user')->defaultNull()->end()
                                    ->scalarNode('group')->defaultNull()->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addConnectionsNode(NodeDefinition $root)
    {
        $root
            ->children()
                ->arrayNode('connections')
                    ->useAttributeAsKey('name')
                    ->addDefaultChildrenIfNoneSet('default')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('hostname')->defaultValue('localhost')->end()
                            ->integerNode('port')->defaultValue(5672)->end()
                            ->scalarNode('username')->defaultValue('guest')->end()
                            ->scalarNode('password')->defaultValue('guest')->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
