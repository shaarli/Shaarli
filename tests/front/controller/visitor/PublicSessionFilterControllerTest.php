<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Visitor;

use Psr\Http\Message\ResponseInterface as Response;
use Shaarli\Security\SessionManager;
use Shaarli\TestCase;
use Shaarli\Tests\Utils\FakeRequest;
use Slim\Psr7\Response as SlimResponse;
use Slim\Psr7\Uri;

class PublicSessionFilterControllerTest extends TestCase
{
    use FrontControllerMockHelper;

    /** @var PublicSessionFilterController */
    protected $controller;

    public function setUp(): void
    {
        $this->createContainer();

        $this->controller = new PublicSessionFilterController($this->container);
    }

    /**
     * Link per page - Default call with valid parameter and a referer.
     */
    public function testLinksPerPage(): void
    {
        $request = (new FakeRequest(
            'GET',
            (new Uri('', ''))->withQuery('nb=8')
        ))->withServerParams([
            'SERVER_NAME' => 'shaarli',
                'SERVER_PORT' => '80',
                'HTTP_REFERER' => 'http://shaarli/subfolder/controller/?searchtag=abc'
        ]);

        $response = new SlimResponse();

        $this->container->get('sessionManager')
            ->expects(static::once())
            ->method('setSessionParameter')
            ->with(SessionManager::KEY_LINKS_PER_PAGE, 8)
        ;

        $result = $this->controller->linksPerPage($request, $response);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/controller/?searchtag=abc'], $result->getHeader('location'));
    }

    /**
     * Link per page - Invalid value, should use default value (20)
     */
    public function testLinksPerPageNotValid(): void
    {
        $request = (new FakeRequest(
            'GET',
            (new Uri('', ''))->withQuery('nb=test')
        ));
        $response = new SlimResponse();

        $this->container->get('sessionManager')
            ->expects(static::once())
            ->method('setSessionParameter')
            ->with(SessionManager::KEY_LINKS_PER_PAGE, 20)
        ;

        $result = $this->controller->linksPerPage($request, $response);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/'], $result->getHeader('location'));
    }

    /**
     * Untagged only - valid call
     */
    public function testUntaggedOnly(): void
    {
        $request = (new FakeRequest(
            'GET',
            (new Uri('', ''))
        ))->withServerParams([
            'SERVER_NAME' => 'shaarli',
            'SERVER_PORT' => '80',
            'HTTP_REFERER' => 'http://shaarli/subfolder/controller/?searchtag=abc'
        ]);
        $response = new SlimResponse();

        $this->container->get('sessionManager')
            ->expects(static::once())
            ->method('setSessionParameter')
            ->with(SessionManager::KEY_UNTAGGED_ONLY, true)
        ;

        $result = $this->controller->untaggedOnly($request, $response);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/controller/?searchtag=abc'], $result->getHeader('location'));
    }

    /**
     * Untagged only - toggle off
     */
    public function testUntaggedOnlyToggleOff(): void
    {
        $request = (new FakeRequest(
            'GET',
            (new Uri('', '', 80, 'shaarli'))
        ))->withServerParams([
            'SERVER_NAME' => 'shaarli',
            'SERVER_PORT' => '80',
            'HTTP_REFERER' => 'http://shaarli/subfolder/controller/?searchtag=abc'
        ]);
        $response = new SlimResponse();

        $this->container->get('sessionManager')
            ->method('getSessionParameter')
            ->with(SessionManager::KEY_UNTAGGED_ONLY)
            ->willReturn(true)
        ;
        $this->container->get('sessionManager')
            ->expects(static::once())
            ->method('setSessionParameter')
            ->with(SessionManager::KEY_UNTAGGED_ONLY, false)
        ;

        $result = $this->controller->untaggedOnly($request, $response);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/controller/?searchtag=abc'], $result->getHeader('location'));
    }
}
