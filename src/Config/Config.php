<?php

namespace Fazland\Rabbitd\Config;

use Fazland\Rabbitd\Exception\UnknownConfigKeyException;
use Symfony\Component\OptionsResolver\OptionsResolver;

abstract class Config implements \ArrayAccess
{
    /**
     * @var array
     */
    protected $config;

    public function __construct(array $config)
    {
        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);

        if (empty($config)) {
            $config = [];
        }

        $this->config = $resolver->resolve($config);
    }

    abstract protected function configureOptions(OptionsResolver $resolver);

    /**
     * {@inheritdoc}
     */
    public function offsetExists($offset)
    {
        return isset($this->config[$offset]);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet($offset)
    {
        if (array_key_exists($offset, $this->config)) {
            return $this->config[$offset];
        }

        throw new UnknownConfigKeyException('Config key '.$offset.' does not exists');
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet($offset, $value)
    {
        throw new \LogicException("Can't set a config value");
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset($offset)
    {
        throw new \LogicException("Can't unset a config value");
    }
}
