<?php

namespace Fazland\Rabbitd;

use Fazland\Rabbitd\Config\MasterConfig;
use Fazland\Rabbitd\Config\QueueConfig;
use Fazland\Rabbitd\OutputFormatter\MasterFormatter;
use Fazland\Rabbitd\Process\CurrentProcess;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessUtils;

class Application
{
    /**
     * @var InputInterface
     */
    private $input;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var bool
     */
    private $restart;

    /**
     * @var bool
     */
    private $running;

    /**
     * @var MasterConfig
     */
    private $config;

    /**
     * @var MasterFormatter
     */
    private $outputFormatter;

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var CurrentProcess
     */
    private $currentProcess;

    /**
     * @var Child[]
     */
    private $children;

    /**
     * Application constructor.
     *
     * @param CurrentProcess $currentProcess
     */
    public function __construct(CurrentProcess $currentProcess = null)
    {
        if (null === $currentProcess) {
            $currentProcess = new CurrentProcess();
        }

        $this->currentProcess = $currentProcess;

        try {
            $this->input = new ArgvInput($this->currentProcess->getArgv(), $this->getInputDefinition());
        } catch (\RuntimeException $ex) {
            echo $ex->getMessage()."\n";
            die(3);
        }

        $this->running = false;
        $this->outputFormatter = new MasterFormatter();
        $this->output = new StreamOutput(fopen('php://stdout', 'ab'), $this->config['verbosity'], null, $this->outputFormatter);
        $this->logger = new ConsoleLogger($this->output, [], [LogLevel::WARNING => 'comment']);

        $this->readConfig();
        $this->checkAlreadyInExecution();
    }

    public function run()
    {
        $this->running = true;
        $this->deamonize();

        $this->logger = new ConsoleLogger($this->output, [], [LogLevel::WARNING => 'comment']);
        $this->logger->info('Starting '.$this->currentProcess->getExecutableName().' with PID #'.$this->getProcess()->getPid());

        set_exception_handler(function ($e) {
            /** @var \Throwable $e */
            $this->logger->critical('Unhandled exception: '.$e->getMessage());
            $this->logger->critical('Stack trace');
            $this->logger->critical($e->getTraceAsString());
        });

        $processUser = posix_getpwuid(posix_getuid());
        $this->logger->debug("Currently executing as '{user}'", ['user' => $processUser['name']]);

        $this->installSignalHandlers();
        $this->initQueues();

        $i = 0;
        while ($this->running) {
            if ($i++ % 10 == 0) {
                $this->sanityCheck();
            }

            sleep(1);
        }

        $alive = true;

        while ($alive) {
            $alive = false;
            foreach ($this->children as $child) {
                $alive = $alive || $child->getProcess()->isAlive();
            }

            sleep(1);
        }

        @unlink($this->config['pid_file']);

        if ($this->restart) {
            $exec = (new PhpExecutableFinder())->find();

            $cmdline = array_map([ProcessUtils::class, 'escapeArgument'], [$exec, $this->getProcess()->getExecutableName()]);
            $cmdline[] = (string)$this->input;

            $this->logger->debug('Launching "'.implode(' ', $cmdline));

            $process = new Process(implode(' ', $cmdline));
            $process
                ->setEnv($_ENV)
                ->setTimeout(0)
                ->start()
            ;

            $time = 5;
            while ($time = sleep($time));

            if ($process->getStatus() !== Process::STATUS_STARTED && $process->getExitCode() !== 0) {
                $this->logger->critical('Cannot restart process: '.$process->getExitCodeText().' ('.$process->getExitCode().')');
                $this->logger->critical($process->getOutput());
            }
        }

        $this->logger->info('Finished #'.$this->currentProcess->getPid());
    }

    /**
     * @return OutputInterface
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * @return MasterConfig
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @return Process|CurrentProcess
     */
    public function getProcess()
    {
        return $this->currentProcess;
    }

    private function getInputDefinition()
    {
        $definition = new InputDefinition([
            new InputOption('config', 'c', InputOption::VALUE_REQUIRED, 'Set configuration file'),
        ]);

        return $definition;
    }

    private function deamonize()
    {
        if (! function_exists('pcntl_fork')) {
            throw new \RuntimeException('pcntl_* functions are not available, cannot continue');
        }

        // Double fork magic, to prevent deamon to acquire a tty
        if ($pid = $this->currentProcess->fork()) {
            exit;
        }

        $this->currentProcess->setSid();

        if ($pid = $this->currentProcess->fork()) {
            exit;
        }

        file_put_contents($this->config['pid_file'], $this->currentProcess->getPid());
        $this->redirectOutputs();
    }

    private function redirectOutputs()
    {
        @mkdir(dirname($this->config['log_file']), 0777, true);

        global $STDIN, $STDOUT, $STDERR;

        fclose(STDIN);
        $STDIN = fopen('/dev/null', 'r');

        fclose(STDOUT);
        $handle = fopen($this->config['log_file'], 'ab');       // This will be the new stdout since 1 is the lowest free file descriptor
        $STDOUT = $handle;

        fclose(STDERR);
        fopen($this->config['log_file'], 'ab');
        $STDERR = $STDOUT;

        $this->output = new StreamOutput($handle, $this->config['verbosity'], false, $this->outputFormatter);
    }

    private function readConfig()
    {
        if (null === ($file = $this->input->getOption('config'))) {
            $dir = isset($_ENV['CONF_DIR']) ? $_ENV['CONF_DIR'] : posix_getcwd().DIRECTORY_SEPARATOR.'conf';
            $file = $dir.DIRECTORY_SEPARATOR.'/rabbitd.yml';
        }

        $this->config = new MasterConfig($file);
    }

    private function installSignalHandlers()
    {
        $this->logger->debug('Installing signal handlers');

        $handler = function ($signo) {
            if (! $this->running) {
                return;
            }

            $this->running = false;
            $this->logger->info('Received '.($signo === SIGTERM ? 'TERM' : 'HUP').' signal. Stopping loop, process will shutdown after the current job has finished');
            $this->restart = $signo === SIGHUP;
            $this->signalTermination();
        };

        pcntl_signal(SIGTERM, $handler);
        pcntl_signal(SIGHUP, $handler);

        pcntl_signal(SIGCHLD, [$this, 'sanityCheck']);
    }

    private function signalTermination()
    {
        foreach ($this->children as $child) {
            $child->getProcess()->kill(SIGTERM);
        }
    }

    public function sanityCheck()
    {
        if (! $this->running) {
            return;
        }

        foreach ($this->children as $child) {
            if (! $child->getProcess()->isAlive()) {
                $child->restart($this);
            }
        }
    }

    private function checkAlreadyInExecution()
    {
        $pidFile = $this->config['pid_file'];
        $pid = file_exists($pidFile) ? (int)file_get_contents($pidFile) : null;

        if (! $pid) {
            return;
        }

        if (posix_kill($pid, 0)) {
            $this->logger->error("Rabbitd is already running with PID #$pid");
            die(2);
        }
    }

    private function initQueues()
    {
        foreach ($this->config['queues'] as $name => $options) {
            $config = new QueueConfig($options, $this->config['symfony.app']);

            for ($i = 0; $i < $config['processes']; ++$i) {
                $this->children[] = new Child($name.' #'.$i, $config, $this);
            }
        }
    }
}
