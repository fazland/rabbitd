<?php

namespace Fazland\Rabbitd\Composer;

use Composer\Config;
use Composer\Factory;
use Composer\Installer;
use Composer\IO\ConsoleIO;
use Composer\IO\IOInterface;
use Composer\IO\NullIO;
use Composer\Json\JsonFile;
use Composer\Package\Loader\RootPackageLoader;
use Composer\Package\RootPackageInterface;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\OutputInterface;

class Composer
{
    /**
     * @var Factory
     */
    private $factory;

    /**
     * @var IOInterface
     */
    private $io;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var JsonFile
     */
    private $rootFile;

    /**
     * @var string[]
     */
    private $packages;

    /**
     * @var string
     */
    private $rootUri;

    public function __construct($rootUri)
    {
        ini_set('memory_limit', '2G');

        $this->factory = new Factory();
        $this->io = new NullIO();
        $this->config = $this->factory->createConfig($this->io);

        $this->rootFile = $this->getJson(__DIR__.'/../../composer.json');
        $jsonConfigSource = new Config\JsonConfigSource($this->rootFile);

        $this->config->setConfigSource($jsonConfigSource);
        $this->config->merge($this->rootFile->read());
        $this->config->merge([
            'config' => ['autoloader-suffix' => md5(uniqid('', true))],
        ]);

        $this->packages = [];
        $this->rootUri = $rootUri;
    }

    public function resolve()
    {
        $composer = $this->factory->createComposer($this->io, $this->config->all());
        $rm = $composer->getRepositoryManager();

        $loader = new RootPackageLoader($rm, $this->config);
        $package = $loader->load($this->rootFile->read());

        $autoload = $package->getAutoload();
        foreach (['psr-0', 'psr-4'] as $type) {
            if (! isset($autoload[$type])) {
                $autoload[$type] = [];
            }

            foreach ($autoload[$type] as &$path) {
                $path = $this->rootUri.'/'.$path;
            }
        }

        $package->setAutoload($autoload);

        $origin = $package->getRequires();
        foreach ($this->packages as $file) {
            $jsonFile = $this->getJson($file);
            $parsed = $jsonFile->read();

            $additionalPackage = $loader->load($parsed);
            foreach ($additionalPackage->getRequires() as $name => $link) {
                if (! isset($origin[$name])) {
                    $origin[$name] = $link;
                } else {
                    $origin[] = $link;
                }
            }

            $this->mergeAutoload($package, $additionalPackage, dirname($file));
        }

        $package->setRequires($origin);
        $composer->setPackage($package);

        $composer->setLocker(new MockLocker());

        $installer = Installer::create($this->io, $composer);
        $installer->setUpdate();

        $installer->run();
    }

    public function addPackage($composerJson)
    {
        $this->packages[] = $composerJson;
    }

    public function setOutput(OutputInterface $output)
    {
        $this->io = new ConsoleIO(new StringInput(''), $output, new HelperSet());
    }

    private function getJson($json)
    {
        // @todo validate
        return new JsonFile($json, null, $this->io);
    }

    /**
     * @param RootPackageInterface $additionalPackage
     * @param string $dirname
     */
    private function mergeAutoload(RootPackageInterface $rootPackage, RootPackageInterface $additionalPackage, $dirname)
    {
        $rootAutoload = $rootPackage->getAutoload();
        $autoload = $additionalPackage->getAutoload();
        foreach (['psr-0', 'psr-4'] as $type) {
            if (! isset($autoload[$type])) {
                continue;
            }

            foreach ($autoload[$type] as $key => $path) {
                $path = $dirname.'/'.$path;
                if (isset($rootAutoload[$type][$key])) {
                    throw new \RuntimeException('Conflicting autoloading rules for key "'.$key.'"');
                }

                $rootAutoload[$type][$key] = $path;
            }
        }

        $rootPackage->setAutoload($rootAutoload);
    }
}
