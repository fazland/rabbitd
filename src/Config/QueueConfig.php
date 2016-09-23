<?php

namespace Fazland\Rabbitd\Config;

use Symfony\Component\OptionsResolver\Options;
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
            'queue.exchange' => null,
            'processes' => 1,
            'symfony.app' => $this->masterConfig['symfony.app'],
            'worker.user' => $this->masterConfig['master.user'],
            'worker.group' => $this->masterConfig['master.group'],
        ]);

        $resolver->setAllowedTypes('symfony.app', 'string');
        $resolver->setAllowedTypes('worker.user', 'string');
        $resolver->setAllowedTypes('worker.group', 'string');
        $resolver->setAllowedTypes('queue.exchange', ['array', 'null']);

        $resolver->setNormalizer('queue.exchange', function (Options $options, $value) {
            if (null === $value) {
                return $value;
            }

            $resolver = new OptionsResolver();
            $resolver->setRequired('name');
            $resolver->setDefault('type', 'fanout');

            return $resolver->resolve($value);
        });
    }
}
