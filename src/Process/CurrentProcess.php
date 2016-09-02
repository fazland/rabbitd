<?php

namespace Fazland\Rabbitd\Process;

class CurrentProcess extends Process
{
    /**
     * @var array
     */
    private $argv;

    /**
     * @var string
     */
    private $executable;

    public function __construct()
    {
        parent::__construct(getmypid());

        $this->argv = $_SERVER['argv'];
        $this->executable = reset($this->argv);
    }

    /**
     * @return array
     */
    public function getArgv()
    {
        return $this->argv;
    }

    /**
     * @return string
     */
    public function getExecutableName()
    {
        return $this->executable;
    }

    /**
     * @return string
     */
    public function getExecutablePath()
    {
        return dirname($this->executable);
    }

    public function fork()
    {
        $pid = pcntl_fork();

        if ($pid === 0) {
            $this->pid = getmypid();
        }

        return $pid;
    }

    public function setSid()
    {
        return posix_setsid();
    }
}