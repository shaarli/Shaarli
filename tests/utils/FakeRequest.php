<?php

namespace Shaarli\Tests\Utils;

use Psr\Http\Message\UriInterface;
use Slim\Psr7\Headers;
use Slim\Psr7\Interfaces\HeadersInterface;
use Slim\Psr7\Request;
use Slim\Psr7\Stream;
use Slim\Psr7\Uri;

class FakeRequest extends Request
{
    public function __construct(
        $method = 'GET',
        UriInterface $uri = null,
        HeadersInterface $headers = null,
        array $cookies = [],
        array $serverParams = [],
        string $body = '',
        array $uploadedFiles = []
    ) {
        $uri = $uri ?? new Uri('', '');
        $bodyStream = new Stream(fopen(sprintf('data://text/plain,%s', $body), 'r'));
        $headers = $headers ?? new Headers([]);
        parent::__construct(
            $method,
            $uri,
            $headers,
            $cookies,
            $serverParams,
            $bodyStream,
            $uploadedFiles
        );
    }

    public function withServerParams(array $serverParams): FakeRequest
    {
        $this->serverParams = $serverParams;
        return $this;
    }
}
