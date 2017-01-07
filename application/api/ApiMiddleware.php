<?php

namespace Shaarli\Api;

use Shaarli\Api\Exceptions\ApiException;
use Shaarli\Api\Exceptions\ApiAuthorizationException;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class ApiMiddleware
 *
 * This will be called before accessing any API Controller.
 * Its role is to make sure that the API is enabled, configured, and to validate the JWT token.
 *
 * If the request is validated, the controller is called, otherwise a JSON error response is returned.
 *
 * @package Api
 */
class ApiMiddleware
{
    /**
     * @var int JWT token validity in seconds (9 min).
     */
    public static $TOKEN_DURATION = 540;

    /**
     * @var Container: contains conf, plugins, etc.
     */
    protected $container;

    /**
     * @var \ConfigManager instance.
     */
    protected $conf;

    /**
     * ApiMiddleware constructor.
     *
     * @param Container $container instance.
     */
    public function __construct($container)
    {
        $this->container = $container;
        $this->conf = $this->container->get('conf');
        $this->setLinkDb($this->conf);
    }

    /**
     * Middleware execution:
     *   - check the API request
     *   - execute the controller
     *   - return the response
     *
     * @param  Request  $request  Slim request
     * @param  Response $response Slim response
     * @param  callable $next     Next action
     *
     * @return Response response.
     */
    public function __invoke($request, $response, $next)
    {
        try {
            $this->checkRequest($request);
            $response = $next($request, $response);
        } catch(ApiException $e) {
            $e->setResponse($response);
            $e->setDebug($this->conf->get('dev.debug', false));
            $response = $e->getApiResponse();
        }

        return $response;
    }

    /**
     * Check the request validity (HTTP method, request value, etc.),
     * that the API is enabled, and the JWT token validity.
     *
     * @param  Request $request  Slim request
     *
     * @throws ApiAuthorizationException The API is disabled or the token is invalid.
     */
    protected function checkRequest($request)
    {
        if (! $this->conf->get('api.enabled', true)) {
            throw new ApiAuthorizationException('API is disabled');
        }
        $this->checkToken($request);
    }

    /**
     * Check that the JWT token is set and valid.
     * The API secret setting must be set.
     *
     * @param  Request $request  Slim request
     *
     * @throws ApiAuthorizationException The token couldn't be validated.
     */
    protected function checkToken($request) {
        if (! $request->hasHeader('Authorization')) {
            throw new ApiAuthorizationException('JWT token not provided');
        }

        if (empty($this->conf->get('api.secret'))) {
            throw new ApiAuthorizationException('Token secret must be set in Shaarli\'s administration');
        }

        $authorization = $request->getHeaderLine('Authorization');

        if (! preg_match('/^Bearer (.*)/i', $authorization, $matches)) {
            throw new ApiAuthorizationException('Invalid JWT header');
        }

        ApiUtils::validateJwtToken($matches[1], $this->conf->get('api.secret'));
    }

    /**
     * Instantiate a new LinkDB including private links,
     * and load in the Slim container.
     *
     * FIXME! LinkDB could use a refactoring to avoid this trick.
     *
     * @param \ConfigManager $conf instance.
     */
    protected function setLinkDb($conf)
    {
        $linkDb = new \LinkDB(
            $conf->get('resource.datastore'),
            true,
            $conf->get('privacy.hide_public_links'),
            $conf->get('redirector.url'),
            $conf->get('redirector.encode_url')
        );
        $this->container['db'] = $linkDb;
    }
}
