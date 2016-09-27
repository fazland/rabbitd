<?php

namespace Fazland\Rabbitd;

use Composer\Autoload\ClassLoader;
use Fazland\Rabbitd\Composer\Composer;
use Fazland\Rabbitd\Console\Environment;
use Fazland\Rabbitd\DependencyInjection\CompilerPass\ConnectionCreator;
use Fazland\Rabbitd\DependencyInjection\CompilerPass\EventListenerPass;
use Fazland\Rabbitd\DependencyInjection\CompilerPass\VerbosityNormalizer;
use Fazland\Rabbitd\DependencyInjection\Configuration;
use Fazland\Rabbitd\Events\ErrorEvent;
use Fazland\Rabbitd\Events\Events;
use Fazland\Rabbitd\Util\Silencer;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Yaml\Yaml;

class Application
{
    /**
     * @var Environment
     */
    private $environment;

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var ClassLoader
     */
    private $autoload;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(Environment $environment, ClassLoader $autoload)
    {
        $this->environment = $environment;
        $this->container = $this->createContainerBuilder();
        $this->autoload = $autoload;

        $this->setLogger(new NullLogger());
    }

    public function start(InputInterface $input, OutputInterface $output)
    {
        $this->container->setParameter('application.root_dir', $this->getRootDir(false));
        $this->container->setParameter('application.root_uri', $this->getRootDir(true));

        $configuration = $this->readConfigurationFile($input);
        if (isset($configuration['configuration']['plugins_dir'])) {
            $pluginsDir = $configuration['configuration']['plugins_dir'];
        } else {
            $pluginsDir = $this->getRootDir(false).'/plugins';
        }

        $this->container->setParameter('plugins_dir', $pluginsDir);
        $loader = new YamlFileLoader($this->container, new FileLocator(__DIR__.'/Resources/config'));
        $loader->load('services.yml');

        $this->initPlugins();

        $this->logger->info('Processing configuration...');
        $processor = new Processor();
        $conf = $processor->processConfiguration($this->createConfiguration(), $configuration);

        $this->container->getParameterBag()->add($conf);

        $composer = $this->container->get('application.plugin_manager.composer');
        $composer->setOutput($output);

        $this->onStart();

        $this->container->compile();
        $this->container->get('event_dispatcher')->dispatch(Events::PRE_START);

        try {
            $this->container->get('application.master')->run();
        } catch (\Exception $e) {
            $this->onError($e);
        } catch (\Throwable $e) {
            $this->onError($e);
        }
    }

    protected function createContainerBuilder()
    {
        return new ContainerBuilder();
    }

    protected function createConfiguration()
    {
        $pluginManager = $this->container->get('application.plugin_manager');
        $configuration = new Configuration($pluginManager);

        return $configuration;
    }

    protected function getRootDir($uri = false)
    {
        if (class_exists('Phar')) {
            $dir = dirname(\Phar::running($uri));
        }

        if (empty($dir)) {
            $dir = realpath(__DIR__.'/..');
        }

        return $dir;
    }

    /**
     * @param \Throwable $throwable
     *
     * @throws \Throwable
     */
    protected function onError($throwable)
    {
        $this->container
            ->get('event_dispatcher')
            ->dispatch(Events::ERROR, new ErrorEvent($throwable));

        throw $throwable;
    }

    private function readConfigurationFile(InputInterface $input)
    {
        $path = $input->getOption('config');
        $content = file_get_contents($path);

        return ['configuration' => Yaml::parse($content)];
    }

    private function onStart()
    {
        $this->container
            ->addCompilerPass(new ConnectionCreator())
            ->addCompilerPass(new VerbosityNormalizer())
            ->addCompilerPass(new EventListenerPass(), PassConfig::TYPE_OPTIMIZE);

        Silencer::call('mkdir', dirname($this->container->getParameter('log_file')), 0777, true);

        $this->container->get('application.plugin_manager')->onStart($this->container);
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->container->set('application.console_logger', $logger);
    }

    private function initPlugins()
    {
        $pluginManager = $this->container->get('application.plugin_manager');
        $pluginManager->addComposerDependencies();

        $this->logger->info('Resolving composer dependencies...');
        $this->container->get('application.plugin_manager.composer')->resolve();

        require $this->getRootDir(false).'/vendor/composer/autoload_static.php';
        require $this->getRootDir(false).'/vendor/composer/autoload_real.php';
        $this->autoload = require $this->getRootDir(false).'/vendor/autoload.php';

        $pluginManager->initPlugins();
    }
}
