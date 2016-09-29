<?php

namespace Fazland\Rabbitd\Events;

use Fazland\Rabbitd\Message\MessageInterface;
use Symfony\Component\EventDispatcher\Event;

class MessageEvent extends Event
{
    /**
     * @var bool
     */
    private $processed = false;

    /**
     * @var MessageInterface
     */
    private $message;

    public function __construct(MessageInterface $message)
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
     * @return MessageInterface
     */
    public function getMessage()
    {
        return $this->message;
    }
}
