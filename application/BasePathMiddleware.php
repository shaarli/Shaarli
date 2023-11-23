<?php

declare(strict_types=1);

namespace Shaarli;

use Selective\BasePath\BasePathDetector;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\App;

/*
 * This is a hack that to make BasePathMiddleware Selective's library without a public subdirectory,
 * and without re-implementing the dectector.
 */
class BasePathMiddleware implements MiddlewareInterface {
    /**
     * @var App The slim app
     */
    private $app;

    /**
     * @var string|null
     */
    private $phpSapi;

    /**
     * The constructor.
     *
     * @param App $app The slim app
     * @param string|null $phpSapi The PHP_SAPI value
     */
    public function __construct(App $app, string $phpSapi = null)
    {
        $this->app = $app;
        $this->phpSapi = $phpSapi;
    }
    
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $params = $request->getServerParams();
        $pathParts = pathinfo($params['SCRIPT_NAME']);
        $params['SCRIPT_NAME'] = $pathParts['dirname'] . '/public/' . $pathParts['basename'];
        
        $detector = new BasePathDetector($params, $this->phpSapi);

        $this->app->setBasePath($detector->getBasePath());

        return $handler->handle($request);
    }
}
