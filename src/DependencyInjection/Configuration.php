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
            ->ignoreExtraKeys(true)
            ->children()
                ->scalarNode('log_file')
                    ->info('Log filepath')
                    ->defaultValue('/var/log/rabbitd.log')
                ->end()
                ->scalarNode('pid_file')
                    ->info('PID file')
                    ->defaultValue('/var/run/rabbitd.pid')
                ->end()
                ->enumNode('verbosity')
                    ->info('Log verbosity. Could be quiet, normal, verbose, very_verbose or debug')
                    ->values(['quiet', 'normal', 'verbose', 'very_verbose', 'debug'])
                    ->defaultValue('very_verbose')
                ->end()
                ->scalarNode('plugins_dir')
                    ->info('Plugins base directory')
                    ->defaultValue('%application.root_dir%/plugins')
                ->end()
            ->end()
        ;

        $this->addConnectionsNode($root);
        $this->addQueuesNode($root);
        $this->addMasterNode($root);

        $this->pluginManager->addConfiguration($root);

        return $treeBuilder;
    }

    private function addQueuesNode(NodeDefinition $root)
    {
        $processUser = posix_getpwuid(posix_geteuid());
        $processGroup = posix_getgrnam(posix_getegid());

        $root
            ->children()
                ->arrayNode('master')
                    ->addDefaultsIfNotSet()
                    ->info('Master execution configuration')
                    ->children()
                        ->scalarNode('user')
                            ->info('Change user of master process to')
                            ->defaultValue($processUser['name'])
                        ->end()
                        ->scalarNode('group')->defaultValue($processGroup)->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addMasterNode(NodeDefinition $root)
    {
        $root
            ->children()
                ->arrayNode('queues')
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('queue_name')->isRequired()->end()
                            ->scalarNode('connection')
                                ->info('One of the connections defined above')
                                ->defaultValue('default')
                            ->end()
                            ->arrayNode('exchange')
                                ->info('Define ONLY if the queue should be bound to an exchange')
                                ->children()
                                    ->scalarNode('name')->isRequired()->end()
                                    ->enumNode('type')
                                        ->values(['fanout', 'direct', 'x-delayed-message'])
                                        ->defaultValue('fanout')
                                    ->end()
                                    ->booleanNode('durable')->defaultTrue()->end()
                                    ->booleanNode('auto_delete')->defaultFalse()->end()
                                    ->arrayNode('arguments')
                                        ->normalizeKeys(false)
                                        ->defaultValue([])
                                        ->prototype('scalar')->end()
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('worker')
                                ->info('Worker process configuration')
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
                    ->info('Define connections to AMQP brokers')
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
