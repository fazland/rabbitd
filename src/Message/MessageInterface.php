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
}
