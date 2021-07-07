<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin\ShaareManageControllerTest;

use Shaarli\Bookmark\Bookmark;
use Shaarli\Front\Controller\Admin\FrontAdminControllerMockHelper;
use Shaarli\Front\Controller\Admin\ShaareManageController;
use Shaarli\Http\HttpAccess;
use Shaarli\TestCase;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Test GET /admin/shaare/private/{hash}
 */
class SharePrivateTest extends TestCase
{
    use FrontAdminControllerMockHelper;

    /** @var ShaareManageController */
    protected $controller;

    public function setUp(): void
    {
        $this->createContainer();

        $this->container->httpAccess = $this->createMock(HttpAccess::class);
        $this->controller = new ShaareManageController($this->container);
    }

    /**
     * Test shaare private with a private bookmark which does not have a key yet.
     */
    public function testSharePrivateWithNewPrivateBookmark(): void
    {
        $hash = 'abcdcef';
        $request = $this->createMock(Request::class);
        $response = new Response();

        $bookmark = (new Bookmark())
            ->setId(123)
            ->setUrl('http://domain.tld')
            ->setTitle('Title 123')
            ->setPrivate(true)
        ;

        $this->container->bookmarkService
            ->expects(static::once())
            ->method('findByHash')
            ->with($hash)
            ->willReturn($bookmark)
        ;
        $this->container->bookmarkService
            ->expects(static::once())
            ->method('set')
            ->with($bookmark, true)
            ->willReturnCallback(function (Bookmark $bookmark): Bookmark {
                static::assertSame(32, strlen($bookmark->getAdditionalContentEntry('private_key')));

                return $bookmark;
            })
        ;

        $result = $this->controller->sharePrivate($request, $response, ['hash' => $hash]);

        static::assertSame(302, $result->getStatusCode());
        static::assertRegExp('#/subfolder/shaare/' . $hash . '\?key=\w{32}#', $result->getHeaderLine('Location'));
    }

    /**
     * Test shaare private with a private bookmark which does already have a key.
     */
    public function testSharePrivateWithExistingPrivateBookmark(): void
    {
        $hash = 'abcdcef';
        $existingKey = 'this is a private key';
        $request = $this->createMock(Request::class);
        $response = new Response();

        $bookmark = (new Bookmark())
            ->setId(123)
            ->setUrl('http://domain.tld')
            ->setTitle('Title 123')
            ->setPrivate(true)
            ->setAdditionalContentEntry('private_key', $existingKey)
        ;

        $this->container->bookmarkService
            ->expects(static::once())
            ->method('findByHash')
            ->with($hash)
            ->willReturn($bookmark)
        ;
        $this->container->bookmarkService
            ->expects(static::never())
            ->method('set')
        ;

        $result = $this->controller->sharePrivate($request, $response, ['hash' => $hash]);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame('/subfolder/shaare/' . $hash . '?key=' . $existingKey, $result->getHeaderLine('Location'));
    }

    /**
     * Test shaare private with a public bookmark.
     */
    public function testSharePrivateWithPublicBookmark(): void
    {
        $hash = 'abcdcef';
        $request = $this->createMock(Request::class);
        $response = new Response();

        $bookmark = (new Bookmark())
            ->setId(123)
            ->setUrl('http://domain.tld')
            ->setTitle('Title 123')
            ->setPrivate(false)
        ;

        $this->container->bookmarkService
            ->expects(static::once())
            ->method('findByHash')
            ->with($hash)
            ->willReturn($bookmark)
        ;
        $this->container->bookmarkService
            ->expects(static::never())
            ->method('set')
        ;

        $result = $this->controller->sharePrivate($request, $response, ['hash' => $hash]);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame('/subfolder/shaare/' . $hash, $result->getHeaderLine('Location'));
    }
}
