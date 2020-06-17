<?php

declare(strict_types=1);

namespace front\controller\admin;

use PHPUnit\Framework\TestCase;
use Shaarli\Bookmark\Bookmark;
use Shaarli\Formatter\BookmarkFormatter;
use Shaarli\Formatter\BookmarkRawFormatter;
use Shaarli\Front\Controller\Admin\ExportController;
use Shaarli\Front\Controller\Admin\FrontAdminControllerMockHelper;
use Shaarli\Netscape\NetscapeBookmarkUtils;
use Shaarli\Security\SessionManager;
use Slim\Http\Request;
use Slim\Http\Response;

class ExportControllerTest extends TestCase
{
    use FrontAdminControllerMockHelper;

    /** @var ExportController */
    protected $controller;

    public function setUp(): void
    {
        $this->createContainer();

        $this->controller = new ExportController($this->container);
    }

    /**
     * Test displaying export page
     */
    public function testIndex(): void
    {
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $request = $this->createMock(Request::class);
        $response = new Response();

        $result = $this->controller->index($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('export', (string) $result->getBody());

        static::assertSame('Export - Shaarli', $assignedVariables['pagetitle']);
    }

    /**
     * Test posting an export request
     */
    public function testExportDefault(): void
    {
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $parameters = [
            'selection' => 'all',
            'prepend_note_url' => 'on',
        ];

        $request = $this->createMock(Request::class);
        $request->method('getParam')->willReturnCallback(function (string $key) use ($parameters) {
            return $parameters[$key] ?? null;
        });
        $response = new Response();

        $bookmarks = [
            (new Bookmark())->setUrl('http://link1.tld')->setTitle('Title 1'),
            (new Bookmark())->setUrl('http://link2.tld')->setTitle('Title 2'),
        ];

        $this->container->netscapeBookmarkUtils = $this->createMock(NetscapeBookmarkUtils::class);
        $this->container->netscapeBookmarkUtils
            ->expects(static::once())
            ->method('filterAndFormat')
            ->willReturnCallback(
                function (
                    BookmarkFormatter $formatter,
                    string $selection,
                    bool $prependNoteUrl,
                    string $indexUrl
                ) use ($parameters, $bookmarks): array {
                    static::assertInstanceOf(BookmarkRawFormatter::class, $formatter);
                    static::assertSame($parameters['selection'], $selection);
                    static::assertTrue($prependNoteUrl);
                    static::assertSame('http://shaarli', $indexUrl);

                    return $bookmarks;
                }
            )
        ;

        $result = $this->controller->export($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('export.bookmarks', (string) $result->getBody());
        static::assertSame(['text/html; charset=utf-8'], $result->getHeader('content-type'));
        static::assertRegExp(
            '/attachment; filename=bookmarks_all_[\d]{8}_[\d]{6}\.html/',
            $result->getHeader('content-disposition')[0]
        );

        static::assertNotEmpty($assignedVariables['date']);
        static::assertSame(PHP_EOL, $assignedVariables['eol']);
        static::assertSame('all', $assignedVariables['selection']);
        static::assertSame($bookmarks, $assignedVariables['links']);
    }

    /**
     * Test posting an export request - without selection parameter
     */
    public function testExportSelectionMissing(): void
    {
        $request = $this->createMock(Request::class);
        $response = new Response();

        $this->container->sessionManager = $this->createMock(SessionManager::class);
        $this->container->sessionManager
            ->expects(static::once())
            ->method('setSessionParameter')
            ->with(SessionManager::KEY_ERROR_MESSAGES, ['Please select an export mode.'])
        ;

        $result = $this->controller->export($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/admin/export'], $result->getHeader('location'));
    }

    /**
     * Test posting an export request - without selection parameter
     */
    public function testExportErrorEncountered(): void
    {
        $parameters = [
            'selection' => 'all',
        ];

        $request = $this->createMock(Request::class);
        $request->method('getParam')->willReturnCallback(function (string $key) use ($parameters) {
            return $parameters[$key] ?? null;
        });
        $response = new Response();

        $this->container->netscapeBookmarkUtils = $this->createMock(NetscapeBookmarkUtils::class);
        $this->container->netscapeBookmarkUtils
            ->expects(static::once())
            ->method('filterAndFormat')
            ->willThrowException(new \Exception($message = 'error message'));
        ;

        $this->container->sessionManager = $this->createMock(SessionManager::class);
        $this->container->sessionManager
            ->expects(static::once())
            ->method('setSessionParameter')
            ->with(SessionManager::KEY_ERROR_MESSAGES, [$message])
        ;

        $result = $this->controller->export($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/admin/export'], $result->getHeader('location'));
    }
}
