<?php

namespace Fazland\Rabbitd\Application;

use Fazland\Rabbitd\DependencyInjection\CompilerPass\ConnectionCreator;
use Fazland\Rabbitd\DependencyInjection\CompilerPass\EventListenerPass;
use Fazland\Rabbitd\DependencyInjection\CompilerPass\VerbosityNormalizer;
use Fazland\Rabbitd\DependencyInjection\Configuration;
use Fazland\Rabbitd\Util\Silencer;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class Kernel
{
    /**
     * @var ContainerBuilder
     */
    private $container;

    public function __construct()
    {
        $this->container = $this->createContainerBuilder();
    }

    public function boot()
    {
        $this->container->setParameter('application.root_dir', $this->getRootDir(false));
        $this->container->setParameter('application.root_uri', $this->getRootDir(true));
        $this->container->set('kernel', $this);
    }

    public function configure(array $configuration)
    {
        if (isset($configuration['configuration']['plugins_dir'])) {
            $pluginsDir = $configuration['configuration']['plugins_dir'];
        } else {
            $pluginsDir = $this->getRootDir(false).'/plugins';
        }

        $this->container->setParameter('plugins_dir', $pluginsDir);
        $loader = new YamlFileLoader($this->container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');

        $this->initPlugins();

        $processor = new Processor();
        $conf = $processor->processConfiguration($this->createConfiguration(), $configuration);

        $this->container->getParameterBag()->add($conf);
        $this->container
            ->addCompilerPass(new ConnectionCreator())
            ->addCompilerPass(new VerbosityNormalizer())
            ->addCompilerPass(new EventListenerPass(), PassConfig::TYPE_OPTIMIZE);

        Silencer::call('mkdir', dirname($this->container->getParameter('log_file')), 0777, true);

        $this->container->get('application.plugin_manager')->onStart($this->container);
        $this->container->compile();
    }

    /**
     * @return ContainerInterface
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * @return Configuration
     */
    public function createConfiguration()
    {
        $pluginManager = $this->container->get('application.plugin_manager');
        $configuration = new Configuration($pluginManager);

        return $configuration;
    }

    protected function createContainerBuilder()
    {
        return new ContainerBuilder();
    }

    protected function getRootDir($uri = false)
    {
        if (class_exists('Phar')) {
            $dir = dirname(\Phar::running($uri));
        }

        if (empty($dir)) {
            $dir = realpath(__DIR__.'/../..');
        }

        return $dir;
    }

    private function initPlugins()
    {
        $pluginManager = $this->container->get('application.plugin_manager');
        $pluginManager->initPlugins();
    }
}
