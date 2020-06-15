<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin\ManageShaareControllerTest;

use PHPUnit\Framework\TestCase;
use Shaarli\Bookmark\Bookmark;
use Shaarli\Bookmark\Exception\BookmarkNotFoundException;
use Shaarli\Front\Controller\Admin\FrontAdminControllerMockHelper;
use Shaarli\Front\Controller\Admin\ManageShaareController;
use Shaarli\Http\HttpAccess;
use Shaarli\Security\SessionManager;
use Slim\Http\Request;
use Slim\Http\Response;

class PinBookmarkTest extends TestCase
{
    use FrontAdminControllerMockHelper;

    /** @var ManageShaareController */
    protected $controller;

    public function setUp(): void
    {
        $this->createContainer();

        $this->container->httpAccess = $this->createMock(HttpAccess::class);
        $this->controller = new ManageShaareController($this->container);
    }

    /**
     * Test pin bookmark - with valid input
     *
     * @dataProvider initialStickyValuesProvider()
     */
    public function testPinBookmarkIsStickyNull(?bool $sticky, bool $expectedValue): void
    {
        $id = 123;

        $request = $this->createMock(Request::class);
        $response = new Response();

        $bookmark = (new Bookmark())
            ->setId(123)
            ->setUrl('http://domain.tld')
            ->setTitle('Title 123')
            ->setSticky($sticky)
        ;

        $this->container->bookmarkService->expects(static::once())->method('get')->with(123)->willReturn($bookmark);
        $this->container->bookmarkService->expects(static::once())->method('set')->with($bookmark, true);

        // Make sure that PluginManager hook is triggered
        $this->container->pluginManager
            ->expects(static::once())
            ->method('executeHooks')
            ->with('save_link')
        ;

        $result = $this->controller->pinBookmark($request, $response, ['id' => (string) $id]);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/'], $result->getHeader('location'));

        static::assertSame($expectedValue, $bookmark->isSticky());
    }

    public function initialStickyValuesProvider(): array
    {
        // [initialStickyState, isStickyAfterPin]
        return [[null, true], [false, true], [true, false]];
    }

    /**
     * Test pin bookmark - invalid bookmark ID
     */
    public function testDisplayEditFormInvalidId(): void
    {
        $id = 'invalid';

        $request = $this->createMock(Request::class);
        $response = new Response();

        $this->container->sessionManager
            ->expects(static::once())
            ->method('setSessionParameter')
            ->with(SessionManager::KEY_ERROR_MESSAGES, ['Bookmark with identifier invalid could not be found.'])
        ;

        $result = $this->controller->pinBookmark($request, $response, ['id' => $id]);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/'], $result->getHeader('location'));
    }

    /**
     * Test pin bookmark - Bookmark ID not provided
     */
    public function testDisplayEditFormIdNotProvided(): void
    {
        $request = $this->createMock(Request::class);
        $response = new Response();

        $this->container->sessionManager
            ->expects(static::once())
            ->method('setSessionParameter')
            ->with(SessionManager::KEY_ERROR_MESSAGES, ['Bookmark with identifier  could not be found.'])
        ;

        $result = $this->controller->pinBookmark($request, $response, []);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/'], $result->getHeader('location'));
    }

    /**
     * Test pin bookmark - bookmark not found
     */
    public function testDisplayEditFormBookmarkNotFound(): void
    {
        $id = 123;

        $request = $this->createMock(Request::class);
        $response = new Response();

        $this->container->bookmarkService
            ->expects(static::once())
            ->method('get')
            ->with($id)
            ->willThrowException(new BookmarkNotFoundException())
        ;

        $this->container->sessionManager
            ->expects(static::once())
            ->method('setSessionParameter')
            ->with(SessionManager::KEY_ERROR_MESSAGES, ['Bookmark with identifier 123 could not be found.'])
        ;

        $result = $this->controller->pinBookmark($request, $response, ['id' => (string) $id]);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/'], $result->getHeader('location'));
    }
}
