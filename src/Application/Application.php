<?php

namespace Fazland\Rabbitd\Application;

use Fazland\Rabbitd\Exception\RestartException;
use Fazland\Rabbitd\Util\ErrorHandlerUtil;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

class Application extends BaseApplication implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    const VERSION = '0.0.6-alpha.6';

    /**
     * @var Kernel
     */
    private $kernel;

    /**
     * @var CommandLocator
     */
    private $commandLocator;

    public function __construct(Kernel $kernel, CommandLocator $commandLocator = null)
    {
        if (null === $commandLocator) {
            $commandLocator = new CommandLocator($this);
        }

        $this->kernel = $kernel;
        $this->commandLocator = $commandLocator;
        $this->setContainer($this->kernel->getContainer());

        parent::__construct('Rabbitd', self::VERSION);

        $this->setDefaultCommand('run');
    }

    /**
     * Finds and registers Commands.
     */
    public function registerCommands()
    {
        $this->commandLocator->register(__DIR__.'/../Command', 'Fazland\\Rabbitd\\Command');
        $this->container->get('application.plugin_manager')->registerCommands($this);
    }

    /**
     * {@inheritdoc}
     */
    public function add(Command $command)
    {
        if ($command instanceof ContainerAwareInterface) {
            $command->setContainer($this->container);
        }

        return parent::add($command);
    }

    public function processCommandFiles(Finder $finder, $prefix)
    {
        foreach ($finder as $file) {
            $ns = $prefix;
            if ($relativePath = $file->getRelativePath()) {
                $ns .= '\\'.str_replace('/', '\\', $relativePath);
            }

            $class = $ns.'\\'.$file->getBasename('.php');

            $r = new \ReflectionClass($class);
            if ($r->isSubclassOf(Command::class) && !$r->isAbstract() && !$r->getConstructor()->getNumberOfRequiredParameters()) {
                $this->add($r->newInstance());
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function doRun(InputInterface $input, OutputInterface $output)
    {
        $this->container->set('application.console_logger', $logger = new ConsoleLogger($output));
        $this->kernel->boot();

        $this->kernel->configure($this->readConfigurationFile($input));
        $this->setDispatcher($this->container->get('event_dispatcher'));

        $this->container->get('logger')->setLogger($logger);
        ErrorHandlerUtil::setLogger($this->container->get('logger'));

        $this->registerCommands();

        return parent::doRun($input, $output);
    }

    /**
     * @return Kernel
     */
    public function getKernel()
    {
        return $this->kernel;
    }

    /**
     * {@inheritdoc}
     */
    protected function doRunCommand(Command $command, InputInterface $input, OutputInterface $output)
    {
        try {
            return parent::doRunCommand($command, $input, $output);
        } catch (RestartException $e) {
            return 0;
        }
    }

    protected function getDefaultInputDefinition()
    {
        $definition = parent::getDefaultInputDefinition();

        $definition->addOption(new InputOption('config', 'c', InputOption::VALUE_REQUIRED, 'Set configuration file', $this->getDefaultConfigurationFilePath()));

        return $definition;
    }

    protected function getDefaultConfigurationFilePath()
    {
        $default_conf_dir = $this->container->get('environment')->get('CONF_DIR', posix_getcwd().'/conf');

        return $default_conf_dir.'/rabbitd.yml';
    }

    private function readConfigurationFile(InputInterface $input)
    {
        $path = $input->getParameterOption(['--config', '-c'], $this->getDefaultConfigurationFilePath());
        if (file_exists($path)) {
            $content = file_get_contents($path);

            return ['configuration' => Yaml::parse($content)];
        }

        return ['configuration' => []];
    }
}
