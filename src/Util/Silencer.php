<?php

/*
 * This file was taken from Composer (https://github.com/composer/composer)
 * and improved as suggested since PHP min version is 5.6
 */

namespace Fazland\Rabbitd\Util;

/**
 * Temporarily suppress PHP error reporting, usually warnings and below.
 *
 * @author Niels Keurentjes <niels.keurentjes@omines.com>
 */
class Silencer
{
    /**
     * @var int[] Unpop stack
     */
    private static $stack = [];

    /**
     * Suppresses given mask or errors.
     *
     * @param int|null $mask Error levels to suppress, default value NULL indicates all warnings and below.
     *
     * @return int The old error reporting level.
     */
    public static function suppress($mask = null)
    {
        if (! isset($mask)) {
            $mask = E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE | E_DEPRECATED | E_USER_DEPRECATED | E_STRICT;
        }

        $old = error_reporting();

        array_push(self::$stack, $old);
        error_reporting($old & ~$mask);

        return $old;
    }

    /**
     * Restores a single state.
     */
    public static function restore()
    {
        if (! empty(self::$stack)) {
            error_reporting(array_pop(self::$stack));
        }
    }

    /**
     * Calls a specified function while silencing warnings and below.
     *
     * @param callable $callable Function to execute.
     * @param array $parameters
     *
     * @return mixed Any exceptions from the callback are rethrown.
     */
    public static function call(callable $callable, ...$parameters)
    {
        try {
            self::suppress();
            $result = $callable(...$parameters);
            self::restore();

            return $result;
        } finally {
            self::restore();
        }
    }
}
