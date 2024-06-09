<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin;

use Shaarli\Bookmark\Bookmark;
use Shaarli\Bookmark\BookmarkFilter;
use Shaarli\Bookmark\SearchResult;
use Shaarli\Config\ConfigManager;
use Shaarli\Front\Exception\WrongTokenException;
use Shaarli\Security\SessionManager;
use Shaarli\TestCase;
use Slim\Http\Request;
use Slim\Http\Response;
// These are declared for the bookmark service
use malkusch\lock\mutex\NoMutex;
use Shaarli\History;
use Shaarli\Plugin\PluginManager;
use Shaarli\Tests\Utils\FakeBookmarkService;
use Shaarli\Tests\Utils\ReferenceLinkDB;

class ManageTagControllerTest extends TestCase
{
    use FrontAdminControllerMockHelper;

    /** @var ManageTagController */
    protected $controller;

    /** @var string Path of test data store */
    protected static $testDatastore = 'sandbox/datastore.php';

    /** @var BookmarkServiceInterface instance */
    protected $bookmarkService;

    /** @var BookmarkFilter instance */
    protected $linkFilter;

    /** @var ReferenceLinkDB instance */
    protected static $refDB;

    /** @var PluginManager */
    protected static $pluginManager;

    public function setUp(): void
    {
        $this->createContainer();

        $this->controller = new ManageTagController($this->container);

        $mutex = new NoMutex();
        $conf = new ConfigManager('tests/utils/config/configJson');
        $conf->set('resource.datastore', self::$testDatastore);
        static::$pluginManager = new PluginManager($conf);
        self::$refDB = new ReferenceLinkDB();
        self::$refDB->write(self::$testDatastore);
        $history = new History('sandbox/history.php');
        $this->container->bookmarkService = new FakeBookmarkService(
            $conf,
            static::$pluginManager,
            $history,
            $mutex,
            true
        );
        $this->linkFilter = new BookmarkFilter(
            $this->container->bookmarkService->getBookmarks(),
            $conf,
            static::$pluginManager
        );
    }

