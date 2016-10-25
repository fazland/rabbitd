<?php

namespace Fazland\Rabbitd\Queue;

interface QueueInterface
{
    public function runLoop();

    public function setExchange($name, $type, $durable, $auto_delete, $arguments = null);

    public function publishMessage($data);

    public function getName();
}
