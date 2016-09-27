<?php

namespace Fazland\Rabbitd\Util;

class ClassUtils
{
    public static function getClassName($file_contents)
    {
        $tokens = token_get_all($file_contents);

        $namespace = '';
        $class = null;
        $lastToken = null;

        for ($i = 0; isset($tokens[$i]); ++$i) {
            $token = $tokens[$i];
            if (! is_array($token)) {
                $lastToken = null;
                continue;
            }

            if (T_WHITESPACE == $token[0]) {
                continue;
            }

            if (T_NAMESPACE === $lastToken) {
                while (isset($tokens[$i]) && is_array($token = $tokens[$i]) && in_array($token[0], [T_NS_SEPARATOR, T_STRING])) {
                    $namespace .= $token[1];
                    ++$i;
                }
            }

            if (T_CLASS === $lastToken && T_STRING === $token[0]) {
                $class = $namespace.'\\'.$token[1];
                break;
            }

            $lastToken = $token[0];
        }

        if (PHP_VERSION_ID >= 70000) {
            // PHP 7 memory manager will not release after token_get_all(), see https://bugs.php.net/70098
            unset($tokens);
            gc_mem_caches();
        }

        return ltrim($class, '\\');
    }
}
