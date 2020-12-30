<?php

namespace Sevming\Foundation\Exceptions;

use Psr\Http\Message\ResponseInterface;

class HttpException extends Exception
{
    /**
     * @var ResponseInterface|null
     */
    public $response;

    /**
     * Constructor.
     *
     * @param string                 $message
     * @param int|null               $code
     * @param ResponseInterface|null $response
     */
    public function __construct(string $message, ?int $code = null, ResponseInterface $response = null)
    {
        parent::__construct($message, $code);
        $this->response = $response;
        if ($response) {
            $response->getBody()->rewind();
        }
    }
}
