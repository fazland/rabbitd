<?php

namespace Fazland\Rabbitd\Connection;

use PhpAmqpLib\Connection\AMQPStreamConnection;

class ConnectionManager
{
    /**
     * @var \AMQPConnection[]
     */
    private $connections;

    /**
     * @var array
     */
    private $parameters;

    public function __construct()
    {
        $this->connections = [];
        $this->parameters = [];
    }

    public function addConnectionParameters($name, $parameters)
    {
        $this->parameters[$name] = $parameters;
    }

    public function getConnection($name)
    {
        if (isset($this->connections[$name])) {
            return $this->connections[$name];
        }

        if (! isset($this->parameters[$name])) {
            throw new \RuntimeException('Connection with name '.$name.' does not exists');
        }

        $parameters = $this->parameters[$name];
        return $this->connections[$name] = new AMQPStreamConnection(
            $parameters['hostname'],
            $parameters['port'],
            $parameters['username'],
            $parameters['password']
        );
    }
}