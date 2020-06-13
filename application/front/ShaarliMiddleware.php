<?php

namespace Shaarli\Front;

use Shaarli\Container\ShaarliContainer;
use Shaarli\Front\Exception\ShaarliFrontException;
use Shaarli\Front\Exception\UnauthorizedException;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class ShaarliMiddleware
 *
 * This will be called before accessing any Shaarli controller.
 */
class ShaarliMiddleware
{
    /** @var ShaarliContainer contains all Shaarli DI */
    protected $container;

    public function __construct(ShaarliContainer $container)
    {
        $this->container = $container;
    }

    /**
     * Middleware execution:
     *   - execute the controller
     *   - return the response
     *
     * In case of error, the error template will be displayed with the exception message.
     *
     * @param  Request  $request  Slim request
     * @param  Response $response Slim response
     * @param  callable $next     Next action
     *
     * @return Response response.
     */
    public function __invoke(Request $request, Response $response, callable $next)
    {
        $this->container->basePath = rtrim($request->getUri()->getBasePath(), '/');

        try {
            $response = $next($request, $response);
        } catch (ShaarliFrontException $e) {
            $this->container->pageBuilder->assign('message', $e->getMessage());
            if ($this->container->conf->get('dev.debug', false)) {
                $this->container->pageBuilder->assign(
                    'stacktrace',
                    nl2br(get_class($this) .': '. $e->getTraceAsString())
                );
            }

            $response = $response->withStatus($e->getCode());
            $response = $response->write($this->container->pageBuilder->render('error'));
        } catch (UnauthorizedException $e) {
            return $response->withRedirect($this->container->basePath . '/login');
        }

        return $response;
    }
}
