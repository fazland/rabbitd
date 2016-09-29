<?php

namespace Fazland\Rabbitd\Exception;

use PhpAmqpLib\Message\AMQPMessage;

abstract class MessageException extends \RuntimeException
{
    /**
     * @var AMQPMessage
     */
    private $AMQPMessage;

    public function __construct(AMQPMessage $AMQPMessage, $message = '', $code = 0, \Exception $previous = null)
    {
        $this->AMQPMessage = $AMQPMessage;
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return AMQPMessage
     */
    public function getAMQPMessage()
    {
        return $this->AMQPMessage;
    }
}
