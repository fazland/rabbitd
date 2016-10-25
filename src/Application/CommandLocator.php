<?php

namespace Fazland\Rabbitd\Application;

use Symfony\Component\Finder\Finder;

class CommandLocator
{
    /**
     * @var Application
     */
    private $application;

    public function __construct(Application $application)
    {
        $this->application = $application;
    }

    public function register($dir, $namespace)
    {
        $finder = new Finder();
        $finder->files()->name('*Command.php')->in($dir);

        $this->application->processCommandFiles($finder, $namespace);
    }
}