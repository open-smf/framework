<?php

namespace Smf\Routing\Traits;

use Swoole\Http\Request;

trait RequestTrait
{
    public function getPathInfo(Request $request): string
    {
        // Keep the same as $_SERVER['PATH_INFO']
        $path = $request->server['path_info'] ?? '/';
        return '/' . trim($path, '/');
    }

    public function getRequestUri(Request $request): string
    {
        // Keep the same as $_SERVER['REQUEST_URI'], $_SERVER['REQUEST_URI'] has query string
        $path = $this->getPathInfo($request);
        $query = $request->server['query_string'] ?? '';
        if (isset($query[0])) {
            $path .= '?' . $query;
        }
        return $path;
    }

    public function getRequestMethod(Request $request): string
    {
        return $request->server['request_method'] ?? 'GET';
    }
}