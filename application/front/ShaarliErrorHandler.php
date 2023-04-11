<?php

declare(strict_types=1);

namespace Shaarli\Front;

use Psr\Container\ContainerInterface as Container;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Shaarli\Bookmark\BookmarkFilter;
use Shaarli\Front\Exception\ShaarliFrontException;
use Shaarli\Render\TemplatePage;
use Slim\Exception\HttpNotFoundException;
use Slim\Handlers\ErrorHandler;
use Slim\Interfaces\CallableResolverInterface;
use Slim\Psr7\Response;
use Slim\Psr7\Response as SlimResponse;
use Slim\Routing\RouteContext;
use Throwable;

class ShaarliErrorHandler extends ErrorHandler
{
    private ?Container $container;

    public function __construct(
        CallableResolverInterface $callableResolver,
        ResponseFactoryInterface $responseFactory,
        ?LoggerInterface $logger = null,
        ?Container $container = null
    ) {
        parent::__construct($callableResolver, $responseFactory, $logger);
        $this->container = $container;
    }

    public function __invoke(
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails
    ): ResponseInterface {
        $resp = parent::__invoke($request, $exception, $displayErrorDetails, $logErrors, $logErrorDetails);
        $response = new Response();
        // Unknown error encountered
        $this->container->get('pageBuilder')->reset();
        if ($exception instanceof HttpNotFoundException) {
            return $this->showError404($request);
        } elseif ($exception instanceof ShaarliFrontException) {
            // Functional error
            $this->assignView('message', nl2br($exception->getMessage()));

            $response = $response->withStatus($exception->getCode());
        } else {
            // Internal error (any other Throwable)
            if (
                $this->container->get('conf')->get('dev.debug', false) ||
                $this->container->get('loginManager')->isLoggedIn()
            ) {
                $this->assignView('message', t('Error: ') . $exception->getMessage());
                $this->assignView(
                    'text',
                    '<a href="https://github.com/shaarli/Shaarli/issues/new">'
                    . t('Please report it on Github.')
                    . '</a>'
                );
                $this->assignView('stacktrace', exception2text($exception));
            } else {
                $this->assignView('message', t('An unexpected error occurred.'));
            }

            $response = $response->withStatus(500);
        }

        $response->getBody()->write($this->render(TemplatePage::ERROR));
        return $response;
    }

    protected function assignView(string $name, $value): self
    {
        $this->container->get('pageBuilder')->assign($name, $value);

        return $this;
    }

    /**
     * Call plugin hooks for header, footer and includes, specifying which page will be rendered.
     * Then assign generated data to RainTPL.
     */
    protected function executeDefaultHooks(string $template): void
    {
        $common_hooks = [
            'includes',
            'header',
            'footer',
        ];

        $parameters = $this->buildPluginParameters($template);

        foreach ($common_hooks as $name) {
            $pluginData = [];
            $this->container->get('pluginManager')->executeHooks(
                'render_' . $name,
                $pluginData,
                $parameters
            );
            $this->assignView('plugins_' . $name, $pluginData);
        }
    }

    protected function buildPluginParameters(?string $template): array
    {
        $basePath = $this->container->get('basePath') ?? '';
        return [
            'target' => $template,
            'loggedin' => $this->container->get('loginManager')->isLoggedIn(),
            'basePath' => $this->container->get('basePath'),
            'rootPath' => preg_replace('#/index\.php$#', '', $basePath),
            'bookmarkService' => $this->container->get('bookmarkService')
        ];
    }

    protected function render(string $template): string
    {
        // Legacy key that used to be injected by PluginManager
        $this->assignView('_PAGE_', $template);
        $this->assignView('template', $template);

        $this->assignView('linkcount', $this->container->get('bookmarkService')->count(BookmarkFilter::$ALL));
        $this->assignView('privateLinkcount', $this->container->get('bookmarkService')
            ->count(BookmarkFilter::$PRIVATE));

        $this->executeDefaultHooks($template);

        $this->assignView('plugin_errors', $this->container->get('pluginManager')->getErrors());

        $basePath = $this->container->get('basePath') ?? '';
        return $this->container->get('pageBuilder')->render($template, $basePath);
    }

    protected function showError404($request): Response
    {
        $response = new SlimResponse();
        // Request from the API
        if (false !== strpos($request->getRequestTarget(), '/api/v1')) {
            return $response->withStatus(404);
        }
        $basePathFromRequest = $request->getAttribute(RouteContext::BASE_PATH) ?? '';

        // This is required because the middleware is ignored if the route is not found.
        $this->container->set('basePath', rtrim($basePathFromRequest, '/'));

        $this->assignView('error_message', t('Requested page could not be found.'));

        $response = $response->withStatus(404);
        $response->getBody()->write($this->render(TemplatePage::ERROR_404));
        return $response;
    }
}
