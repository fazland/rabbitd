<?php

namespace Fazland\Rabbitd\Tests\Util;

use Fazland\Rabbitd\Util\ClassUtils;

class ClassUtilsTest extends \PHPUnit_Framework_TestCase
{
    public function sourceDataProvider()
    {
        $tests = [];

        $source = <<<EOF
<?php

class A {
}
EOF;
        $name = 'A';
        $tests[] = [$source, $name];

        $source = <<<EOF
<?php

namespace B\A;

class    C    {
}

EOF;
        $name = 'B\A\C';
        $tests[] = [$source, $name];

        return $tests;
    }

    /**
     * @dataProvider sourceDataProvider
     */
    public function testGetClassName($source, $expected)
    {
        $this->assertEquals($expected, ClassUtils::getClassName($source));
    }
}
