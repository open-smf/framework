<?php

namespace Smf\Routing\Traits;

use Swoole\Http\Response;

trait ResponseTrait
{
    public static $chunkLimit = 2097152; // 2M

    public function sendHeaders(Response $response, array $headers)
    {
        foreach ($headers as $key => $value) {
            $response->header($key, $value);
        }
    }

    public function sendContent(Response $response, $content)
    {
        $len = strlen($content);
        if ($len === 0) {
            $response->end();
            return;
        }

        if ($len > self::$chunkLimit) {
            for ($i = 0, $limit = 1024 * 1024; $i < $len; $i += $limit) {
                $chunk = substr($content, $i, $limit);
                $response->write($chunk);
            }
            $response->end();
        } else {
            $response->end($content);
        }
    }
}