<?php

namespace Sevming\Foundation\Providers\Cache;

use \Exception;
use \Closure;
use Doctrine\Common\Cache\{CacheProvider, FilesystemCache, RedisCache};
use Sevming\Foundation\Foundation;
use Sevming\Foundation\Exceptions\InvalidArgumentException;

/**
 * Class Cache
 *
 * @method fetch($id)
 * @method contains($id)
 * @method save($id, $data, $lifeTime = 0)
 * @method delete($id)
 * @method getStats()
 */
class Cache
{
    /**
     * @var Foundation
     */
    protected $app;

    /**
     * The array of resolved drivers.
     *
     * @var array
     */
    protected $drivers = [];

    /**
     * The registered custom driver creators.
     *
     * @var array
     */
    protected $customCreators = [];

    /**
     * Constructor.
     *
     * @param Foundation $app
     */
    public function __construct(Foundation $app)
    {
        $this->app = $app;
    }

    /**
     * Get a cache driver instance.
     *
     * @param string|null $driver
     *
     * @return mixed
     * @throws Exception
     */
    public function driver(?string $driver = null)
    {
        return $this->get($driver ?? $this->getDefaultDriver());
    }

    /**
     * Get the default cache driver name.
     *
     * @return string
     */
    public function getDefaultDriver()
    {
        return $this->app->config->get('cache.driver');
    }

    /**
     * Register a custom driver creator Closure.
     *
     * @param string  $driver
     * @param Closure $callback
     *
     * @return $this
     */
    public function extend(string $driver, Closure $callback)
    {
        $this->customCreators[$driver] = $callback->bindTo($this, $this);
        return $this;
    }

    /**
     * Attempt to get the cache from the local cache.
     *
     * @param string $name
     *
     * @return CacheProvider
     * @throws InvalidArgumentException
     */
    protected function get(string $name)
    {
        return $this->drivers[$name] ?? ($this->drivers[$name] = $this->resolve());
    }

    /**
     * Resolve the given cache instance by name.
     *
     * @return CacheProvider
     * @throws InvalidArgumentException
     */
    protected function resolve()
    {
        $config = $this->app->config->get('cache');
        if (isset($this->customCreators[$config['driver']])) {
            return $this->callCustomCreator($config);
        }

        $driverMethod = 'create' . ucfirst($config['driver']) . 'Driver';
        if (method_exists($this, $driverMethod)) {
            return $this->{$driverMethod}($config);
        }

        throw new InvalidArgumentException(\sprintf('INVALID ARGUMENT: Driver [%s] is not supported.', $config['driver']));
    }

    /**
     * Call a custom driver creator.
     *
     * @param array $config
     *
     * @return mixed
     */
    protected function callCustomCreator(array $config)
    {
        return $this->customCreators[$config['driver']]($this->app, $config);
    }

    /**
     * Create an instance of the filesystem cache driver.
     *
     * @param array $config
     *
     * @return CacheProvider
     */
    protected function createFilesystemDriver(array $config)
    {
        return new FilesystemCache(
            $config['dir'],
            $config['extension'] ?? FilesystemCache::EXTENSION,
            $config['umask'] ?? 0002
        );
    }

    /**
     * Create an instance of the redis cache driver.
     *
     * @param array $config
     *
     * @return CacheProvider
     * @throws InvalidArgumentException
     */
    protected function createRedisDriver(array $config)
    {
        if (!(($config['client'] ?? null) instanceof \Redis)) {
            throw new InvalidArgumentException('INVALID ARGUMENT: Client must instantiate redis.');
        }

        $cache = new RedisCache();
        $cache->setRedis($config['client']);

        return $cache;
    }

    /**
     * Dynamically call the default driver instance.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @return mixed
     * @throws Exception
     */
    public function __call($method, $parameters)
    {
        return $this->driver()->$method(...$parameters);
    }
}
