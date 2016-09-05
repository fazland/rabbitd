<?php

namespace Fazland\Rabbitd\Tests\Config;

use Fazland\Rabbitd\Config\Config;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SubConfig extends Config
{
    public function __construct(array $config = [])
    {
        parent::__construct($config);
    }

    protected function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'default_value' => 'foobar',
            'bar' => 'foo',
            'fooz' => 'barz'
        ]);
    }
}

class ConfigTest extends \PHPUnit_Framework_TestCase
{
    public function testIsset()
    {
        $config = new SubConfig();

        $this->assertTrue(isset($config['default_value']));
        $this->assertFalse(isset($config['no_exists']));
    }

    public function testGet()
    {
        $config = new SubConfig([
            'bar' => 'baz'
        ]);

        $this->assertEquals('foobar', $config['default_value']);
        $this->assertEquals('baz', $config['bar']);
    }

    /**
     * @expectedException \Fazland\Rabbitd\Exception\UnknownConfigKeyException
     */
    public function testGetThrowsIfNonExistentConfigParamIsRequested()
    {
        $config = new SubConfig();
        $config['no_exists'];
    }

    public function testGetDontThrowIfParamIsNull()
    {
        $config = new SubConfig([
            'fooz' => null
        ]);

        $this->assertNull($config['fooz']);
    }

    /**
     * @expectedException \LogicException
     */
    public function testSetThrows()
    {
        $config = new SubConfig();
        $config['cant_do'] = 'ciaone';
    }

    /**
     * @expectedException \LogicException
     */
    public function testUnsetThrows()
    {
        $config = new SubConfig();
        unset($config['fooz']);
    }
}
