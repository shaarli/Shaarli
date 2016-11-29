<?php

require_once 'ApiResponse.php';
require_once 'ApiException.php';

/**
 * Class Api
 *
 * Shaarli's API V1. This class validates and processes API request, and returns an ApiResponse.
 */
class Api
{
    /**
     * @var int Number of item returned by default.
     */
    public static $DEFAULT_LIMIT = 20;

    /**
     * @var int JWT token validity in seconds.
     */
    public static $TOKEN_DURATION = 540;

    /**
     * @var ConfigManager instance.
     */
    protected $conf;

    /**
     * @var LinkDB instance.
     */
    protected $linkDB;

    /**
     * @var PluginManager instance.
     */
    protected $pluginManager;

    /**
     * @var array List of allowed service methods.
     */
    protected static $allowedMethod = array(
        'getInfo',
    );

    /**
     * Api constructor.
     *
     * @param $conf          ConfigManager instance.
     * @param $linkDB        LinkDB        instance.
     * @param $pluginManager PluginManager instnace.
     */
    public function __construct(&$conf, $linkDB, $pluginManager)
    {
        $this->conf = $conf;
        $this->linkDB = $linkDB;
        /*
           FIXME!
           This is a workaround to load private links even though the user is not logged in Shaarli.
           We need to refactor how links are loaded and rendered (also needed for other features).
        */
        $this->linkDB->setLoggedIn(true);
        $this->linkDB->refresh();
        $this->pluginManager = $pluginManager;
    }

    /**
     * Service request processor:
     *   - Validates the request (token, generic parameters, etc.)
     *   - Calls the appropriate service with formatted parameters.
     *   - Returns the ApiResponse processed by the service, created due to an error.
     *
     * @param $server  array  $_SERVER.
     * @param $headers array  List of all request headers (must include `jwt`).
     * @param $get     array  $_GET.
     * @param $body    string Request body content as a string.
     *
     * @return ApiResponse
     */
    public function call($server, $headers, $get, $body)
    {
        try {
            $this->checkRequest($server, $headers, $get);
            $pathParams = ApiUtils::getPathParameters($get['q']);
            $method = ApiUtils::getMethod($server['REQUEST_METHOD'], $get['q']);
            if (! in_array($method, static::$allowedMethod)) {
                throw new ApiAuthorizationException('Method "'. $method .'"" is not allowed');
            }

            $body = ApiUtils::parseRequestBody($body);
            $response = $this->$method($get, $pathParams, $body);
            if (! $response instanceof ApiResponse) {
                throw new ApiInternalException('Couldn\'t build the response');
            }
            return $response;
        } catch (Exception $e) {
            if (! $e instanceof ApiException) {
                $e = new ApiInternalException($e->getMessage(), 500, $e);
            }

            $e->setDebug($this->conf->get('dev.debug'));
            $e->setServer($server);
            $e->setHeaders($headers);
            $e->setGet($get);
            $e->setBody($body);
            return $e->getApiResponse();
        }
    }

    /**
     * Produces link counters and a few useful settings.
     *
     * @return array Information about Shaarli's instance.
     */
    public function getInfo()
    {
        $info = array(
            'global_counter' => count($this->linkDB),
            'private_counter' => count_private($this->linkDB),
            'settings' => array(
                'title' => $this->conf->get('general.title', 'Shaarli'),
                'header_link' => $this->conf->get('general.header_link', '?'),
                'timezone' => $this->conf->get('general.timezone', 'UTC'),
                'enabled_plugins' => $this->conf->get('general.enabled_plugins', array()),
                'default_private_links' => $this->conf->get('privacy.default_private_links', false),
            ),
        );
        return new ApiResponse(200, array(), $info);
    }

    /**
     * Check the request validity (HTTP method, request value, etc.),
     * that the API is enabled, and the JWT token validity.
     *
     * @param array $server  $_SERVER array.
     * @param array $headers All request headers.
     * @param array $get     Request parameters.
     *
     * @throws ApiAuthorizationException The API is disabled or the token is invalid.
     * @throws ApiBadParametersException Invalid request.
     */
    protected function checkRequest($server, $headers, $get)
    {
        if (! $this->conf->get('api.enabled', true)) {
            throw new ApiAuthorizationException('API is disabled');
        }
        if (empty($get['q']) || empty($server['REQUEST_METHOD'])) {
            throw new ApiBadParametersException('Invalid API call');
        }
        $this->checkToken($headers);
    }

    /**
     * Check that the JWT token is set and valid.
     * The API secret setting must be set.
     *
     * @param array $headers HTTP headers.
     *
     * @throws ApiAuthorizationException The token couldn't be validated.
     */
    protected function checkToken($headers) {
        if (empty($headers['jwt'])) {
            throw new ApiAuthorizationException('JWT token not provided');
        }

        $secret = $this->conf->get('api.secret');
        if (empty($secret)) {
            throw new ApiAuthorizationException('Token secret must be set in Shaarli\'s administration');
        }

        ApiUtils::validateJwtToken($headers['jwt'], $this->conf->get('api.secret'));
    }

    /**
     * @param ConfigManager $conf
     */
    public function setConf($conf)
    {
        $this->conf = $conf;
    }
}
