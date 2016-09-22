<?php

/*
 * This file was taken from Composer (https://github.com/composer/composer)
 * and improved as suggested since PHP min version is 5.6
 */

namespace Fazland\Rabbitd\Tests\Util;

use Fazland\Rabbitd\Util\Silencer;

/**
 * SilencerTest
 *
 * @author Niels Keurentjes <niels.keurentjes@omines.com>
 */
class SilencerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test succeeds when no warnings are emitted externally, and original level is restored.
     */
    public function testSilencer()
    {
        $before = error_reporting();

        // Check warnings are suppressed correctly
        Silencer::suppress();
        @trigger_error('Test', E_USER_WARNING);
        Silencer::restore();

        // Check all parameters and return values are passed correctly in a silenced call.
        $result = Silencer::call(function ($a, $b, $c) {
            @trigger_error('Test', E_USER_WARNING);

            return $a * $b * $c;
        }, 2, 3, 4);
        $this->assertEquals(24, $result);

        // Check the error reporting setting was restored correctly
        $this->assertEquals($before, error_reporting());
    }

    /**
     * Test whether exception from silent callbacks are correctly forwarded.
     * @expectedException \RuntimeException
     */
    public function testSilencedException()
    {
        $verification = microtime();
        $this->expectExceptionMessage($verification);

        Silencer::call(function () use ($verification) {
            throw new \RuntimeException($verification);
        });
    }
}
