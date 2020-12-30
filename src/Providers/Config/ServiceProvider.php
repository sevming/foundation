<?php

namespace Sevming\Foundation\Providers\Config;

use Pimple\{Container, ServiceProviderInterface};
use Sevming\Support\Config;

class ServiceProvider implements ServiceProviderInterface
{
    /**
     * {@inheritDoc}
     *
     * @param Container $pimple
     */
    public function register(Container $pimple)
    {
        $pimple['config'] = function ($app) {
            return new Config($app->getConfig());
        };
    }
}
