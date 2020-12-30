<?php

namespace Sevming\Foundation\Providers\Log;

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
        $pimple['log'] = function ($app) {
            $config = $this->formatLogConfig($app->config);
            if (!empty($config)) {
                $app->rebind('config', $app->config->merge($config));
            }

            return new Log($app);
        };
    }

    /**
     * @param Config $config
     *
     * @return array|array[]
     */
    private function formatLogConfig(Config $config)
    {
        if (!empty($config->get('log.channels'))) {
            return [];
        }

        if (empty($config->get('log'))) {
            return [
                'log' => [
                    'default' => 'errorlog',
                    'channels' => [
                        'errorlog' => [
                            'driver' => 'errorlog',
                            'level' => 'debug',
                        ],
                    ],
                ],
            ];
        }

        return [
            'log' => [
                'default' => 'single',
                'channels' => [
                    'single' => [
                        'driver' => 'single',
                        'path' => $config->get('log.file', \sys_get_temp_dir() . "/logs/sevfoundation.log"),
                        'level' => $config->get('log.level', 'debug'),
                    ],
                ],
            ],
        ];
    }
}
