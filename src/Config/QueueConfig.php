<?php

namespace Fazland\Rabbitd\Config;

use Symfony\Component\OptionsResolver\OptionsResolver;

class QueueConfig extends Config
{
    /**
     * @var MasterConfig
     */
    private $masterConfig;

    public function __construct(array $config, MasterConfig $masterConfig)
    {
        $this->masterConfig = $masterConfig;
        parent::__construct($config);
    }

    protected function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'rabbitmq.hostname' => 'localhost',
            'rabbitmq.port' => 5672,
            'rabbitmq.username' => 'guest',
            'rabbitmq.password' => 'guest',
            'queue.name' => 'task_queue',
            'processes' => 1,
            'symfony.app' => $this->masterConfig['symfony.app'],
            'worker.user' => $this->masterConfig['master.user'],
            'worker.group' => $this->masterConfig['master.group'],
        ]);

        $resolver->setAllowedTypes('symfony.app', 'string');
        $resolver->setAllowedTypes('worker.user', 'string');
        $resolver->setAllowedTypes('worker.group', 'string');
    }
}
