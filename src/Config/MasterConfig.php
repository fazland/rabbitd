<?php

namespace Fazland\Rabbitd\Config;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Yaml\Yaml;

class MasterConfig extends Config
{
    public function __construct($filename)
    {
        $config = null;
        if (is_readable($filename)) {
            $config = Yaml::parse(@file_get_contents($filename));
        }

        if (empty($config)) {
            $config = [];
        }

        parent::__construct($config);
    }

    protected function configureOptions(OptionsResolver $resolver)
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
}
