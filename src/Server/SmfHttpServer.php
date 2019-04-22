<?php

namespace Smf\Server;

use Smf\Routing\Router;

class SmfHttpServer extends HttpServer
{
    public function __construct(string $workDir, array $settings = [])
    {
        parent::__construct($workDir, function () {
            return new SmfHttpHandler(new Router);
        }, $settings);
    }
}