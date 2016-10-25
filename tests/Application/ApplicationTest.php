<?php

namespace Fazland\Rabbitd\Tests\Application;

use Fazland\Rabbitd\Application\Application;
use Fazland\Rabbitd\Application\CommandLocator;
use Fazland\Rabbitd\Application\Kernel;
use Fazland\Rabbitd\Console\Environment;
use Fazland\Rabbitd\Exception\RestartException;
use Fazland\Rabbitd\Logger\DelegateLogger;
use Fazland\Rabbitd\Plugin\PluginManager;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ApplicationTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Kernel|ObjectProphecy
     */
    private $kernel;

    /**
     * @var Application
     */
    private $application;

    /**
     * @var ContainerInterface|ObjectProphecy
     */
    private $container;

    /**
     * @var Environment|ObjectProphecy
     */
    private $environment;

    /**
     * @var PluginManager|ObjectProphecy
     */
    private $pluginManager;

    public function setUp()
    {
        $this->environment = $this->prophesize(Environment::class);
        $this->environment->get(Argument::type('string'), Argument::any())->will(function ($args) {
            return $args[1];
        });

        $this->pluginManager = $this->prophesize(PluginManager::class);

        $this->container = $this->prophesize(ContainerInterface::class);
        $this->container->get('environment')->willReturn($this->environment);
        $this->container->get('event_dispatcher')->willReturn($this->prophesize(EventDispatcherInterface::class));
        $this->container->get('logger')->willReturn($this->prophesize(DelegateLogger::class));
        $this->container->get('application.plugin_manager')->willReturn($this->pluginManager);
        $this->container->set(Argument::cetera())->willReturn();

        $this->kernel = $this->prophesize(Kernel::class);
        $this->kernel->getContainer()->willReturn($this->container);
        $this->kernel->boot()->willReturn();
        $this->kernel->configure(Argument::type('array'))->willReturn();

        $this->application = new Application($this->kernel->reveal(), $this->prophesize(CommandLocator::class)->reveal());
        $this->application->setCatchExceptions(false);
        $this->application->setAutoExit(false);
    }

    public function testRunShouldBeTheDefaultCommand()
    {
        $command = new TestRunCommand();

        $this->application->add($command);
        $this->application->run($this->prophesize(InputInterface::class)->reveal(), $this->prophesize(OutputInterface::class)->reveal());

        $this->assertTrue($command->called);
    }

    public function testShouldRegisterPluginCommands()
    {
        $this->pluginManager->registerCommands($this->application)->shouldBeCalled();

        $this->application->add(new TestRunCommand());
        $this->application->run($this->prophesize(InputInterface::class)->reveal(), $this->prophesize(OutputInterface::class)->reveal());
    }

    public function testAddShouldInjectContainerIntoContainerAwareCommands()
    {
        $this->application->add($command = new TestRunCommand());
        $this->assertEquals($this->container->reveal(), $command->getContainer());
    }

    public function testRestartExceptionShouldNotPropagate()
    {
        $this->application->add($command = new TestRestartExceptionCommand());
        $ret = $this->application->run($this->prophesize(InputInterface::class)->reveal(), $this->prophesize(OutputInterface::class)->reveal());

        $this->assertEquals(0, $ret);
    }
}

class TestRunCommand extends Command implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    public $called = false;

    public function __construct()
    {
        parent::__construct('run');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->called = true;
    }

    public function getContainer()
    {
        return $this->container;
    }
}

class TestRestartExceptionCommand extends Command
{

    public function __construct()
    {
        parent::__construct('run');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        throw new RestartException();
    }
}
