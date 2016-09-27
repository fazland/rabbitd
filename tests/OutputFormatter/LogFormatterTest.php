<?php

namespace Fazland\Rabbitd\Tests\OutputFormatter;

use Fazland\Rabbitd\OutputFormatter\LogFormatter;

/**
 * @runTestsInSeparateProcesses
 */
class LogFormatterTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        $time = (new \DateTime('2016-09-07 08:00:00', new \DateTimeZone('Etc/UTC')))->getTimestamp();
        eval(<<<PHPCODE
namespace Fazland\Rabbitd\OutputFormatter {
    function time() {
        return $time;
    }
}
PHPCODE
);
    }

    public function testMessageShouldBeFormatted()
    {
        $formatter = new LogFormatter('master');
        $msg = $formatter->format('foo bar message');

        $this->assertEquals('[2016-09-07 08:00:00.000000 - master] foo bar message', $msg);
    }
}
