<?php

namespace Smf\HttpServer;

use Smf\Routing\Router;

class SmfServer extends BasicServer
{
    public function __construct(string $workDir, array $settings = [])
    {
        parent::__construct($workDir, function () {
            return new SmfHttpHandler(new Router);
        }, $settings);
    }
}