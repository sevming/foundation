<h1 align="left">Foundation</h1>

## Requirement   
1. PHP >= 7.2
2. [Composer](https://getcomposer.org/)


## Installing
```shell
$ composer require sevming/foundation -vvv
```

## Usage
> 基本使用
```php
<?php

use Sevming\Foundation\Foundation;

$config = [];
$foundation = new Foundation($config);
```

> [Cache](https://www.doctrine-project.org/projects/doctrine-cache/en/1.10/index.html) 
```php
<?php

use Sevming\Foundation\Foundation;

/*** 默认使用 Filesystem 缓存驱动 ***/ 

// Filesystem
$config = [
    'cache' => [        
        'driver' => 'filesystem',        
        'dir' => '/foundation',
    ]   
];

// Redis
$redis = new Redis();
$redis->connect('127.0.0.1');
$redis->auth('123456');
$config = [
    'cache' => [        
        'driver' => 'redis',        
        'client' => $redis,
    ]   
];

// 自定义缓存驱动
$config = [
    'cache' => [
        'driver' => 'custom'
    ]
];
$foundation = new Foundation($config);
$foundation->cache->extend('custom', function ($app, $config) {
    exit('自定义缓存驱动');
});
$driver = $foundation->cache->driver();
```

> Log
```php
<?php

use Sevming\Foundation\Foundation;

// 基本配置
$config = [
    'log' => [
        'default' => 'dev', // 默认使用的 channel
        'channels' => [
            // 测试环境
            'dev' => [
                'driver' => 'single',
                'path' => '/foundation.log',
                'level' => 'debug',
            ],
            // 生产环境
            'prod' => [
                'driver' => 'daily',
                'path' => '/foundation.log',
                'level' => 'info',
            ],
        ],
    ],
];
$foundation = new Foundation($config);
$foundation->log->debug('test');

// 自定义日志驱动
$config = [
    'log' => [
        'default' => 'chrome',
        'channels' => [
            'chrome' => [
                'driver' => 'chrome',
            ]
        ],
    ],
];
$foundation = new Foundation($config);
$foundation->log->extend('chrome', function ($app, $config) {
    $chromePHP = new \Monolog\Handler\ChromePHPHandler();
    $logger = new \Monolog\Logger('foundation');
    $logger->pushHandler($chromePHP);

    return $logger;
});
$foundation->log->debug('gg');
```

> http - 基本使用
```php
<?php

use Sevming\Foundation\Foundation;

$config = [
    'response_type' => 'collection', // 指定 Response 格式
    'http' => [
        'base_uri' => 'http://sev.test.com/'
    ]
];
$foundation = new Foundation($config);
$response = $foundation->http->request('test.php');

// 当请求结束后,默认会调度(触发) HttpResponseCreated 事件,监听配置
class HttpResponseCreatedEvent
{
    /**
     * Response created event.
     *
     * @param \Sevming\Foundation\Events\HttpResponseCreated $event
     *
     * @throws Exception
     */
    public function onResponseCreatedEvent(\Sevming\Foundation\Events\HttpResponseCreated $event)
    {
        var_dump($event);
        exit;
    }
}

$config = [
    'http' => [
        'base_uri' => 'http://sev.test.com/'
    ],
    'events' => [
        'listen' => [
            \Sevming\Foundation\Events\HttpResponseCreated::class => [
                [
                    new HttpResponseCreatedEvent(),
                    'onResponseCreatedEvent'
                ]
            ]
        ]
    ],
];
$response = $foundation->http->request('test.php');
```

> http - middleware
```php
<?php

use Psr\Http\Message\{RequestInterface, ResponseInterface};
use GuzzleHttp\Exception\GuzzleException;
use Sevming\Support\Collection;
use Sevming\Foundation\Foundation;
use Sevming\Foundation\Supports\Response;
use Sevming\Foundation\Exceptions\InvalidConfigException;

class Service extends Foundation
{
    public function __construct(array $config = [])
    {
        parent::__construct($config);
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
     * @throws GuzzleException
     * @throws InvalidConfigException
     */
    public function request(string $url, string $method = 'GET', array $options = [], bool $returnRaw = false)
    {
        $this->http->pushMiddleware($this->retryMiddleware(), 'retry');
        $this->http->pushMiddleware($this->tokenMiddleware(), 'token');

        return $this->http->request($url, $method, $options, $returnRaw);
    }

    /**
     * Get token.
     *
     * @param bool $refresh
     *
     * @return string
     * @throws Exception
     */
    public function getToken(bool $refresh = false)
    {
        $cache = $this->cache->driver();
        $tokenKey = 'token';
        $token = $cache->fetch($tokenKey);
        if ($token && !$refresh) {
            return $token;
        }

        // 模拟无效请求(首次测试后,需删除TOKEN缓存文件)
        $token = !$refresh ? 'invalidToken' : 'effectiveToken';
        $cache->save($tokenKey, $token, 10);

        return $token;
    }

    /**
     * Retry middleware.
     *
     * @return Closure
     */
    public function retryMiddleware()
    {
        return \GuzzleHttp\Middleware::retry(function (
            $retries,
            RequestInterface $request,
            ResponseInterface $response = null,
            Exception $e = null
        ) {
            if ($retries < 1 && $response && $body = $response->getBody()) {
                $response = Response::resolveData($response);
                if ($response->get('code') != 10000) {
                    $this->log->debug('Retrying with refreshed token.');
                    $this->getToken(true);

                    return true;
                }
            }

            return false;
        });
    }

    /**
     * Token middleware.
     *
     * @return Closure
     */
    public function tokenMiddleware()
    {
        return function (callable $handler) {
            return function (RequestInterface $request, array $options) use ($handler) {
                $token = $this->getToken();
                // Header add token
                $request = $request->withHeader('token', $token);
                // Query add token
                parse_str($request->getUri()->getQuery(), $query);
                $query = http_build_query(array_merge([
                    'token' => $token,
                ], $query));
                $request = $request->withUri($request->getUri()->withQuery($query));

                return $handler($request, $options);
            };
        };
    }
}

try {
    $config = [
        'http' => [
            'base_uri' => 'http://sev.test.com/'
        ],
        'log' => [
            'default' => 'dev',
            'channels' => [
                'dev' => [
                    'driver' => 'single',
                    'path' => '/foundation.log',
                    'level' => 'debug',
                ],
            ],
        ],
        'cache' => [
            'driver' => 'filesystem',
            'dir' => '/foundation',
        ]
    ];
    $service = new Service($config);
    $response = $service->request('test.php', 'GET', [
        'nickname' => '测试',
    ]);
} catch (Throwable $t) {
    exit("{$t->getFile()} - {$t->getLine()} - {$t->getMessage()}");
}
```
```php
<?php

// test.php

$token = $_GET['token'] ?? '';
$data = [
    'code' => $token === 'effectiveToken' ? 10000 : 20000,
    'msg' => '',
    'data' => [
        'get' => $_GET,
        'post' => $_POST,
        'input' => file_get_contents('php://input'),
        'server' => $_SERVER,
    ]
];

header("Content-type:application/json;charset=utf8");
echo json_encode($data);
```


## Contributing

You can contribute in one of three ways:

1. File bug reports using the [issue tracker](https://github.com/sevming/foundation/issues).
2. Answer questions or fix bugs on the [issue tracker](https://github.com/sevming/foundation/issues).
3. Contribute new features or update the wiki.

_The code contribution process is not very formal. You just need to make sure that you follow the PSR-0, PSR-1, and PSR-2 coding guidelines. Any new code contributions must be accompanied by unit tests where applicable._

## License

MIT