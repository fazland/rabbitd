<?php

namespace Fazland\Rabbitd\Composer;

use Composer\Config;
use Composer\Factory;
use Composer\Installer;
use Composer\IO\ConsoleIO;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Package\Loader\RootPackageLoader;
use Composer\Package\RootPackageInterface;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Composer
{
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

    /**
     * @var string
     */
    private $rootPath;

    public function __construct($rootUri, $rootPath)
    {
        ini_set('memory_limit', '2G');
        $this->rootUri = $rootUri;
        $this->rootPath = $rootPath;

        $this->packages = [];
    }

    public function resolve(InputInterface $input, OutputInterface $output, HelperSet $helperSet)
    {
        $oldCwd = posix_getcwd();
        chdir($this->rootPath);

        $factory = new Factory();
        $config = $factory->createConfig();

        $this->rootFile = $this->getJson(__DIR__.'/../../composer.json');
        $jsonConfigSource = new Config\JsonConfigSource($this->rootFile);

        $config->setConfigSource($jsonConfigSource);
        $config->merge($this->rootFile->read());
        $config->merge([
            'config' => ['autoloader-suffix' => md5(uniqid('', true))],
        ]);

        $io = new ConsoleIO($input, $output, $helperSet);
        $composer = $factory->createComposer($io, $config->all());
        $rm = $composer->getRepositoryManager();

        $loader = new RootPackageLoader($rm, $config);
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
            $jsonFile = $this->getJson($file, $io);
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

        $installer = Installer::create($io, $composer);
        $installer->setUpdate();

        $installer->run();

        chdir($oldCwd);
    }

    public function addPackage($composerJson)
    {
        $this->packages[] = $composerJson;
    }

    private function getJson($json, IOInterface $io = null)
    {
        // @todo validate
        return new JsonFile($json, null, $io);
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
