<?php

namespace Sevming\Foundation\Providers\EventDispatcher;

use Pimple\{Container, ServiceProviderInterface};
use Symfony\Component\EventDispatcher\EventDispatcher;

class ServiceProvider implements ServiceProviderInterface
{
    /**
     * {@inheritDoc}
     *
     * @param Container $pimple
     */
    public function register(Container $pimple)
    {
        $pimple['events'] = function ($app) {
            $dispatcher = new EventDispatcher();
            foreach ($app->config->get('events.listen', []) as $event => $listeners) {
                foreach ($listeners as $listener) {
                    $dispatcher->addListener($event, $listener);
                }
            }

            return $dispatcher;
        };
    }
}
