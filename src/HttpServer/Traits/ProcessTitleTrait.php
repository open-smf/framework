<?php

namespace Smf\HttpServer\Traits;

trait ProcessTitleTrait
{
    public function setProcessTitle(string $title)
    {
        if (PHP_OS === 'Darwin') {
            return;
        }
        if (function_exists('cli_set_process_title')) {
            cli_set_process_title($title);
        } elseif (function_exists('swoole_set_process_name')) {
            swoole_set_process_name($title);
        }
    }
}