    /**
     * Test displaying manage tag page
     */
    public function testIndex(): void
    {
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $request = $this->createMock(Request::class);
        $request->method('getParam')->with('fromtag')->willReturn('fromtag');
        $response = new Response();

        $result = $this->controller->index($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('changetag', (string) $result->getBody());

        static::assertSame('fromtag', $assignedVariables['fromtag']);
        static::assertSame('@', $assignedVariables['tags_separator']);
        static::assertSame('Manage tags - Shaarli', $assignedVariables['pagetitle']);
    }

    /**
     * Test displaying manage tag page
     */
    public function testIndexWhitespaceSeparator(): void
    {
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $this->container->conf = $this->createMock(ConfigManager::class);
        $this->container->conf->method('get')->willReturnCallback(function (string $key) {
            return $key === 'general.tags_separator' ? ' ' : $key;
        });

        $request = $this->createMock(Request::class);
        $response = new Response();

        $this->controller->index($request, $response);

        static::assertSame('&nbsp;', $assignedVariables['tags_separator']);
        static::assertSame('whitespace', $assignedVariables['tags_separator_desc']);
    }

    /**
     * Test posting a tag update - rename tag - valid info provided.
     */
    public function testSaveRenameTagValid(): void
    {
        $session = [];
        $this->assignSessionVars($session);

        $this->assertEquals(
            3,
            count($this->linkFilter->filter(BookmarkFilter::$FILTER_TAG, 'cartoon'))
        );
        $this->assertEquals(
            4,
            count($this->linkFilter->filter(BookmarkFilter::$FILTER_TAG, 'web'))
        );
        $this->assertEquals(
            2,
            count($this->linkFilter->filter(BookmarkFilter::$FILTER_TAG, 'cartoon web'))
        );

        $requestParameters = [
            'renametag' => 'rename',
            'fromtag' => 'cartoon',
            'totag' => 'web',
        ];
        $request = $this->createMock(Request::class);
        $request
            ->expects(static::atLeastOnce())
            ->method('getParam')
            ->willReturnCallback(function (string $key) use ($requestParameters): ?string {
                return $requestParameters[$key] ?? null;
            })
        ;
        $response = new Response();
        $result = $this->controller->save($request, $response);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/?searchtags=web'], $result->getHeader('location'));

        static::assertArrayNotHasKey(SessionManager::KEY_ERROR_MESSAGES, $session);
        static::assertArrayNotHasKey(SessionManager::KEY_WARNING_MESSAGES, $session);
        static::assertArrayHasKey(SessionManager::KEY_SUCCESS_MESSAGES, $session);
        static::assertSame(['The tag was renamed in 3 bookmarks.'], $session[SessionManager::KEY_SUCCESS_MESSAGES]);

        $this->assertEquals(
            0,
            count($this->linkFilter->filter(BookmarkFilter::$FILTER_TAG, 'cartoon'))
        );
        $new = $this->linkFilter->filter(BookmarkFilter::$FILTER_TAG, 'web');
        $this->assertEquals(
            5,
            count($new)
        );

        // Make sure there are no duplicate tags
        foreach ($new as $bookmark) {
            $tags = $bookmark->getTags();
            $this->assertEquals(
                count($tags),
                count(array_unique($tags))
            );
        }
    }

    /**
     * Test posting a tag update - delete tag - valid info provided.
     */
    public function testSaveDeleteTagValid(): void
    {
        $session = [];
        $this->assignSessionVars($session);

        $this->assertEquals(
            3,
            count($this->linkFilter->filter(BookmarkFilter::$FILTER_TAG, 'cartoon'))
        );

        $requestParameters = [
            'deletetag' => 'delete',
            'fromtag' => 'cartoon',
        ];

        $request = $this->createMock(Request::class);
        $request
            ->expects(static::atLeastOnce())
            ->method('getParam')
            ->willReturnCallback(function (string $key) use ($requestParameters): ?string {
                return $requestParameters[$key] ?? null;
            })
        ;
        $response = new Response();

        $result = $this->controller->save($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/admin/tags'], $result->getHeader('location'));

        static::assertArrayNotHasKey(SessionManager::KEY_ERROR_MESSAGES, $session);
        static::assertArrayNotHasKey(SessionManager::KEY_WARNING_MESSAGES, $session);
        static::assertArrayHasKey(SessionManager::KEY_SUCCESS_MESSAGES, $session);
        static::assertSame(['The tag was removed from 3 bookmarks.'], $session[SessionManager::KEY_SUCCESS_MESSAGES]);
    }

    /**
     * Test posting a tag update - wrong token.
     */
    public function testSaveWrongToken(): void
    {
        $this->container->sessionManager = $this->createMock(SessionManager::class);
        $this->container->sessionManager->method('checkToken')->willReturn(false);

        $this->container->conf->expects(static::never())->method('set');
        $this->container->conf->expects(static::never())->method('write');

        $request = $this->createMock(Request::class);
        $response = new Response();

        $this->expectException(WrongTokenException::class);

        $this->controller->save($request, $response);
    }

    /**
     * Test posting a tag update - rename tag - missing "FROM" tag.
     */
    public function testSaveRenameTagMissingFrom(): void
    {
        $session = [];
        $this->assignSessionVars($session);

        $requestParameters = [
            'renametag' => 'rename',
        ];
        $request = $this->createMock(Request::class);
        $request
            ->expects(static::atLeastOnce())
            ->method('getParam')
            ->willReturnCallback(function (string $key) use ($requestParameters): ?string {
                return $requestParameters[$key] ?? null;
            })
        ;
        $response = new Response();

        $result = $this->controller->save($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/admin/tags'], $result->getHeader('location'));

        static::assertArrayNotHasKey(SessionManager::KEY_ERROR_MESSAGES, $session);
        static::assertArrayHasKey(SessionManager::KEY_WARNING_MESSAGES, $session);
        static::assertArrayNotHasKey(SessionManager::KEY_SUCCESS_MESSAGES, $session);
        static::assertSame(['Invalid tags provided.'], $session[SessionManager::KEY_WARNING_MESSAGES]);
    }

    /**
     * Test posting a tag update - delete tag - missing "FROM" tag.
     */
    public function testSaveDeleteTagMissingFrom(): void
    {
        $session = [];
        $this->assignSessionVars($session);

        $requestParameters = [
            'deletetag' => 'delete',
        ];
        $request = $this->createMock(Request::class);
        $request
            ->expects(static::atLeastOnce())
            ->method('getParam')
            ->willReturnCallback(function (string $key) use ($requestParameters): ?string {
                return $requestParameters[$key] ?? null;
            })
        ;
        $response = new Response();

        $result = $this->controller->save($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/admin/tags'], $result->getHeader('location'));

        static::assertArrayNotHasKey(SessionManager::KEY_ERROR_MESSAGES, $session);
        static::assertArrayHasKey(SessionManager::KEY_WARNING_MESSAGES, $session);
        static::assertArrayNotHasKey(SessionManager::KEY_SUCCESS_MESSAGES, $session);
        static::assertSame(['Invalid tags provided.'], $session[SessionManager::KEY_WARNING_MESSAGES]);
    }

    /**
     * Test posting a tag update - rename tag - missing "TO" tag.
     */
    public function testSaveRenameTagMissingTo(): void
    {
        $session = [];
        $this->assignSessionVars($session);

        $requestParameters = [
            'renametag' => 'rename',
            'fromtag' => 'old-tag'
        ];
        $request = $this->createMock(Request::class);
        $request
            ->expects(static::atLeastOnce())
            ->method('getParam')
            ->willReturnCallback(function (string $key) use ($requestParameters): ?string {
                return $requestParameters[$key] ?? null;
            })
        ;
        $response = new Response();

        $result = $this->controller->save($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/admin/tags'], $result->getHeader('location'));

        static::assertArrayNotHasKey(SessionManager::KEY_ERROR_MESSAGES, $session);
        static::assertArrayHasKey(SessionManager::KEY_WARNING_MESSAGES, $session);
        static::assertArrayNotHasKey(SessionManager::KEY_SUCCESS_MESSAGES, $session);
        static::assertSame(['Invalid tags provided.'], $session[SessionManager::KEY_WARNING_MESSAGES]);
    }

    /**
     * Test changeSeparator to '#': redirection + success message.
     */
    public function testChangeSeparatorValid(): void
    {
        $toSeparator = '#';

        $session = [];
        $this->assignSessionVars($session);

        $request = $this->createMock(Request::class);
        $request
            ->expects(static::atLeastOnce())
            ->method('getParam')
            ->willReturnCallback(function (string $key) use ($toSeparator): ?string {
                return $key === 'separator' ? $toSeparator : $key;
            })
        ;
        $response = new Response();

        $this->container->conf
            ->expects(static::once())
            ->method('set')
            ->with('general.tags_separator', $toSeparator, true, true)
        ;

        $result = $this->controller->changeSeparator($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/admin/tags'], $result->getHeader('location'));

        static::assertArrayNotHasKey(SessionManager::KEY_ERROR_MESSAGES, $session);
        static::assertArrayNotHasKey(SessionManager::KEY_WARNING_MESSAGES, $session);
        static::assertArrayHasKey(SessionManager::KEY_SUCCESS_MESSAGES, $session);
        static::assertSame(
            ['Your tags separator setting has been updated!'],
            $session[SessionManager::KEY_SUCCESS_MESSAGES]
        );
    }

    /**
     * Test changeSeparator to '#@' (too long): redirection + error message.
     */
    public function testChangeSeparatorInvalidTooLong(): void
    {
        $toSeparator = '#@';

        $session = [];
        $this->assignSessionVars($session);

        $request = $this->createMock(Request::class);
        $request
            ->expects(static::atLeastOnce())
            ->method('getParam')
            ->willReturnCallback(function (string $key) use ($toSeparator): ?string {
                return $key === 'separator' ? $toSeparator : $key;
            })
        ;
        $response = new Response();

        $this->container->conf->expects(static::never())->method('set');

        $result = $this->controller->changeSeparator($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/admin/tags'], $result->getHeader('location'));

        static::assertArrayNotHasKey(SessionManager::KEY_SUCCESS_MESSAGES, $session);
        static::assertArrayNotHasKey(SessionManager::KEY_WARNING_MESSAGES, $session);
        static::assertArrayHasKey(SessionManager::KEY_ERROR_MESSAGES, $session);
        static::assertSame(
            ['Tags separator must be a single character.'],
            $session[SessionManager::KEY_ERROR_MESSAGES]
        );
    }

    /**
     * Test changeSeparator to '#@' (too long): redirection + error message.
     */
    public function testChangeSeparatorInvalidReservedCharacter(): void
    {
        $toSeparator = '*';

        $session = [];
        $this->assignSessionVars($session);

        $request = $this->createMock(Request::class);
        $request
            ->expects(static::atLeastOnce())
            ->method('getParam')
            ->willReturnCallback(function (string $key) use ($toSeparator): ?string {
                return $key === 'separator' ? $toSeparator : $key;
            })
        ;
        $response = new Response();

        $this->container->conf->expects(static::never())->method('set');

        $result = $this->controller->changeSeparator($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/admin/tags'], $result->getHeader('location'));

        static::assertArrayNotHasKey(SessionManager::KEY_SUCCESS_MESSAGES, $session);
        static::assertArrayNotHasKey(SessionManager::KEY_WARNING_MESSAGES, $session);
        static::assertArrayHasKey(SessionManager::KEY_ERROR_MESSAGES, $session);
        static::assertStringStartsWith(
            'These characters are reserved and can\'t be used as tags separator',
            $session[SessionManager::KEY_ERROR_MESSAGES][0]
        );
    }
}
