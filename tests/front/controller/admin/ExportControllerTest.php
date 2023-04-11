<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin;

use Shaarli\Bookmark\Bookmark;
use Shaarli\Formatter\BookmarkFormatter;
use Shaarli\Formatter\BookmarkRawFormatter;
use Shaarli\Netscape\NetscapeBookmarkUtils;
use Shaarli\Security\SessionManager;
use Shaarli\TestCase;
use Shaarli\Tests\Utils\FakeRequest;
use Slim\Psr7\Response as SlimResponse;
use Slim\Psr7\Uri;

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

        $request = new FakeRequest();
        $response = new SlimResponse();

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

        $request = (new FakeRequest(
            'POST',
            new Uri('', '', 80, '')
        ))->withParsedBody($parameters)->withServerParams([
            'SERVER_PORT' => 80,
            'SERVER_NAME' => 'shaarli',
            'SCRIPT_NAME' => '/subfolder/index.php',
        ]);
        $response = new SlimResponse();

        $bookmarks = [
            (new Bookmark())->setUrl('http://link1.tld')->setTitle('Title 1'),
            (new Bookmark())->setUrl('http://link2.tld')->setTitle('Title 2'),
        ];

        $this->container->set('netscapeBookmarkUtils', $this->createMock(NetscapeBookmarkUtils::class));
        $this->container->get('netscapeBookmarkUtils')
            ->expects(static::once())
            ->method('filterAndFormat')
            ->willReturnCallback(
                function (
                    BookmarkFormatter $formatter,
                    string $selection,
                    bool $prependNoteUrl,
                    string $indexUrl
                ) use (
                    $parameters,
                    $bookmarks
                ): array {
                    static::assertInstanceOf(BookmarkRawFormatter::class, $formatter);
                    static::assertSame($parameters['selection'], $selection);
                    static::assertTrue($prependNoteUrl);
                    static::assertSame('http://shaarli/subfolder/', $indexUrl);

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
        $request = new FakeRequest();
        $response = new SlimResponse();

        $this->container->get('sessionManager')
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

        $request = (new FakeRequest(
            'POST',
            new Uri('', '', 80, '')
        ))->withParsedBody($parameters)->withServerParams([
            'SERVER_PORT' => 80,
            'SERVER_NAME' => 'shaarli',
            'SCRIPT_NAME' => '/subfolder/index.php',
        ]);
        $response = new SlimResponse();

        $this->container->set('netscapeBookmarkUtils', $this->createMock(NetscapeBookmarkUtils::class));
        $this->container->get('netscapeBookmarkUtils')
            ->expects(static::once())
            ->method('filterAndFormat')
            ->willThrowException(new \Exception($message = 'error message'));
        ;

        $this->container->get('sessionManager')
            ->expects(static::once())
            ->method('setSessionParameter')
            ->with(SessionManager::KEY_ERROR_MESSAGES, [$message])
        ;

        $result = $this->controller->export($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/admin/export'], $result->getHeader('location'));
    }
}
