<?php

namespace Fazland\Rabbitd\PhpAmqpLib\IO;

use PhpAmqpLib\Wire\IO\SocketIO as BaseIO;
use Symfony\Component\Debug\Exception\ContextErrorException;

class SocketIO extends BaseIO
{
    public function select($sec, $usec)
    {
        $read = array($this->getSocket());
        $write = null;
        $except = null;

        try {
            return socket_select($read, $write, $except, $sec, $usec);
        } catch (ContextErrorException $e) {
            if (strpos($e->getMessage(), 'Interrupted system call') !== false) {
                return 0;
            }

            throw $e;
        }
    }
}
