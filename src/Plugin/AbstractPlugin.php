<?php

namespace Fazland\Rabbitd\Plugin;

use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class AbstractPlugin implements PluginInterface
{
    /**
     * @inheritDoc
     */
    public function onStart(ContainerInterface $container)
    {
    }
}
