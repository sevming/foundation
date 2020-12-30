<?php

namespace Sevming\Foundation\Providers\Cache;

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
        $pimple['cache'] = function ($app) {
            $config = $this->formatCacheConfig($app->config);
            if (!empty($config)) {
                $app->rebind('config', $app->config->merge($config));
            }

            return new Cache($app);
        };
    }

    /**
     * @param Config $config
     *
     * @return array|array[]
     */
    private function formatCacheConfig(Config $config)
    {
        if (!empty($config->get('cache.driver'))) {
            return [];
        }

        return [
            'cache' => [
                'driver' => 'filesystem',
                'dir' => $config->get('cache.dir', \sys_get_temp_dir() . '/sevfoundation'),
            ]
        ];
    }
}
