<?php

namespace Fazland\Rabbitd\Config;

use Fazland\Rabbitd\Exception\UnknownConfigKeyException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Yaml\Yaml;

class MasterConfig implements \ArrayAccess
{
    /**
     * @var array
     */
    private $config;

    public function __construct($filename)
    {
        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);

        $config = null;
        if (is_readable($filename)) {
            $config = Yaml::parse(@file_get_contents($filename));
        }

        if (empty($config)) {
            $config = [];
        }

        $this->config = $resolver->resolve($config);
    }

    private function configureOptions(OptionsResolver $resolver)
    {
        $logDir = isset($_ENV['LOG_DIR']) ? $_ENV['LOG_DIR'] : posix_getcwd().DIRECTORY_SEPARATOR.'logs';
        $pidFile = isset($_ENV['PIDFILE']) ? $_ENV['PIDFILE'] : posix_getcwd().DIRECTORY_SEPARATOR.'rabbitd.pid';
        $resolver->setDefaults([
            'log_file' => $logDir.DIRECTORY_SEPARATOR.'rabbitd.log',
            'verbosity' => 'very_verbose',
            'pid_file' => $pidFile,
            'queues' => [],
            'symfony.app' => posix_getcwd().DIRECTORY_SEPARATOR.'console',
        ]);

        $resolver->setAllowedTypes('symfony.app', 'string');

        $resolver->setAllowedValues('verbosity', ['quiet', 'normal', 'verbose', 'very_verbose', 'debug']);
        $resolver->setNormalizer('verbosity', function (Options $options, $verbosity) {
            switch ($verbosity) {
                case 'quiet':
                    return OutputInterface::VERBOSITY_QUIET;

                case 'normal':
                    return OutputInterface::VERBOSITY_NORMAL;

                case 'verbose':
                    return OutputInterface::VERBOSITY_VERBOSE;

                case 'very_verbose':
                    return OutputInterface::VERBOSITY_VERY_VERBOSE;

                case 'debug':
                    return OutputInterface::VERBOSITY_DEBUG;
            }
        });

        $resolver->setAllowedTypes('queues', 'array');
        $resolver->setAllowedValues('queues', function (array $value) {
            return count($value);
        });
    }

    /**
     * @inheritDoc
     */
    public function offsetExists($offset)
    {
        return isset($this->config[$offset]);
    }

    /**
     * @inheritDoc
     */
    public function offsetGet($offset)
    {
        if (array_key_exists($offset, $this->config)) {
            return $this->config[$offset];
        }

        throw new UnknownConfigKeyException("Config key ".$offset." does not exists");
    }

    /**
     * @inheritDoc
     */
    public function offsetSet($offset, $value)
    {
        throw new \LogicException("Can't set a config value");
    }

    /**
     * @inheritDoc
     */
    public function offsetUnset($offset)
    {
        throw new \LogicException("Can't unset a config value");
    }
}
