<?php

namespace WCO\Starter\Rest;

abstract class BaseRoute
{
    protected const NAMESPACE = 'wco-starter/v1';

    protected static function register_route(string $route, array $args): void
    {
        register_rest_route(self::NAMESPACE, $route, $args);
    }
}
