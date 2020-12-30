<?php

namespace Sevming\Foundation\Providers\Http;

use \Closure;
use Psr\Log\LogLevel;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\{Client, ClientInterface, HandlerStack, Exception\GuzzleException, MessageFormatter, Middleware};
use Sevming\Support\Collection;
use Sevming\Foundation\Foundation;
use Sevming\Foundation\Supports\Response;
use Sevming\Foundation\Events\HttpResponseCreated;
use Sevming\Foundation\Exceptions\InvalidConfigException;

class Http
{
    /**
     * @var Foundation
     */
    protected $app;

    /**
     * @var ClientInterface
     */
    protected $httpClient;

    /**
     * @var array
     */
    protected $middlewares = [];

    /**
     * @var HandlerStack
     */
    protected $handlerStack;

    /**
     * @var array
     */
    protected $defaults = [
        'curl' => [
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        ],
    ];

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
     * Set guzzle default settings.
     *
     * @param array $defaults
     *
     * @return $this
     */
    public function setDefaultOptions(array $defaults = [])
    {
        $this->defaults = $defaults;
        return $this;
    }

    /**
     * Return current guzzle default settings.
     *
     * @return array
     */
    public function getDefaultOptions(): array
    {
        return $this->defaults;
    }

    /**
     * Get http client.
     *
     * @return ClientInterface
     */
    public function getHttpClient(): ClientInterface
    {
        if (!($this->httpClient instanceof ClientInterface)) {
            $this->httpClient = new Client(array_merge([
                'timeout' => 30.0,
            ], $this->app->config->get('http', [])));
        }

        return $this->httpClient;
    }

    /**
     * Set http client.
     *
     * @param ClientInterface $httpClient
     *
     * @return $this
     */
    public function setHttpClient(ClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
        return $this;
    }

