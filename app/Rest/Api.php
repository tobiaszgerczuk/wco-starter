<?php

namespace WCO\Starter\Rest;

use WCO\Starter\Rest\Contracts\RouteInterface;
use WCO\Starter\Rest\Routes\CartRoute;
use WCO\Starter\Rest\Routes\PingRoute;
use WCO\Starter\Rest\Routes\PostsRoute;

class Api
{
    public static function boot(): void
    {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function register_routes(): void
    {
        foreach (self::route_classes() as $routeClass) {
            if (!is_a($routeClass, RouteInterface::class, true)) {
                continue;
            }

            $routeClass::register();
        }
    }

    /**
     * Central list of route classes.
     * Add new endpoint classes here.
     *
     * @return array<class-string<RouteInterface>>
     */
    private static function route_classes(): array
    {
        return [
            PingRoute::class,
            CartRoute::class,
            PostsRoute::class,
        ];
    }
}
