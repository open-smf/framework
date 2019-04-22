<?php

namespace Smf\HttpServer;

use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;

interface HttpHandlerInterface
{
    public function onRequest(Server $server, Request $request, Response $response);
}