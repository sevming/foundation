<?php

namespace Sevming\Foundation;

use Pimple\Container;

/**
 * Class Foundation.
 *
 * @property \Sevming\Support\Config                            $config
 * @property \Sevming\Foundation\Providers\Cache\Cache          $cache
 * @property \Sevming\Foundation\Providers\Log\Log              $log
 * @property \Sevming\Foundation\Providers\Http\Http            $http
 * @property \Symfony\Component\HttpFoundation\Request          $request
 * @property \Symfony\Component\EventDispatcher\EventDispatcher $events
 */
class Foundation extends Container
{
    /**
     * @var array
     */
    protected $userConfig;

    /**
     * @var array
     */
    protected $providers = [];

    /**
     * Constructor.
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        parent::__construct();
        $this->userConfig = $config;
        $this->registerProviders($this->getProviders());
    }

    /**
     * Get config.
     *
     * @return mixed
     */
    public function getConfig()
    {
        return $this->userConfig;
    }

    /**
     * Register providers.
     *
     * @param array $providers
     */
    public function registerProviders(array $providers)
    {
        foreach ($providers as $provider) {
            parent::register(new $provider());
        }
    }

    /**
     * Return all providers.
     *
     * @return array
     */
    public function getProviders()
    {
        return array_merge([
            Providers\Config\ServiceProvider::class,
            Providers\Cache\ServiceProvider::class,
            Providers\Log\ServiceProvider::class,
            Providers\Http\ServiceProvider::class,
            Providers\Request\ServiceProvider::class,
            Providers\EventDispatcher\ServiceProvider::class
        ], $this->providers);
    }

    /**
     * Rebind.
     *
     * @param string $id
     * @param mixed  $value
     */
    public function rebind($id, $value)
    {
        $this->offsetUnset($id);
        $this->offsetSet($id, $value);
    }

    /**
     * Magic get access.
     *
     * @param string $id
     *
     * @return mixed
     */
    public function __get($id)
    {
        return $this->offsetGet($id);
    }

    /**
     * Magic set access.
     *
     * @param string $id
     * @param mixed  $value
     */
    public function __set($id, $value)
    {
        $this->offsetSet($id, $value);
    }
}
