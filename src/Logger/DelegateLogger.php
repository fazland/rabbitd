<?php

namespace Fazland\Rabbitd\Logger;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

class DelegateLogger implements LoggerInterface
{
    use LoggerTrait;

    /**
     * @var LoggerInterface
     */
    private $delegate;

    public function __construct(LoggerInterface $logger = null)
    {
        $this->delegate = $logger;
    }

    /**
     * @param LoggerInterface $delegate
     */
    public function setLogger($delegate)
    {
        $this->delegate = $delegate;
    }

    public function log($level, $message, array $context = [])
    {
        if (null === $this->delegate) {
            return;
        }

        $this->delegate->$level($message, $context);
    }
}
