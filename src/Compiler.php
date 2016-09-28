<?php

namespace Fazland\Rabbitd;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\PhpExecutableFinder;

class Compiler
{
    /**
     * @var \Phar
     */
    private $phar;

    /**
     * @var string
     */
    private $file;

    public function __construct()
    {
        $this->file = __DIR__.'/../build/rabbitd.phar';
        $this->phar = new \Phar($this->file, 0, 'rabbitd.phar');
    }

    public function compile()
    {
        $phpExecutable = (new PhpExecutableFinder())->find();

        $composer = $this->findComposer();
        exec($phpExecutable.' '.$composer.' config autoloader-suffix RabbitdPhar');
        exec($phpExecutable.' '.$composer.' install --no-dev --no-interaction -o');
        exec($phpExecutable.' '.$composer.' config autoloader-suffix --unset');

        $this->phar->startBuffering();

        $finder = Finder::create()
            ->files()
            ->in('src')
            ->in('vendor')
            ->notPath('#Tests?#iu')
        ;

        foreach ($finder as $file) {
            $this->addFile($file);
        }

        $this->addFile(new \SplFileInfo(__DIR__.'/../composer.json'));
        $this->addFile(new \SplFileInfo(__DIR__.'/../main.php'));

        $stub = <<<EOF
#!/usr/bin/env php
<?php

Phar::mapPhar('rabbitd.phar');
require 'phar://rabbitd.phar/main.php';

__HALT_COMPILER();

EOF;

        $this->phar->setStub($stub);
        $this->phar->compressFiles(\Phar::BZ2);

        $this->phar->stopBuffering();

        chmod($this->file, 0754);
    }

    private function findComposer()
    {
        return __DIR__.'/../vendor/bin/composer';
    }

    private function addFile(\SplFileInfo $file)
    {
        $path = strtr(str_replace(dirname(__DIR__).DIRECTORY_SEPARATOR, '', $file->getRealPath()), '\\', '/');
        echo 'Adding "'.$path."\"...\n";
        $content = file_get_contents($file);

        $this->phar->addFromString($path, $content);
    }
}
