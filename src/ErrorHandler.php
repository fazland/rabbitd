<?php

namespace Fazland\Rabbitd;

use Psr\Log\LoggerInterface;

class ErrorHandler
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;

        set_exception_handler(function ($e) {
            /** @var \Throwable $e */
            $this->logger->critical('Unhandled exception: '.$e->getMessage());
            $this->logger->critical('Stack trace');
            $this->logger->critical($e->getTraceAsString());
        });

        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            $this->logger->critical('Error: '.$errstr);
            $this->logger->critical('In file '.$errfile.' at line '.$errline);
        });
    }
}
