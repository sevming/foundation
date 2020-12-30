<?php

namespace Sevming\Foundation\Supports;

use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Psr7\Response as GuzzleHttpResponse;
use Sevming\Support\{Collection, Xml};
use Sevming\Foundation\Exceptions\{InvalidArgumentException, InvalidConfigException};

class Response extends GuzzleHttpResponse
{
    /**
     * Constructor.
     *
     * @param ResponseInterface $response
     */
    public function __construct(ResponseInterface $response)
    {
        parent::__construct(
            $response->getStatusCode(),
            $response->getHeaders(),
            $response->getBody(),
            $response->getProtocolVersion(),
            $response->getReasonPhrase()
        );
    }

    /**
     * Resolve data.
     *
     * @param ResponseInterface $response
     * @param string|null       $type
     *
     * @return string|array|object|Collection|ResponseInterface
     * @throws InvalidConfigException
     */
    public static function resolveData(ResponseInterface $response, ?string $type = null)
    {
        $response = new self($response);
        switch ($type ?? 'collection') {
            case 'string':
                return $response->getBodyContents();
            case 'array':
                return $response->toArray();
            case 'json':
                return $response->toJson();
            case 'object':
                return $response->toObject();
            case 'collection':
                return $response->toCollection();
            case 'raw':
                return $response;
            default:
                throw new InvalidConfigException(\sprintf('INVALID CONFIG: Unsupported conversion to "%s"', $type));
        }
    }

    /**
     * Detect and convert data.
     *
     * @param mixed       $response
     * @param string|null $type
     *
     * @return string|array|object|Collection|ResponseInterface
     * @throws InvalidArgumentException|InvalidConfigException
     */
    public static function detectAndConvertData($response, ?string $type = null)
    {
        switch (true) {
            case $response instanceof ResponseInterface:
                $response = new GuzzleHttpResponse(
                    $response->getStatusCode(),
                    $response->getHeaders(),
                    $response->getBody(),
                    $response->getProtocolVersion(),
                    $response->getReasonPhrase()
                );
                break;
            case ($response instanceof Collection) || is_array($response) || is_object($response):
                $response = new GuzzleHttpResponse(200, [], \json_encode($response));
                break;
            case is_scalar($response):
                $response = new GuzzleHttpResponse(200, [], (string)$response);
                break;
            default:
                throw new InvalidArgumentException(\sprintf('INVALID ARGUMENT：Unsupported response type "%s"', gettype($response)));
        }

        return self::resolveData($response, $type);
    }

    /**
     * Get body contents.
     *
     * @return string
     */
    public function getBodyContents()
    {
        $this->getBody()->rewind();
        $contents = $this->getBody()->getContents();
        $this->getBody()->rewind();

        return $contents;
    }

    /**
     * Build to array.
     *
     * @return array
     */
    public function toArray()
    {
        $contents = $this->removeControlCharacters($this->getBodyContents());
        if (false !== stripos($this->getHeaderLine('Content-Type'), 'xml') || 0 === stripos($contents, '<xml')) {
            return Xml::parse($contents);
        }

        $array = json_decode($contents, true, 512, JSON_BIGINT_AS_STRING);
        if (JSON_ERROR_NONE === json_last_error()) {
            return (array)$array;
        }

        return [];
    }

    /**
     * Build to collection.
     *
     * @return Collection
     */
    public function toCollection()
    {
        return new Collection($this->toArray());
    }

    /**
     * Build to json.
     *
     * @return string
     */
    public function toJson()
    {
        return \json_encode($this->toArray());
    }

    /**
     * Build to object.
     *
     * @return object
     */
    public function toObject()
    {
        return json_decode($this->toJson());
    }

    /**
     * @param string $content
     *
     * @return string
     */
    protected function removeControlCharacters(string $content)
    {
        return \preg_replace('/[\x00-\x1F\x80-\x9F]/u', '', $content);
    }
}
