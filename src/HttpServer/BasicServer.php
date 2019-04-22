<?php

namespace Smf\HttpServer;

use Smf\HttpServer\Traits\LogTrait;
use Smf\HttpServer\Traits\ProcessTitleTrait;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;
use Swoole\Process;

class BasicServer
{
    use LogTrait;
    use ProcessTitleTrait;

    /**@var string The work directory */
    protected $workDir;

    /**@var callable Resolver of http handler */
    protected $httpHandlerResolver;

    /**@var HttpHandlerInterface */
    protected $httpHandler;

    /**@var Server The swoole http server instance */
    protected $swoole;

    /**@var array Default swoole settings */
    protected $defaultSetting = [
        'daemonize'             => false,
        'dispatch_mode'         => 1,
        'max_request'           => 10000,
        'open_tcp_nodelay'      => true,
        'reload_async'          => true,
        'max_wait_time'         => 60,
        'enable_reuse_port'     => true,
        'enable_coroutine'      => true,
        'http_compression'      => false,
        'enable_static_handler' => false,
        'buffer_output_size'    => 4 * 1024 * 1024,
        'log_level'             => 4,
    ];

    /**@var array Settings of Swoole */
    protected $settings = [];

    /** @var []callable */
    protected $workerStartEvents = [];
    /** @var []callable */
    protected $workerStopEvents = [];
    /** @var []callable */
    protected $workerExitEvents = [];
    /** @var []callable */
    protected $workerErrorEvents = [];

    public function __construct(string $workDir, callable $httpHandlerResolver, array $settings = [])
    {
        $this->workDir = $workDir;
        $this->httpHandlerResolver = $httpHandlerResolver;
        $this->settings = array_merge($this->defaultSetting, [
            'reactor_num' => swoole_cpu_num() * 2,
            'worker_num'  => swoole_cpu_num() * 2,
        ], $settings);
    }

    public function init(string $ip, int $port)
    {
        $this->swoole = new Server($ip, $port);
        $this->swoole->set($this->settings);

        $this->bindBaseEvent();
        $this->bindHttpEvent();
    }

    public function getSwoole()
    {
        return $this->swoole;
    }

    public function start()
    {
        $this->swoole->start();
    }

    public function stop()
    {
        $pidFile = $this->settings['pid_file'];
        if (!file_exists($pidFile)) {
            $this->log('It seems that Swoole is not running.', 'WARN');
            return;
        }

        $pid = file_get_contents($pidFile);
        if (kill($pid, 0)) {
            if (kill($pid, SIGTERM)) {
                // Make sure that master process quit
                $time = 1;
                $waitTime = 60;
                while (kill($pid, 0)) {
                    if ($time > $waitTime) {
                        $this->log(
                            "PID[{$pid}] cannot be stopped gracefully in {$waitTime}s, will be stopped forced right now.",
                            'WARN'
                        );
                        return;
                    }
                    $this->log("Waiting PID[{$pid}] to stop. [{$time}]");
                    sleep(1);
                    $time++;
                }
                if (file_exists($pidFile)) {
                    unlink($pidFile);
                }
                $this->log("PID[{$pid}] is stopped.");
            } else {
                $this->log("PID[{$pid}] is stopped failed.", 'ERROR');
            }
        } else {
            $this->log("PID[{$pid}] does not exist, or permission denied.", 'ERROR');
            if (file_exists($pidFile)) {
                unlink($pidFile);
            }
        }
    }

    public function reload()
    {
        return $this->reloadByPidFile($this->settings['pid_file']);
    }

    public function reloadByPidFile(string $pidFile)
    {
        if (!file_exists($pidFile)) {
            $this->log(sprintf('PID file[%s] does not exist.', $pidFile), 'ERROR');
            return false;
        }

        $pid = file_get_contents($pidFile);
        if (!$pid || !kill($pid, 0)) {
            $this->log("PID[{$pid}] does not exist, or permission denied.", 'ERROR');
            return false;
        }

        if (kill($pid, SIGUSR1)) {
            $now = date('Y-m-d H:i:s');
            $this->log("PID[{$pid}] is reloaded at {$now}.");
            return true;
        } else {
            $this->log("PID[{$pid}] is reloaded failed.", 'ERROR');
            return false;
        }
    }

    protected function bindBaseEvent()
    {
        $this->swoole->on('Start', [$this, 'onStart']);
        $this->swoole->on('ManagerStart', [$this, 'onManagerStart']);
        $this->swoole->on('WorkerStart', [$this, 'onWorkerStart']);
        $this->swoole->on('WorkerStop', [$this, 'onWorkerStop']);
        $this->swoole->on('WorkerError', [$this, 'onWorkerError']);
    }

    protected function bindHttpEvent()
    {
        $this->swoole->on('Request', [$this, 'onRequest']);
    }

    public function onStart(Server $server)
    {
        $this->setProcessTitle(sprintf('%s swoole: master process', $this->workDir));
        $this->log(
            sprintf(
                'PID[%s] is listening at %s:%d.',
                file_get_contents($server->setting['pid_file']),
                $server->host,
                $server->port
            )
        );
    }

    public function addProcess($name, callable $callback, $redirectStdInOut = false, $pipeType = 0)
    {
        $process = new Process(
            function (Process $process) use ($name, $callback) {
                $this->setProcessTitle($name);
                call_user_func_array($callback, [$process, $this->swoole]);
            },
            $redirectStdInOut,
            $pipeType
        );
        $this->swoole->addProcess($process);
    }

    public function onManagerStart()
    {
        $this->setProcessTitle(sprintf('%s swoole: manager process', $this->workDir));
    }

    public function addWorkerStartEvent(callable $event)
    {
        $this->workerStartEvents[] = $event;
    }

    public function addWorkerStopEvent(callable $event)
    {
        $this->workerStopEvents[] = $event;
    }

    public function addWorkerExitEvent(callable $event)
    {
        $this->workerExitEvents[] = $event;
    }

    public function addWorkerErrorEvent(callable $event)
    {
        $this->workerErrorEvents[] = $event;
    }

    public function onWorkerStart(Server $server, $workerId)
    {
        $process = $workerId >= $server->setting['worker_num'] ? 'task worker' : 'worker';
        $this->setProcessTitle(sprintf('%s swoole: %s process %d', $this->workDir, $process, $workerId));
        function_exists('opcache_reset') AND opcache_reset();
        function_exists('apc_clear_cache') AND apc_clear_cache();
        clearstatcache();
        foreach ($this->workerStartEvents as $event) {
            call_user_func_array($event, array_merge([$this], func_get_args()));
        }
    }

    public function onWorkerStop(Server $server, $workerId)
    {
        foreach ($this->workerStopEvents as $event) {
            call_user_func_array($event, array_merge([$this], func_get_args()));
        }
    }

    public function onWorkerError(Server $server, $workerId, $workerPId, $exitCode, $signal)
    {
        $this->log(sprintf('worker[%d] error: exitCode=%s, signal=%s', $workerId, $exitCode, $signal), 'ERROR');
        foreach ($this->workerErrorEvents as $event) {
            call_user_func_array($event, array_merge([$this], func_get_args()));
        }
    }

    public function onRequest(Request $request, Response $response)
    {
        $this->getHttpHandler()->onRequest($this->swoole, $request, $response);
    }

    /**
     * @return HttpHandlerInterface
     */
    public function getHttpHandler(): HttpHandlerInterface
    {
        if (is_null($this->httpHandler)) {
            $this->httpHandler = ($this->httpHandlerResolver)();
        }
        return $this->httpHandler;
    }

}