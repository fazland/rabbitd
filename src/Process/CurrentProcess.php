<?php

namespace Fazland\Rabbitd\Process;

use Fazland\Rabbitd\Util\Silencer;

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

    public function setProcessTitle($title)
    {
        return Silencer::call('cli_set_process_title', $title);
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

    public function setUser($userName)
    {
        if ($userInfo = posix_getpwnam($userName)) {
            posix_setuid($userInfo['uid']);
        }

        return $this;
    }

    public function setGroup($groupName)
    {
        if ($groupInfo = posix_getgrnam($groupName)) {
            posix_setgid($groupInfo['gid']);
        }

        return $this;
    }
}
