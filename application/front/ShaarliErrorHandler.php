<?php

declare(strict_types=1);

namespace Shaarli\Front;

use Psr\Container\ContainerInterface as Container;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Shaarli\Front\Controller\PageTrait;
use Shaarli\Front\Exception\ShaarliFrontException;
use Shaarli\Render\TemplatePage;
use Slim\Exception\HttpNotFoundException;
use Slim\Handlers\ErrorHandler;
use Slim\Interfaces\CallableResolverInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Routing\RouteContext;
use Throwable;

class ShaarliErrorHandler extends ErrorHandler
{
    use PageTrait;

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
        parent::__invoke($request, $exception, $displayErrorDetails, $logErrors, $logErrorDetails);
        $response = (new ResponseFactory())->createResponse();
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

    protected function showError404($request): ResponseInterface
    {
        $response = (new ResponseFactory())->createResponse();
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
