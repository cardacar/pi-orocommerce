<?php

namespace Oro\Component\Routing\Resolver;

use Symfony\Component\Routing\Route;

interface RouteOptionsResolverInterface
{
    /**
     * Performs the route modifications based on its options
     */
    public function resolve(Route $route, RouteCollectionAccessor $routes);
}
