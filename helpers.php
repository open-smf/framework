<?php

function kill(int $pid, int $sig)
{
    try {
        return Swoole\Process::kill($pid, $sig);
    } catch (\Exception $e) {
        return false;
    }
}