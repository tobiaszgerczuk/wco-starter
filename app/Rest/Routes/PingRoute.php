<?php

namespace WCO\Starter\Rest\Routes;

use WCO\Starter\Rest\BaseRoute;
use WCO\Starter\Rest\Contracts\RouteInterface;
use WP_REST_Response;

class PingRoute extends BaseRoute implements RouteInterface
{
    public static function register(): void
    {
        self::register_route('/ping', [
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'callback' => static fn() => new WP_REST_Response([
                'ok' => true,
                'message' => 'pong',
            ], 200),
        ]);
    }
}
