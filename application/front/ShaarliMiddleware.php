<?php

namespace Shaarli\Front;

use Psr\Container\ContainerInterface as Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Shaarli\Front\Exception\UnauthorizedException;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Routing\RouteContext;

/**
 * Class ShaarliMiddleware
 *
 * This will be called before accessing any Shaarli controller.
 */
class ShaarliMiddleware
{
    /** @var Container contains all Shaarli DI */
    protected $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Middleware execution:
     *   - run updates
     *   - if not logged in open shaarli, redirect to login
     *   - execute the controller
     *   - return the response
     *
     * In case of error, the error template will be displayed with the exception message.
     *
     * @param Request $request Slim request
     * @param RequestHandler $handler
     * @return Response response.
     */
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $this->initBasePath($request);

        try {
            if (
                !is_file($this->container->get('conf')->getConfigFileExt())
                && !in_array($request
                    ->getAttribute(RouteContext::ROUTE)->getName(), ['displayInstall', 'saveInstall'], true)
            ) {
                return $this->redirect($this->container->get('basePath') . '/install');
            }

            $this->runUpdates();
            $this->checkOpenShaarli($request, $handler);

            return $handler->handle($request);
        } catch (UnauthorizedException $e) {
            $returnUrl = urlencode($request->getServerParams()['REQUEST_URI']);

            return $this->redirect($this->container->get('basePath') . '/login?returnurl=' . $returnUrl);
        }
        // Other exceptions are handled by ErrorController
    }

    /**
     * Run the updater for every requests processed while logged in.
     */
    protected function runUpdates(): void
    {
        if ($this->container->get('loginManager')->isLoggedIn() !== true) {
            return;
        }

        $this->container->get('updater')->setBasePath($this->container->get('basePath'));
        $newUpdates = $this->container->get('updater')->update();
        if (!empty($newUpdates)) {
            $this->container->get('updater')->writeUpdates(
                $this->container->get('conf')->get('resource.updates'),
                $this->container->get('updater')->getDoneUpdates()
            );

            $this->container->get('pageCacheManager')->invalidateCaches();
        }
    }

    /**
     * Access is denied to most pages with `hide_public_links` + `force_login` settings.
     */
    protected function checkOpenShaarli(Request $request, RequestHandler $handler): bool
    {
        if (
// if the user isn't logged in
            !$this->container->get('loginManager')->isLoggedIn()
            // and Shaarli doesn't have public content...
            && $this->container->get('conf')->get('privacy.hide_public_links')
            // and is configured to enforce the login
            && $this->container->get('conf')->get('privacy.force_login')
            // and the current page isn't already the login page
            // and the user is not requesting a feed (which would lead to a different content-type as expected)
            && !in_array($request->getAttribute(RouteContext::ROUTE)
                ->getName(), ['login', 'processLogin', 'atom', 'rss'], true)
        ) {
            throw new UnauthorizedException();
        }

        return true;
    }

    /**
     * Initialize the URL base path if it hasn't been defined yet.
     */
    protected function initBasePath(Request $request): void
    {
        if (null === $this->container->get('basePath')) {
            $this->container->set('basePath', rtrim($request->getAttribute('basePath'), '/'));
        }
    }

    /**
     * @param string $url
     * @return Response
     */
    protected function redirect(string $url): Response
    {
        return (new ResponseFactory())->createResponse(302)
            ->withHeader('Location', $url);
    }
}
