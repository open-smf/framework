<?php

namespace Smf\HttpServer;

use Smf\Routing\Router;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;

class SmfHttpHandler implements HttpHandlerInterface
{
    protected $router;

    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    public function onRequest(Server $server, Request $request, Response $response)
    {
        $route = $this->router->parse($request);
        // TODO
        $response->end('Route: ' . print_r($route, true));
    }

}