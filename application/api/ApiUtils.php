<?php

/**
 * Class ApiUtils
 *
 * Utility functions for the API.
 */
class ApiUtils
{
    /**
     * Extract the service method name in Api from the request.
     *
     * FixMe! The current system (httpmethodServicename) isn't enough (e.g. getLinks).
     *
     * @param string $requestType HTTP Method name.
     * @param string $query       API query parameter in $_GET.
     *
     * @return string Service method name.
     */
    public static function getMethod($requestType, $query)
    {
        $parts = explode('/', $query);
        $request = ! empty($parts[1]) ? $parts[1] : '';
        return strtolower($requestType) . ucfirst($request);
    }

    /**
     * Extract the path parameters from the API query.
     * Anything after the service name is a path parameter.
     *
     * @param string $query API query parameter in $_GET.
     *
     * @return array List of path parameters, or an empty array if none is provided.
     */
    public static function getPathParameters($query)
    {
        $parts = explode('/', $query);
        $request = ! empty($parts[1]) ? $parts[1] : '';
        return array_slice($parts, 2);
    }

    /**
     * Create an API formatted link object from an internal link object.
     *
     * @param array $link Link in LinkDB format.
     *
     * @return array API formatted link.
     */
    public static function formatLink($link) {
        if (empty($link['linkdate'])) {
            return array();
        }
        if (! empty($link['tags'])) {
            $tags = preg_split('/\s+/', $link['tags'], 0, PREG_SPLIT_NO_EMPTY);
        }
        $created = DateTime::createFromFormat(LinkDB::LINK_DATE_FORMAT, $link['linkdate']);

        return array(
            'id' => $link['linkdate'],
            'url' => !empty($link['url']) ? $link['url'] : '',
            'title' => !empty($link['title']) ? $link['title'] : '',
            'description' => !empty($link['description']) ? $link['description'] : '',
            'tags' => ! empty($tags) ? $tags : array(),
            'private' => !empty($link['private']) ? $link['private'] : false,
            'created' => $created->format(DateTime::ISO8601),
            'updated' => '',
        );
    }

    /**
     * Convert a link given through a request, to a valid link for LinkDB.
     *
     * If no URL is provided, it will generate a local note URL.
     * If no title is provided, it will use the URL as title.
     *
     * @param string $linkdate       Link identifier (link date).
     * @param array  $input          Request Link.
     * @param bool   $defaultPrivate Request Link.
     *
     * @return array Formatted link.
     */
    public static function buildLinkFromRequest($linkdate, $input, $defaultPrivate)
    {
        if (empty($input['url'])) {
            $input['url'] = '?' . smallHash($linkdate);;
        }

        $link = array(
            'linkdate'      => $linkdate,
            'title'         => !empty($input['title']) ? $input['title'] : $input['url'],
            'url'           => $input['url'],
            'description'   => !empty($input['description']) ? $input['description'] : '',
            'tags'          => !empty($input['tags']) ? implode(' ', $input['tags']) : '',
            'private'       => isset($input['private']) ? $input['private'] === true : $defaultPrivate,
        );

        return $link;
    }

    /**
     * Create a default error array for the API.
     *
     * @param int    $code    Error code, usually the HTTP code.
     * @param string $message Error message.
     *
     * @return array Formatted error.
     */
    public static function formatError($code, $message)
    {
        return array(
            'code' => $code,
            'message' => $message,
        );
    }

    /**
     * Set response HTTP code.
     * Uses a different method for PHP 5.3 because `http_response_code()` isn't available.
     *
     * @param int $code HTTP code.
     */
    public static function setHttpCode($code) {
        if (function_exists('http_response_code')) {
            http_response_code($code);
        } else {
            // PHP 5.3 fallback
            header('X-PHP-Response-Code: '. $code, true, $code);
        }
    }

    /**
     * Validates a JWT token authenticity.
     *
     * @param string $token  JWT token extracted from the headers.
     * @param string $secret API secret set in the settings.
     *
     * @throws ApiAuthorizationException the token is not valid.
     */
    public static function validateJwtToken($token, $secret)
    {
        $parts = explode('.', $token);
        if (count($parts) != 3 || strlen($parts[0]) == 0 || strlen($parts[1]) == 0) {
            throw new ApiAuthorizationException('Malformed JWT token');
        }

        $genSign = hash_hmac('sha512', $parts[0] .'.'. $parts[1], $secret);
        if ($parts[2] != $genSign) {
            throw new ApiAuthorizationException('Invalid JWT signature');
        }

        $header = json_decode(base64_decode($parts[0]));
        if ($header === null) {
            throw new ApiAuthorizationException('Invalid JWT header');
        }

        $payload = json_decode(base64_decode($parts[1]));
        if ($payload === null) {
            throw new ApiAuthorizationException('Invalid JWT payload');
        }

        if (empty($payload->iat)
            || $payload->iat > time()
            || time() - $payload->iat > Api::$TOKEN_DURATION
        ) {
            throw new ApiAuthorizationException('Invalid JWT issued time');
        }
    }

    /**
     * Display an API response in JSON format.
     *
     * @param ApiResponse $response API Response to be displayed.
     */
    public static function render($response)
    {
        ApiUtils::setHttpCode($response->getCode());
        foreach ($response->getHeaders() as $header) {
            header($header);
        }
        header('Content-Type: application/json');
        $body = $response->getBody();
        if (!empty($body)) {
            echo json_encode($body);
        }
    }

    /**
     * Parse the JSON request body into an array.
     *
     * @param string $body HTTP request body as a string.
     *
     * @return array|null Parsed JSON array or null if the body is empty.
     *
     * @throws ApiBadParametersException An error occurred while trying to parse the JSON.
     */
    public static function parseRequestBody($body)
    {
        if (empty($body)) {
            return null;
        }
        $body = json_decode($body, true);
        if (json_last_error() != JSON_ERROR_NONE) {
            // PHP 5.5
            if (function_exists('json_last_error_msg')) {
                $error = json_last_error_msg();
            } else {
                $error = 'JSON error #' . json_last_error();
            }
            throw new ApiBadParametersException('Invalid request content: ' . $error);
        }
        return $body;
    }
}
