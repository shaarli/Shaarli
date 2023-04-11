<?php

namespace Shaarli\Api\Exceptions;

use Slim\Http\Response;
use Slim\Psr7\Response as SlimResponse;

/**
 * Abstract class ApiException
 *
 * Parent Exception related to the API, able to generate a valid Response (ResponseInterface).
 * Also can include various information in debug mode.
 */
abstract class ApiException extends \Exception
{
    /**
     * @var Response instance from Slim.
     */
    protected $response;

    /**
     * @var bool Debug mode enabled/disabled.
     */
    protected $debug;

    /**
     * Build the final response.
     *
     * @return Response Final response to give.
     */
    abstract public function getApiResponse();

    /**
     * Creates ApiResponse body.
     * In production mode, it will only return the exception message,
     * but in dev mode, it includes additional information in an array.
     *
     * @return array|string response body
     */
    protected function getApiResponseBody()
    {
        if ($this->debug !== true) {
            return $this->getMessage();
        }
        return [
            'message' => $this->getMessage(),
            'stacktrace' => get_class($this) . ': ' . $this->getTraceAsString()
        ];
    }

    /**
     * Build the Response object to return.
     *
     * @param int $code HTTP status.
     *
     * @return Response with status + body.
     */
    protected function buildApiResponse($code)
    {
        $style = $this->debug ? JSON_PRETTY_PRINT : null;
        $response = $this->response ?? new SlimResponse();
        $response->getBody()->write(json_encode($this->getApiResponseBody(), $style));
        return $response->withStatus($code);
    }

    /**
     * @param Response $response
     */
    public function setResponse($response)
    {
        $this->response = $response;
    }

    /**
     * @param bool $debug
     */
    public function setDebug($debug)
    {
        $this->debug = $debug;
    }
}
