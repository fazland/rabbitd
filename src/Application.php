<?php

namespace Fazland\Rabbitd;

use Fazland\Rabbitd\Console\Environment;
use Fazland\Rabbitd\DependencyInjection\CompilerPass\ConnectionCreator;
use Fazland\Rabbitd\DependencyInjection\CompilerPass\EventListenerPass;
use Fazland\Rabbitd\DependencyInjection\CompilerPass\VerbosityNormalizer;
use Fazland\Rabbitd\DependencyInjection\Configuration;
use Fazland\Rabbitd\Events\ErrorEvent;
use Fazland\Rabbitd\Events\Events;
use Fazland\Rabbitd\Util\Silencer;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Input\InputInterface;
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

    public function __construct(Environment $environment)
    {
        $this->environment = $environment;
        $this->container = $this->createContainerBuilder();
    }

    public function start(InputInterface $input)
    {
        $processor = new Processor();
        $conf = $processor->processConfiguration($this->createConfiguration(), $this->readConfigurationFile($input));

        $loader = new YamlFileLoader($this->container, new FileLocator(__DIR__.'/Resources/config'));
        $loader->load('services.yml');

        $this->container->getParameterBag()->add($conf);
        $this->container->setParameter('application.root_dir', $this->getRootDir());

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
        return new Configuration();
    }

    protected function getRootDir()
    {
        if (class_exists('Phar')) {
            $dir = dirname(\Phar::running(false));
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
    }
}
