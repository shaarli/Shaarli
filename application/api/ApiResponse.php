<?php

/**
 * Class ApiResponse
 *
 * Bean containing the elements of an HTTP response.
 */
class ApiResponse
{
    /**
     * @var int HTTP code.
     */
    protected $code;

    /**
     * @var array List of HTTP headers.
     */
    protected $headers;

    /**
     * @var mixed Body content in its PHP form (will be serialized to readable data, such as JSON).
     */
    protected $body;

    /**
     * ApiResponse constructor.
     *
     * @param $code
     * @param $headers
     * @param $body
     */
    public function __construct($code, $headers, $body)
    {
        $this->code = $code;
        $this->headers = $headers;
        $this->body = $body;
    }

    /**
     * @return int
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @param int $code
     */
    public function setCode($code)
    {
        $this->code = $code;
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @param array $headers
     */
    public function setHeaders($headers)
    {
        $this->headers = $headers;
    }

    /**
     * @return mixed
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * @param mixed $body
     */
    public function setBody($body)
    {
        $this->body = $body;
    }
}
