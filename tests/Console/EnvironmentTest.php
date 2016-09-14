<?php

namespace Fazland\Rabbitd\Tests\Console;

use Fazland\Rabbitd\Console\Environment;

class EnvironmentTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @runInSeparateProcess
     */
    public function testShouldContinueIfEnvIsNotPopulated()
    {
        eval('?><?php 
namespace Fazland\Rabbitd\Console { 
    function ini_get() { return "SGP"; }
    function trigger_error($message) { echo $message; }
}');

        ob_start();
        Environment::createFromGlobal();

        $contents = ob_get_clean();
        $this->assertEquals("variables_order ini directive does not contain 'E'. Environment variables should not be read", $contents);
    }

    public function testGetShouldReturnDefaultIfNotSet()
    {
        $env = new Environment([
            'baz' => 'bbar',
        ]);

        $this->assertEquals('foo', $env->get('bar', 'foo'));
    }

    public function testGetShouldNotReturnDefaultIfNull()
    {
        $env = new Environment([
            'baz' => 'bbar',
            'bar' => null,
        ]);

        $this->assertNull($env->get('bar', 'foo'));
    }
}
