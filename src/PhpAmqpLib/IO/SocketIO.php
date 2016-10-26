<?php

namespace Fazland\Rabbitd\PhpAmqpLib\IO;

use PhpAmqpLib\Wire\IO\SocketIO as BaseIO;

class SocketIO extends BaseIO
{
    public function select($sec, $usec)
    {
        $read = array($this->getSocket());
        $write = null;
        $except = null;

        return @socket_select($read, $write, $except, $sec, $usec);
    }
}