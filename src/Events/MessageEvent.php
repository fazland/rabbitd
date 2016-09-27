<?php

namespace Fazland\Rabbitd\Events;

use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\EventDispatcher\Event;

class MessageEvent extends Event
{
    /**
     * @var bool
     */
    private $processed = false;

    /**
     * @var AMQPMessage
     */
    private $message;

    public function __construct(AMQPMessage $message)
    {
        $this->message = $message;
    }

    /**
     * @return bool
     */
    public function isProcessed()
    {
        return $this->processed;
    }

    /**
     * @param bool $processed
     *
     * @return $this
     */
    public function setProcessed($processed = true)
    {
        $this->processed = $processed;

        return $this;
    }

    /**
     * @return AMQPMessage
     */
    public function getMessage()
    {
        return $this->message;
    }
}
