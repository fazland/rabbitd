<?php

namespace Fazland\Rabbitd\Queue;

use Fazland\Rabbitd\Events\Events;
use Fazland\Rabbitd\Events\MessageEvent;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPIOWaitException;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AmqpLibQueue
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var AMQPStreamConnection
     */
    private $connection;

    /**
     * @var \PhpAmqpLib\Channel\AMQPChannel
     */
    private $channel;

    /**
     * @var string
     */
    private $queue;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * AmpqLibQueue constructor.
     *
     * @param LoggerInterface $logger
     * @param AMQPStreamConnection $connection
     * @param string $queue
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(LoggerInterface $logger, AMQPStreamConnection $connection, $queue, EventDispatcherInterface $eventDispatcher)
    {
        $this->connection = $connection;
        $this->channel = $this->connection->channel();

        $this->channel->queue_declare($queue, false, true, false, false);

        $this->logger = $logger;
        $this->queue = $queue;

        $this->channel->basic_qos(null, 1, null);
        $this->channel->basic_consume($this->queue, '', false, false, false, false, [$this, 'processMessage']);
        $this->eventDispatcher = $eventDispatcher;
    }

    public function __destruct()
    {
        $this->channel->close();
    }

    public function runLoop()
    {
        try {
            $this->channel->wait(null, true, 1);
        } catch (AMQPTimeoutException $ex) {
        } catch (AMQPIOWaitException $ex) {
        }
    }

    public function processMessage(AMQPMessage $msg)
    {
        $this->logger->debug('Received '.$msg->body);

        $this->eventDispatcher->dispatch(Events::MESSAGE_RECEIVED, $event = new MessageEvent($msg));

        if ($event->isProcessed()) {
            $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
        } else {
            throw new \Exception('Message left unprocessed. See log for details');
        }
    }

    public function setExchange($name, $type)
    {
        $this->channel->exchange_declare($name, $type);
        $this->channel->queue_bind($this->queue, $name);
    }
}
