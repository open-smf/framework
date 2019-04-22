<?php

namespace Smf\Routing;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use Smf\Exceptions\HttpException;
use Smf\Routing\Traits\RequestTrait;
use Swoole\Http\Request;
use function FastRoute\simpleDispatcher;

class Router
{
    use RequestTrait;

    protected $dispatcher;

    public function __construct()
    {
        $this->dispatcher = simpleDispatcher(function (RouteCollector $r) {
            // TODO
            $r->addRoute('GET', '/users', 'get_all_users_handler');
            // {id} must be a number (\d+)
            $r->addRoute('GET', '/user/{id:\d+}', 'get_user_handler');
            // The /{title} suffix is optional
            $r->addRoute('GET', '/articles/{id:\d+}[/{title}]', 'get_article_handler');
        });
    }

    public function parse(Request $request)
    {
        $method = $this->getRequestMethod($request);
        $uri = $this->getPathInfo($request);
        $route = $this->dispatcher->dispatch($method, $uri);
        switch ($route[0]) {
            case Dispatcher::NOT_FOUND:
                throw new HttpException('Not Found', 404);
            case Dispatcher::METHOD_NOT_ALLOWED:
                throw new HttpException('Method Not Allowed', 405);
            case Dispatcher::FOUND:
                if (is_string($route[1]) && false !== $pos = strpos($route[1], '@')) {
                    return ['controller', substr($route[1], 0, $pos), substr($route[1], $pos + 1), $route[2]];
                }
                if (is_callable($route[1])) {
                    return ['callable', $route[1], $route[2]];
                }
                throw new \RuntimeException('Invalid route: ' . json_encode($route));
            default:
                throw new \RuntimeException('Invalid dispatch result: ' . json_encode($route));
        }
    }
}