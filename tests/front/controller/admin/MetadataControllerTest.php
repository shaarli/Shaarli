<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin;

use Shaarli\Front\Exception\WrongTokenException;
use Shaarli\Http\MetadataRetriever;
use Shaarli\TestCase;
use Slim\Http\Request;
use Slim\Http\Response;

class MetadataControllerTest extends TestCase
{
    use FrontAdminControllerMockHelper;

    /** @var MetadataController */
    protected $controller;

    public function setUp(): void
    {
        $this->createContainer();

        $this->container->metadataRetriever = $this->createMock(MetadataRetriever::class);
        // Override the default checkToken mock from FrontAdminControllerMockHelper
        // by using a fresh mock that doesn't have the default stub
        $this->container->sessionManager = $this->createMock(
            \Shaarli\Security\SessionManager::class
        );
        $this->controller = new MetadataController($this->container);
    }

    /**
     * Helper to create a request mock with token and URL params
     */
    private function createRequestMock(?string $token, string $url): Request
    {
        $request = $this->createMock(Request::class);
        $request->method('getParam')
            ->willReturnCallback(function ($param) use ($token, $url) {
                if ($param === 'token') {
                    return $token;
                }
                if ($param === 'url') {
                    return $url;
                }
                return null;
            });

        return $request;
    }

    /**
     * Test that the endpoint requires a valid CSRF token
     */
    public function testRequiresCsrfToken(): void
    {
        $request = $this->createRequestMock(null, 'http://example.com');

        // Use will() without expects() to override the default stub from createContainer()
        $this->container->sessionManager
            ->method('checkToken')
            ->will($this->returnCallback(function ($token) {
                return $token !== null;
            }))
        ;

        $this->expectException(WrongTokenException::class);

        $this->controller->ajaxRetrieveTitle($request, new Response());
    }

    /**
     * Test that a valid token allows metadata retrieval
     */
    public function testWithValidToken(): void
    {
        $url = 'http://example.com';
        $token = 'valid-token';

        $request = $this->createRequestMock($token, $url);

        $this->container->sessionManager
            ->expects(static::once())
            ->method('checkToken')
            ->with($token)
            ->willReturn(true)
        ;

        $this->container->metadataRetriever
            ->expects(static::once())
            ->method('retrieve')
            ->with($url)
            ->willReturn(['title' => 'Test', 'description' => 'Desc'])
        ;

        $result = $this->controller->ajaxRetrieveTitle($request, new Response());

        static::assertSame(200, $result->getStatusCode());
        static::assertSame(
            json_encode(['title' => 'Test', 'description' => 'Desc']),
            (string) $result->getBody()
        );
    }

    /**
     * Test that an invalid token returns 403
     */
    public function testWithInvalidToken(): void
    {
        $request = $this->createRequestMock('invalid-token', 'http://example.com');

        $this->container->sessionManager
            ->method('checkToken')
            ->will($this->returnCallback(function ($token) {
                return $token === 'valid-token';
            }))
        ;

        $this->expectException(WrongTokenException::class);

        $this->controller->ajaxRetrieveTitle($request, new Response());
    }

    /**
     * Test that an empty URL returns empty JSON (no metadata retrieval attempted)
     */
    public function testEmptyUrlReturnsEmptyJson(): void
    {
        $request = $this->createRequestMock('valid-token', '');

        $this->container->sessionManager
            ->expects(static::once())
            ->method('checkToken')
            ->with('valid-token')
            ->willReturn(true)
        ;

        $this->container->metadataRetriever
            ->expects(static::never())
            ->method('retrieve')
        ;

        $result = $this->controller->ajaxRetrieveTitle($request, new Response());

        static::assertSame(200, $result->getStatusCode());
        static::assertSame(json_encode([]), (string) $result->getBody());
    }

    /**
     * Test that non-HTTP URLs return empty JSON (no metadata retrieval attempted)
     */
    public function testNonHttpUrlReturnsEmptyJson(): void
    {
        $request = $this->createRequestMock('valid-token', 'ftp://example.com/file');

        $this->container->sessionManager
            ->expects(static::once())
            ->method('checkToken')
            ->with('valid-token')
            ->willReturn(true)
        ;

        $this->container->metadataRetriever
            ->expects(static::never())
            ->method('retrieve')
        ;

        $result = $this->controller->ajaxRetrieveTitle($request, new Response());

        static::assertSame(200, $result->getStatusCode());
        static::assertSame(json_encode([]), (string) $result->getBody());
    }

    /**
     * Test that unsafe URLs are rejected (no metadata retrieval attempted)
     */
    public function testUnsafeUrlReturnsEmptyJson(): void
    {
        $request = $this->createRequestMock('valid-token', 'http://127.0.0.1/');

        $this->container->sessionManager
            ->expects(static::once())
            ->method('checkToken')
            ->with('valid-token')
            ->willReturn(true)
        ;

        $this->container->metadataRetriever
            ->expects(static::never())
            ->method('retrieve')
        ;

        $result = $this->controller->ajaxRetrieveTitle($request, new Response());

        static::assertSame(200, $result->getStatusCode());
        static::assertSame(json_encode([]), (string) $result->getBody());
    }

    /**
     * Test that the token is validated (not ignored) before metadata retrieval.
     * This verifies that the CSRF check is active.
     */
    public function testTokenIsCheckedBeforeRetrieval(): void
    {
        $token = 'valid-token';

        $request = $this->createMock(Request::class);
        $request->method('getParam')
            ->willReturnCallback(function ($param) use ($token) {
                if ($param === 'token') {
                    return $token;
                }
                if ($param === 'url') {
                    return 'http://example.com';
                }
                return null;
            });

        $this->container->sessionManager
            ->expects(static::once())
            ->method('checkToken')
            ->with($token)
            ->willReturn(true)
        ;

        $this->container->metadataRetriever
            ->expects(static::once())
            ->method('retrieve')
            ->willReturn(['title' => 'Test'])
        ;

        $this->controller->ajaxRetrieveTitle($request, new Response());
    }
}
