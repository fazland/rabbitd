<?php

namespace Fazland\Rabbitd\Queue;

use Fazland\Rabbitd\Events\Events;
use Fazland\Rabbitd\Events\MessageEvent;
use Fazland\Rabbitd\Exception\MessageHandlerException;
use Fazland\Rabbitd\Exception\MessageUnprocessedException;
use Fazland\Rabbitd\Message\AMQPMessage;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Exception\AMQPIOWaitException;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage as BaseMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AmqpLibQueue implements QueueInterface
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var AbstractConnection
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
     * @param AbstractConnection $connection
     * @param string $queue
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(LoggerInterface $logger, AbstractConnection $connection, $queue, EventDispatcherInterface $eventDispatcher)
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

    public function processMessage(BaseMessage $msg)
    {
        $msg = AMQPMessage::wrap($msg);
        $this->logger->debug('Received '.$msg->getBody());

        try {
            $this->eventDispatcher->dispatch(Events::MESSAGE_RECEIVED, $event = new MessageEvent($msg));
        } catch (\Exception $exception) {
            throw new MessageHandlerException($msg, 'Exception thrown while processing message', 0, $exception);
        }

        if ($event->isProcessed()) {
            $this->postProcess($msg);
        } else {
            throw new MessageUnprocessedException($msg, 'Message left unprocessed. See log for details');
        }

        $this->eventDispatcher->dispatch(Events::MESSAGE_PROCESSED, new MessageEvent($msg));
    }

    public function publishMessage($data)
    {
        $message = new AMQPMessage($data, ['delivery_mode' => 2]);
        $this->channel->basic_publish($message, '', $this->queue);
    }

    public function setExchange($name, $type, $durable, $auto_delete, $arguments = null)
    {
        if (null !== $arguments) {
            $arguments = new AMQPTable($arguments);
        }

        $this->channel->exchange_declare($name, $type, false, $durable, $auto_delete, false, false, $arguments);
        $this->channel->queue_bind($this->queue, $name);
    }

    public function getName()
    {
        return $this->queue;
    }

    /**
     * @param AMQPMessage $msg
     */
    private function postProcess(AMQPMessage $msg)
    {
        if ($msg->needsAck()) {
            $msg->sendAcknowledged();
        }
    }
}
