<?php

namespace Fazland\Rabbitd\Config;

use Symfony\Component\OptionsResolver\OptionsResolver;

class QueueConfig extends Config
{
    /**
     * @var string
     */
    private $symfonyApp;

    public function __construct(array $config, $symfonyApp)
    {
        $this->symfonyApp = $symfonyApp;
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
            'symfony.app' => $this->symfonyApp
        ]);

        $resolver->setAllowedTypes('symfony.app', 'string');
    }
}
