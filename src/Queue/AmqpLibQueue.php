<?php

namespace Fazland\Rabbitd\Queue;

use Fazland\Rabbitd\Util\Silencer;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPIOWaitException;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\ProcessBuilder;

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
    private $symfony_app;

    /**
     * @var string
     */
    private $queue;

    /**
     * AmpqLibQueue constructor.
     *
     * @param LoggerInterface $logger
     * @param AMQPStreamConnection $connection
     * @param string $queue
     */
    public function __construct(LoggerInterface $logger, AMQPStreamConnection $connection, $queue)
    {
        $this->connection = $connection;
        $this->channel = $this->connection->channel();

        $this->channel->queue_declare($queue, false, true, false, false);

        $this->logger = $logger;
        $this->queue = $queue;

        $this->channel->basic_qos(null, 1, null);
        $this->channel->basic_consume($this->queue, '', false, false, false, false, [$this, 'processMessage']);
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

        $message = Silencer::call('json_decode', $msg->body, true);
        if (false === $message) {
            $message = Silencer::call('unserialize', $msg->body);
        }

        if (! $message) {
            $this->logger->error("Unreadable message '$msg->body'");
        } else {
            switch ($message['type']) {
                case 'run_process':
                    $this->exec($message, $message['cmdline']);
                    break;

                case 'run_symfony':
                    $cmdline = $message['command'];
                    array_unshift($cmdline, $this->symfony_app);
                    $this->exec($message, $cmdline);
                    break;

                default:
                    $this->logger->error("Unknown type '{$message['type']}'");
                    throw new \Exception('Unknown type received. See log for details');
            }
        }

        $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
    }

    public function setExchange($name, $type)
    {
        $this->channel->exchange_declare($name, $type);
    }

    public function setSymfonyConsoleApp($console)
    {
        $this->symfony_app = $console;
    }

    /**
     * @param $message
     * @param $cmdline
     *
     * @throws \Exception
     */
    private function exec($message, $cmdline)
    {
        $stdin = isset($message['stdin']) ? $message['stdin'] : null;

        $process = ProcessBuilder::create($cmdline)
            ->setInput(json_encode($stdin))
            ->setTimeout(null)
            ->getProcess();

        $this->logger->info('Executing '.$process->getCommandLine());

        $process->run(function ($type, $data) {
            $this->logger->debug($data);
        });

        if ($process->getExitCode() != 0) {
            $error = 'Process errored [cmd_line: '.$process->getCommandLine().
                ', input: '.json_encode($stdin)."]\n".
                "Output: \n".$process->getOutput()."\n".
                "Error: \n".$process->getErrorOutput();
            $this->logger->error($error);

            throw new \Exception('Process errored. See log for details');
        }
    }
}
