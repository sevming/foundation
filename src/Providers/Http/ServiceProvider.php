<?php

namespace Sevming\Foundation\Providers\Http;

use Pimple\{Container, ServiceProviderInterface};

class ServiceProvider implements ServiceProviderInterface
{
    /**
     * {@inheritDoc}
     *
     * @param Container $pimple
     */
    public function register(Container $pimple)
    {
        $pimple['http'] = function ($app) {
            return new Http($app);
        };
    }
}
