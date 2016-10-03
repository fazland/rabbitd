<?php

namespace Fazland\Rabbitd\Exception;

use Fazland\Rabbitd\Message\MessageInterface;

abstract class MessageException extends \RuntimeException
{
    /**
     * @var MessageInterface
     */
    private $queueMessage;

    public function __construct(MessageInterface $queueMessage, $message = '', $code = 0, \Exception $previous = null)
    {
        $this->queueMessage = $queueMessage;
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return MessageInterface
     */
    public function getQueueMessage()
    {
        return $this->queueMessage;
    }
}
