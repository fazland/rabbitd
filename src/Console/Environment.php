<?php

namespace Fazland\Rabbitd\Console;

class Environment
{
    /**
     * @var array
     */
    private $env;

    public function __construct(array $env)
    {
        $this->env = $env;
    }

    /**
     * @return self
     */
    public static function createFromGlobal()
    {
        if (strpos(ini_get('variables_order'), 'E') === false) {
            trigger_error("variables_order ini directive does not contain 'E'. Environment variables should not be read", E_USER_WARNING);
        }

        return new self($_ENV);
    }

    public function get($name, $default = null)
    {
        if (! array_key_exists($name, $this->env)) {
            return $default;
        }

        return $this->env[$name];
    }

    public function all()
    {
        return $this->env;
    }
}
