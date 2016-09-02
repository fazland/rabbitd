<?php

namespace Fazland\Rabbitd\Process;

class Process
{
    /**
     * @var int
     */
    protected $pid;

    public function __construct($pid)
    {
        $this->pid = $pid;
    }

    /**
     * @return int
     */
    public function getPid()
    {
        return $this->pid;
    }

    /**
     * @param int $signal
     *
     * @return bool
     */
    public function kill($signal)
    {
        return posix_kill($this->pid, $signal);
    }

    /**
     * @return bool
     */
    public function isAlive()
    {
        pcntl_waitpid($this->pid, $status, WNOHANG|WUNTRACED);
        return ! pcntl_wifstopped($status) && ! pcntl_wifexited($status);
    }
}