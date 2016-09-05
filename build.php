#!/usr/bin/env php
<?php

if (ini_get('phar.readonly')) {
    echo "PHAR readonly flag is set in PHP.ini. Please disable it!\n";
    die;
}

function findComposer($name)
{
    if (ini_get('open_basedir')) {
        $searchPath = explode(PATH_SEPARATOR, ini_get('open_basedir'));
        $dirs = [];
        foreach ($searchPath as $path) {
            // Silencing against https://bugs.php.net/69240
            if (@is_dir($path)) {
                $dirs[] = $path;
            } else {
                if (basename($path) == $name && is_executable($path)) {
                    return $path;
                }
            }
        }
    } else {
        $dirs = explode(PATH_SEPARATOR, getenv('PATH') ?: getenv('Path'));
    }

    foreach ($dirs as $dir) {
        if (is_file($file = $dir.DIRECTORY_SEPARATOR.$name) && ('\\' === DIRECTORY_SEPARATOR || is_executable($file))) {
            return $file;
        }
    }

    return null;
}

$composer = findComposer('composer') or $composer = findComposer('composer.phar');
exec($composer.' install --no-dev --no-interaction');

$p = new Phar(__DIR__.'/build/rabbitd.phar', FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::KEY_AS_FILENAME, 'rabbitd.phar');
$p->buildFromDirectory(__DIR__, '/^.+\.php$/');
unset($p['build.php']);

$p->setStub(Phar::createDefaultStub('main.php'));
