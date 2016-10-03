<?php

namespace Fazland\Rabbitd\Message;

interface MessageInterface
{
    /**
     * Send ACK signal to queue
     *
     * @return void
     */
    public function sendAcknowledged();

    /**
     * Returns the message body as a string
     *
     * @return string
     */
    public function getBody();

    /**
     * @return bool
     */
    public function needsAck();

    /**
     * @param bool $needAck
     */
    public function setNeedAck($needAck);
}
