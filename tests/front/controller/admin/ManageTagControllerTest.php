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
use Shaarli\Tests\Utils\FakeRequest;
use Slim\Psr7\Response as SlimResponse;
use Slim\Psr7\Uri;


class ManageTagControllerTest extends TestCase
{
    use FrontAdminControllerMockHelper;

    /** @var ManageTagController */
    protected $controller;

    public function setUp(): void
    {
        $this->createContainer();

        $this->controller = new ManageTagController($this->container);
    }

    /**
     * Test displaying manage tag page
     */
    public function testIndex(): void
    {
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $request = (new FakeRequest(
            'GET',
            (new Uri('', ''))
                ->withQuery('fromtag=fromtag')
        ));
        $response = new SlimResponse();

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

        $this->container->set('conf', $this->createMock(ConfigManager::class));
        $this->container->get('conf')->method('get')->willReturnCallback(function (string $key) {
            return $key === 'general.tags_separator' ? ' ' : $key;
        });

        $request = new FakeRequest();
        $response = new SlimResponse();

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

        $requestParameters = [
            'renametag' => 'rename',
            'fromtag' => 'old-tag',
            'totag' => 'new-tag',
        ];
        $request = (new FakeRequest(
            'POST',
            (new Uri('', ''))
        ))->withParsedBody($requestParameters);
        $response = new SlimResponse();

        $bookmark1 = $this->createMock(Bookmark::class);
        $bookmark2 = $this->createMock(Bookmark::class);
        $this->container->get('bookmarkService')
            ->expects(static::once())
            ->method('search')
            ->with(['searchtags' => 'old-tag'], BookmarkFilter::$ALL, true)
            ->willReturnCallback(function () use ($bookmark1, $bookmark2): SearchResult {
                $bookmark1->expects(static::once())->method('renameTag')->with('old-tag', 'new-tag');
                $bookmark2->expects(static::once())->method('renameTag')->with('old-tag', 'new-tag');

                return SearchResult::getSearchResult([$bookmark1, $bookmark2]);
            })
        ;
        $this->container->get('bookmarkService')
            ->expects(static::exactly(2))
            ->method('set')
            ->withConsecutive([$bookmark1, false], [$bookmark2, false])
        ;
        $this->container->get('bookmarkService')->expects(static::once())->method('save');

        $result = $this->controller->save($request, $response);

        static::assertSame(302, $result->getStatusCode());
        $a = $result->getHeader('location');
        static::assertSame(['/subfolder/?searchtags=new-tag'], $result->getHeader('location'));

        static::assertArrayNotHasKey(SessionManager::KEY_ERROR_MESSAGES, $session);
        static::assertArrayNotHasKey(SessionManager::KEY_WARNING_MESSAGES, $session);
        static::assertArrayHasKey(SessionManager::KEY_SUCCESS_MESSAGES, $session);
        static::assertSame(['The tag was renamed in 2 bookmarks.'], $session[SessionManager::KEY_SUCCESS_MESSAGES]);
    }

    /**
     * Test posting a tag update - delete tag - valid info provided.
     */
    public function testSaveDeleteTagValid(): void
    {
        $session = [];
        $this->assignSessionVars($session);

        $requestParameters = [
            'deletetag' => 'delete',
            'fromtag' => 'old-tag',
        ];
        $request = (new FakeRequest(
            'POST',
            (new Uri('', ''))
        ))->withParsedBody($requestParameters);
        $response = new SlimResponse();

        $bookmark1 = $this->createMock(Bookmark::class);
        $bookmark2 = $this->createMock(Bookmark::class);
        $this->container->get('bookmarkService')
            ->expects(static::once())
            ->method('search')
            ->with(['searchtags' => 'old-tag'], BookmarkFilter::$ALL, true)
            ->willReturnCallback(function () use ($bookmark1, $bookmark2): SearchResult {
                $bookmark1->expects(static::once())->method('deleteTag')->with('old-tag');
                $bookmark2->expects(static::once())->method('deleteTag')->with('old-tag');

                return SearchResult::getSearchResult([$bookmark1, $bookmark2]);
            })
        ;
        $this->container->get('bookmarkService')
            ->expects(static::exactly(2))
            ->method('set')
            ->withConsecutive([$bookmark1, false], [$bookmark2, false])
        ;
        $this->container->get('bookmarkService')->expects(static::once())->method('save');

        $result = $this->controller->save($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/admin/tags'], $result->getHeader('location'));

        static::assertArrayNotHasKey(SessionManager::KEY_ERROR_MESSAGES, $session);
        static::assertArrayNotHasKey(SessionManager::KEY_WARNING_MESSAGES, $session);
        static::assertArrayHasKey(SessionManager::KEY_SUCCESS_MESSAGES, $session);
        static::assertSame(['The tag was removed from 2 bookmarks.'], $session[SessionManager::KEY_SUCCESS_MESSAGES]);
    }

    /**
     * Test posting a tag update - wrong token.
     */
    public function testSaveWrongToken(): void
    {
        $this->container->set('sessionManager', $this->createMock(SessionManager::class));
        $this->container->get('sessionManager')->method('checkToken')->willReturn(false);

        $this->container->get('conf')->expects(static::never())->method('set');
        $this->container->get('conf')->expects(static::never())->method('write');

        $request = new FakeRequest();
        $response = new SlimResponse();

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
        $request = (new FakeRequest(
            'POST',
            (new Uri('', ''))
        ))->withParsedBody($requestParameters);
        $response = new SlimResponse();

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
        $request = (new FakeRequest(
            'POST',
            (new Uri('', ''))
        ))->withParsedBody($requestParameters);
        $response = new SlimResponse();

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
        $request = (new FakeRequest(
            'POST',
            (new Uri('', ''))
        ))->withParsedBody($requestParameters);
        $response = new SlimResponse();

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

        $request = (new FakeRequest(
            'POST',
            (new Uri('', ''))
        ))->withParsedBody(['separator' => $toSeparator]);

        $response = new SlimResponse();

        $this->container->get('conf')
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

        $request = (new FakeRequest(
            'POST',
            (new Uri('', ''))
        ))->withParsedBody(['separator' => $toSeparator]);
        $response = new SlimResponse();

        $this->container->get('conf')->expects(static::never())->method('set');

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

        $request = (new FakeRequest(
            'POST',
            (new Uri('', ''))
        ))->withParsedBody(['separator' => $toSeparator]);
        $response = new SlimResponse();

        $this->container->get('conf')->expects(static::never())->method('set');

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
