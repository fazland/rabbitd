<?php

namespace Fazland\Rabbitd;

use Fazland\Rabbitd\Config\MasterConfig;
use Fazland\Rabbitd\Console\Environment;
use Fazland\Rabbitd\Exception\RestartException;
use Fazland\Rabbitd\Process\CurrentProcess;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\Output;
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
     * @var MasterConfig
     */
    private $config;

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var CurrentProcess
     */
    private $currentProcess;

    /**
     * @var ErrorHandler
     */
    private $errorHandler;

    /**
     * @var Environment
     */
    private $environment;

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
        $this->environment = Environment::createFromGlobal();

        try {
            $this->input = new ArgvInput($this->currentProcess->getArgv(), $this->getInputDefinition());
        } catch (\RuntimeException $ex) {
            echo $ex->getMessage()."\n";
            die(3);
        }

        $this->output = new StreamOutput(fopen('php://stdout', 'ab'), Output::VERBOSITY_VERY_VERBOSE);
        $this->logger = new ConsoleLogger($this->output, [], [LogLevel::WARNING => 'comment']);

        $this->readConfig();
        $this->checkAlreadyInExecution();
    }

    public function run()
    {
        $this->deamonize();

        $this->currentProcess
            ->setUser($this->config['master.user'])
            ->setGroup($this->config['master.group']);

        $master = new Master($this->config, clone $this->output, $this->currentProcess);

        $this->logger->info('Starting '.$this->currentProcess->getExecutableName().' with PID #'.$this->currentProcess->getPid());
        $this->errorHandler = new ErrorHandler($this->logger);

        try {
            $master->run();
        } catch (RestartException $e) {
            $this->restart();
        }

        $this->logger->info('Finished #'.$this->currentProcess->getPid());
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

        $this->output = new StreamOutput($handle, $this->config['verbosity'], false);
        $this->logger = new ConsoleLogger($this->output, [], [LogLevel::WARNING => 'comment']);
    }

    private function readConfig()
    {
        if (null === ($file = $this->input->getOption('config'))) {
            $dir = $this->environment->get('CONF_DIR', posix_getcwd().DIRECTORY_SEPARATOR.'conf');
            $file = $dir.DIRECTORY_SEPARATOR.'/rabbitd.yml';
        }

        $this->config = new MasterConfig($file, $this->environment);
    }

    private function restart()
    {
        $exec = (new PhpExecutableFinder())->find();

        $cmdline = array_map([ProcessUtils::class, 'escapeArgument'], [$exec, $this->currentProcess->getExecutableName()]);
        $cmdline[] = (string)$this->input;

        $this->logger->debug('Launching "'.implode(' ', $cmdline));

        $commandline = '{ ('.implode(' ', $cmdline).') <&3 3<&- 3>/dev/null & } 3<&0;';
        exec($commandline, $output, $exitcode);

        $time = 5;
        while ($time = sleep($time));

        if ($exitcode !== 0) {
            $text = isset(Process::$exitCodes[$exitcode]) ? Process::$exitCodes[$exitcode] : 'Unknown error';
            $this->logger->critical('Cannot restart process: '.$text.' ('.$exitcode.')');
            $this->logger->critical($output);
        }
    }
}
