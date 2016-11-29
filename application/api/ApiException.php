<?php

/**
 * Class ApiException
 *
 * Parent Exception related to the API, able to generate a valid ApiResponse.
 * Also can include various information in debug mode.
 */
abstract class ApiException extends Exception {
    /**
     * @var bool Debug mode enabled/disabled.
     */
    protected $debug;

    /**
     * @var array $_SERVER
     */
    protected $server;

    /**
     * @var array List of request parameters
     */
    protected $get;

    /**
     * @var array List of request headers
     */
    protected $headers;

    /**
     * @var string Body content.
     */
    protected $body;

    /**
     * Generate a valid ApiResponse for the exception.
     *
     * @return ApiResponse
     */
    public abstract function getApiResponse();

    /**
     * Creates ApiResponse body.
     * In production mode, it will only return the exception message,
     * but in dev mode, it includes additional information in an array.
     *
     * @return array|string response body
     */
    protected function getApiResponseBody() {
        if ($this->debug !== true) {
            return $this->getMessage();
        }
        return array(
            $this->getMessage(),
            $this->server,
            $this->headers,
            $this->get,
            $this->body,
            $this->getTraceAsString()
        );
    }

    /**
     * @param bool $debug
     */
    public function setDebug($debug)
    {
        $this->debug = $debug;
    }

    /**
     * @param array $server
     */
    public function setServer($server)
    {
        $this->server = $server;
    }

    /**
     * @param array $get
     */
    public function setGet($get)
    {
        $this->get = $get;
    }

    /**
     * @param array $headers
     */
    public function setHeaders($headers)
    {
        $this->headers = $headers;
    }

    /**
     * @param string $body
     */
    public function setBody($body)
    {
        $this->body = $body;
    }
}

/**
 * Class ApiInternalException
 *
 * Generic exception, return a 500 HTTP code.
 */
class ApiInternalException extends ApiException
{
    /**
     * @inheritdoc
     */
    public function getApiResponse()
    {
        $error = ApiUtils::formatError(500, $this->getApiResponseBody());
        return new ApiResponse(500, array(), $error);
    }
}

/**
 * Class ApiAuthorizationException
 *
 * Request not authorized, return a 401 HTTP code.
 */
class ApiAuthorizationException extends ApiException
{
    /**
     * {@inheritdoc}
     */
    public function getApiResponse()
    {
        $this->setMessage('Not authorized');
        $error = ApiUtils::formatError(401, $this->getApiResponseBody());
        return new ApiResponse(401, array(), $error);
    }

    /**
     * Set the exception message.
     *
     * We only return a generic error message in production mode to avoid giving
     * to much security information.
     *
     * @param $message string the exception message.
     */
    public function setMessage($message)
    {
        $original = $this->debug === true ? ': '. $this->getMessage() : '';
        $this->message = $message . $original;
    }
}

/**
 * Class ApiBadParametersException
 *
 * Invalid request exception, return a 400 HTTP code.
 */
class ApiBadParametersException extends ApiException
{
    /**
     * {@inheritdoc}
     */
    public function getApiResponse()
    {
        $error = ApiUtils::formatError(400, $this->getApiResponseBody());
        return new ApiResponse(400, array(), $error);
    }
}
