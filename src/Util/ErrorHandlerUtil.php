<?php

namespace Fazland\Rabbitd\Util;

use Psr\Log\LoggerInterface;
use Symfony\Component\Debug\ErrorHandler;

class ErrorHandlerUtil
{
    public static function setLogger(LoggerInterface $logger)
    {
        list($handler, ) = set_error_handler('var_dump');
        restore_error_handler();

        if (! $handler instanceof ErrorHandler) {
            return;
        }

        $handler->setDefaultLogger($logger, E_ALL, true);
    }
}