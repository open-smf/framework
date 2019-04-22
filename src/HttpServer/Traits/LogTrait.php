<?php

namespace Smf\HttpServer\Traits;

trait LogTrait
{
    public function logException(\Throwable $e, string $extra = '')
    {
        $this->log(
            sprintf(
                'Uncaught exception \'%s\': [%d]%s %s called in %s:%d%s%s',
                get_class($e),
                $e->getCode(),
                $e->getMessage(),
                isset($extra[0]) ? "[{$extra}]" : '',
                $e->getFile(),
                $e->getLine(),
                PHP_EOL,
                $e->getTraceAsString()
            ),
            'ERROR'
        );
    }

    public function log(string $msg, string $type = 'INFO')
    {
        echo sprintf('[%s] [%s] Swoole: %s%s', date('Y-m-d H:i:s'), $type, $msg, PHP_EOL);
    }

    public function callWithCatchException(callable $callback)
    {
        try {
            return $callback();
        } catch (\Throwable $e) {
            $this->logException($e);
            return false;
        }
    }
}