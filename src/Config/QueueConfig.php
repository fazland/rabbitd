<?php

namespace Fazland\Rabbitd\Config;

use Symfony\Component\OptionsResolver\OptionsResolver;

class QueueConfig extends Config
{
    protected function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'rabbitmq.hostname' => 'localhost',
            'rabbitmq.port' => 5672,
            'rabbitmq.username' => 'guest',
            'rabbitmq.password' => 'guest',
            'queue.name' => 'task_queue',
            'processes' => 1,
        ]);
    }
}
