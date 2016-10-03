<?php

namespace Fazland\Rabbitd\Message;

use PhpAmqpLib\Message\AMQPMessage as BaseMessage;

class AMQPMessage extends BaseMessage implements MessageInterface
{
    private $needAck = true;

    /**
     * @param BaseMessage $message
     *
     * @return self
     */
    public static function wrap(BaseMessage $message)
    {
        $instance = new self($message->body, $message->get_properties());
        $instance->body_size = $message->body_size;
        $instance->content_encoding = $message->content_encoding;
        $instance->delivery_info = $message->delivery_info;
        $instance->is_truncated = $message->is_truncated;

        return $instance;
    }

    /**
     * {@inheritdoc}
     */
    public function sendAcknowledged()
    {
        $this->delivery_info['channel']->basic_ack($this->delivery_info['delivery_tag']);
    }

    /**
     * @return bool
     */
    public function needsAck()
    {
        return $this->needAck;
    }

    /**
     * @param bool $needAck
     */
    public function setNeedAck($needAck)
    {
        $this->needAck = $needAck;
    }
}
