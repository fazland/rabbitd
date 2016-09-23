<?php

namespace Fazland\Rabbitd\DependencyInjection\CompilerPass;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class VerbosityNormalizer implements CompilerPassInterface
{
    /**
     * @inheritDoc
     */
    public function process(ContainerBuilder $container)
    {
        $verbosity = $container->getParameter('verbosity');

        $container->setParameter('verbosity', $this->normalize($verbosity));
    }

    /**
     * @param string $verbosity
     *
     * @return int
     */
    private function normalize($verbosity)
    {
        switch ($verbosity) {
            case 'quiet':
                return OutputInterface::VERBOSITY_QUIET;

            case 'normal':
                return OutputInterface::VERBOSITY_NORMAL;

            case 'verbose':
                return OutputInterface::VERBOSITY_VERBOSE;

            case 'very_verbose':
                return OutputInterface::VERBOSITY_VERY_VERBOSE;

            case 'debug':
                return OutputInterface::VERBOSITY_DEBUG;
        }
    }

}