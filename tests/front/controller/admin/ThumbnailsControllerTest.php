<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin;

use PHPUnit\Framework\TestCase;
use Shaarli\Bookmark\Bookmark;
use Shaarli\Bookmark\Exception\BookmarkNotFoundException;
use Shaarli\Thumbnailer;
use Slim\Http\Request;
use Slim\Http\Response;

class ThumbnailsControllerTest extends TestCase
{
    use FrontAdminControllerMockHelper;

    /** @var ThumbnailsController */
    protected $controller;

    public function setUp(): void
    {
        $this->createContainer();

        $this->controller = new ThumbnailsController($this->container);
    }

    /**
     * Test displaying the thumbnails update page
     * Note that only non-note and HTTP bookmarks should be returned.
     */
    public function testIndex(): void
    {
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $request = $this->createMock(Request::class);
        $response = new Response();

        $this->container->bookmarkService
            ->expects(static::once())
            ->method('search')
            ->willReturn([
                (new Bookmark())->setId(1)->setUrl('http://url1.tld')->setTitle('Title 1'),
                (new Bookmark())->setId(2)->setUrl('?abcdef')->setTitle('Note 1'),
                (new Bookmark())->setId(3)->setUrl('http://url2.tld')->setTitle('Title 2'),
                (new Bookmark())->setId(4)->setUrl('ftp://domain.tld', ['ftp'])->setTitle('FTP'),
            ])
        ;

        $result = $this->controller->index($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('thumbnails', (string) $result->getBody());

        static::assertSame('Thumbnails update - Shaarli', $assignedVariables['pagetitle']);
        static::assertSame([1, 3], $assignedVariables['ids']);
    }

    /**
     * Test updating a bookmark thumbnail with valid parameters
     */
    public function testAjaxUpdateValid(): void
    {
        $request = $this->createMock(Request::class);
        $response = new Response();

        $bookmark = (new Bookmark())
            ->setId($id = 123)
            ->setUrl($url = 'http://url1.tld')
            ->setTitle('Title 1')
            ->setThumbnail(false)
        ;

        $this->container->thumbnailer = $this->createMock(Thumbnailer::class);
        $this->container->thumbnailer
            ->expects(static::once())
            ->method('get')
            ->with($url)
            ->willReturn($thumb = 'http://img.tld/pic.png')
        ;

        $this->container->bookmarkService
            ->expects(static::once())
            ->method('get')
            ->with($id)
            ->willReturn($bookmark)
        ;
        $this->container->bookmarkService
            ->expects(static::once())
            ->method('set')
            ->willReturnCallback(function (Bookmark $bookmark) use ($thumb) {
                static::assertSame($thumb, $bookmark->getThumbnail());
            })
        ;

        $result = $this->controller->ajaxUpdate($request, $response, ['id' => (string) $id]);

        static::assertSame(200, $result->getStatusCode());

        $payload = json_decode((string) $result->getBody(), true);

        static::assertSame($id, $payload['id']);
        static::assertSame($url, $payload['url']);
        static::assertSame($thumb, $payload['thumbnail']);
    }

    /**
     * Test updating a bookmark thumbnail - Invalid ID
     */
    public function testAjaxUpdateInvalidId(): void
    {
        $request = $this->createMock(Request::class);
        $response = new Response();

        $result = $this->controller->ajaxUpdate($request, $response, ['id' => 'nope']);

        static::assertSame(400, $result->getStatusCode());
    }

    /**
     * Test updating a bookmark thumbnail - No ID
     */
    public function testAjaxUpdateNoId(): void
    {
        $request = $this->createMock(Request::class);
        $response = new Response();

        $result = $this->controller->ajaxUpdate($request, $response, []);

        static::assertSame(400, $result->getStatusCode());
    }

    /**
     * Test updating a bookmark thumbnail with valid parameters
     */
    public function testAjaxUpdateBookmarkNotFound(): void
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

        $result = $this->controller->ajaxUpdate($request, $response, ['id' => (string) $id]);

        static::assertSame(404, $result->getStatusCode());
    }
}