    /**
     * Return all middlewares.
     *
     * @return array
     */
    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }

    /**
     * Add a middleware.
     *
     * @param callable $middleware
     * @param string   $name
     *
     * @return $this
     */
    public function pushMiddleware(callable $middleware, string $name = null)
    {
        if (!is_null($name)) {
            $this->middlewares[$name] = $middleware;
        } else {
            array_push($this->middlewares, $middleware);
        }

        return $this;
    }

    /**
     * Build a handler stack.
     *
     * @return HandlerStack
     */
    public function getHandlerStack()
    {
        if ($this->handlerStack) {
            return $this->handlerStack;
        }

        $this->handlerStack = HandlerStack::create(\GuzzleHttp\choose_handler());
        foreach ($this->middlewares as $name => $middleware) {
            $this->handlerStack->push($middleware, $name);
        }

        if (true === $this->app->config->get('http.log', true)) {
            $this->handlerStack->push($this->logMiddleware(), 'log');
        }

        return $this->handlerStack;
    }

    /**
     * Set handle stack.
     *
     * @param HandlerStack $handlerStack
     *
     * @return $this
     */
    public function setHandlerStack(HandlerStack $handlerStack)
    {
        $this->handlerStack = $handlerStack;
        return $this;
    }

    /**
     * GET request.
     *
     * @param string $url
     * @param array  $query
     *
     * @return string|array|object|Collection|ResponseInterface
     * @throws GuzzleException|InvalidConfigException
     */
    public function get(string $url, array $query = [])
    {
        return $this->request($url, 'GET', ['query' => $query]);
    }

    /**
     * POST request.
     *
     * @param string $url
     * @param array  $data
     *
     * @return string|array|object|Collection|ResponseInterface
     * @throws GuzzleException|InvalidConfigException
     */
    public function post(string $url, array $data = [])
    {
        return $this->request($url, 'POST', ['form_params' => $data]);
    }

    /**
     * JSON request.
     *
     * @param string $url
     * @param array  $data
     * @param array  $query
     *
     * @return string|array|object|Collection|ResponseInterface
     * @throws GuzzleException|InvalidConfigException
     */
    public function json(string $url, array $data = [], array $query = [])
    {
        return $this->request($url, 'POST', ['query' => $query, 'json' => $data]);
    }

    /**
     * Upload file.
     *
     * @param string $url
     * @param array  $files
     * @param array  $form
     * @param array  $query
     *
     * @return string|array|object|Collection|ResponseInterface
     * @throws GuzzleException|InvalidConfigException
     */
    public function upload(string $url, array $files = [], array $form = [], array $query = [])
    {
        $multipart = [];
        foreach ($files as $name => $path) {
            if (is_array($path)) {
                foreach ($path as $item) {
                    $multipart[] = ['name' => $name . '[]'] + $this->fileToMultipart($item);
                }
            } else {
                $multipart[] = ['name' => $name] + $this->fileToMultipart($path);
            }
        }

        foreach ($form as $name => $contents) {
            $multipart = array_merge($multipart, $this->normalizeMultipartField($name, $contents));
        }

        return $this->request(
            $url,
            'POST',
            [
                'query' => $query,
                'multipart' => $multipart,
                'connect_timeout' => 30,
                'timeout' => 30,
                'read_timeout' => 30
            ]
        );
    }

    /**
     * Request.
     *
     * @param string $url
     * @param string $method
     * @param array  $options
     * @param bool   $returnRaw
     *
     * @return string|array|object|Collection|ResponseInterface
     * @throws GuzzleException|InvalidConfigException
     */
    public function request(string $url, string $method = 'GET', array $options = [], bool $returnRaw = false)
    {
        $method = strtoupper($method);
        $options = array_merge($this->defaults, $options, ['handler' => $this->getHandlerStack()]);
        $options = $this->fixJsonIssue($options);
        $response = $this->getHttpClient()->request($method, $url, $options);
        $response->getBody()->rewind();
        $this->app->events->dispatch(new HttpResponseCreated($response));

        return $returnRaw ? $response : Response::resolveData($response, $this->app->config->get('response_type'));
    }

    /**
     * Fix json issue.
     *
     * @param array $options
     *
     * @return array
     */
    protected function fixJsonIssue(array $options)
    {
        if (isset($options['json']) && is_array($options['json'])) {
            $options['headers'] = array_merge($options['headers'] ?? [], ['Content-Type' => 'application/json']);
            if (empty($options['json'])) {
                $options['body'] = \GuzzleHttp\json_encode($options['json'], JSON_FORCE_OBJECT);
            } else {
                $options['body'] = \GuzzleHttp\json_encode($options['json'], JSON_UNESCAPED_UNICODE);
            }

            unset($options['json']);
        }

        return $options;
    }

    /**
     * Normalize multipart field.
     *
     * @param string $name
     * @param mixed  $contents
     *
     * @return array
     */
    protected function normalizeMultipartField(string $name, $contents)
    {
        if (!is_array($contents)) {
            return [compact('name', 'contents')];
        }

        $field = [];
        foreach ($contents as $key => $value) {
            $key = \sprintf('%s[%s]', $name, $key);
            $field = array_merge($field, is_array($value) ? $this->normalizeMultipartField($key, $value) : [
                [
                    'name' => $key,
                    'contents' => $value
                ]
            ]);
        }

        return $field;
    }

    /**
     * File to multipart.
     *
     * @param $file
     *
     * @return array
     */
    protected function fileToMultipart($file)
    {
        if (is_array($file)) {
            return $file;
        } elseif (@file_exists($file)) {
            return ['contents' => fopen($file, 'r')];
        } elseif (filter_var($file, FILTER_VALIDATE_URL)) {
            return ['contents' => file_get_contents($file)];
        } else {
            return ['contents' => $file];
        }
    }

    /**
     * Log middleware.
     *
     * @return Closure
     */
    protected function logMiddleware()
    {
        $formatter = new MessageFormatter($this->app->config->get('http.log_template', "Request >>>>>>>>\n{request}\n<<<<<<<< Response\n{response}\n--------\nError {error}\n"));
        return Middleware::log($this->app->log, $formatter, LogLevel::DEBUG);
    }
}
