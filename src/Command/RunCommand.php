<?php

namespace Fazland\Rabbitd\Command;

use Fazland\Rabbitd\Events\Events;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

class RunCommand extends Command implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('run')
            ->addOption('foreground', 'f', InputOption::VALUE_NONE, 'Execute rabbitd in foreground')
            ->setDescription('Run the rabbitd daemon');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->getApplication()->setCatchExceptions(false);

        $this->container->get('event_dispatcher')->dispatch(Events::PRE_START);
        $this->container->get('application.master')->run(! $input->getOption('foreground'));
    }
}
