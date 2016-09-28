<?php

namespace Fazland\Rabbitd\Application;

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

    /**
     * @var Kernel
     */
    private $kernel;

    public function __construct(Kernel $kernel)
    {
        $this->kernel = $kernel;
        $this->setContainer($this->kernel->getContainer());

        parent::__construct('Rabbitd', 'dev');

        $this->setDefaultCommand('run');
    }

    /**
     * Finds and registers Commands.
     *
     * Override this method if your bundle commands do not follow the conventions:
     *
     * * Commands are in the 'Command' sub-directory
     * * Commands extend Symfony\Component\Console\Command\Command
     *
     * @param Application $application An Application instance
     */
    public function registerCommands()
    {
        $finder = new Finder();
        $finder->files()->name('*Command.php')->in(__DIR__.'/../Command');

        $prefix = 'Fazland\\Rabbitd\\Command';
        foreach ($finder as $file) {
            $ns = $prefix;
            if ($relativePath = $file->getRelativePath()) {
                $ns .= '\\'.str_replace('/', '\\', $relativePath);
            }

            $class = $ns.'\\'.$file->getBasename('.php');

            $r = new \ReflectionClass($class);
            if ($r->isSubclassOf(Command::class) && !$r->isAbstract() && !$r->getConstructor()->getNumberOfRequiredParameters()) {
                $command = $r->newInstance();
                if ($command instanceof ContainerAwareInterface) {
                    $command->setContainer($this->container);
                }

                $this->add($command);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function doRun(InputInterface $input, OutputInterface $output)
    {
        $this->container->set('application.console_logger', new ConsoleLogger($output));
        $this->kernel->boot();

        $this->kernel->configure($this->readConfigurationFile($input));
        $this->setDispatcher($this->container->get('event_dispatcher'));

        $this->registerCommands();

        return parent::doRun($input, $output);
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
        $content = file_get_contents($path);

        return ['configuration' => Yaml::parse($content)];
    }
}
