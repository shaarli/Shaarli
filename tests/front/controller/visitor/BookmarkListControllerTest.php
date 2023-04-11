<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Visitor;

use Shaarli\Bookmark\Bookmark;
use Shaarli\Bookmark\Exception\BookmarkNotFoundException;
use Shaarli\Bookmark\SearchResult;
use Shaarli\Config\ConfigManager;
use Shaarli\Security\LoginManager;
use Shaarli\TestCase;
use Shaarli\Tests\Utils\FakeRequest;
use Shaarli\Thumbnailer;
use Slim\Psr7\Response as SlimResponse;
use Slim\Psr7\Uri;

class BookmarkListControllerTest extends TestCase
{
    use FrontControllerMockHelper;

    /** @var BookmarkListController */
    protected $controller;

    public function setUp(): void
    {
        $this->createContainer();

        $this->controller = new BookmarkListController($this->container);
    }

    /**
     * Test rendering list of bookmarks with default parameters (first page).
     */
    public function testIndexDefaultFirstPage(): void
    {
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $request = (new FakeRequest())->withServerParams([
            'SERVER_PORT' => 80,
            'SERVER_NAME' => 'shaarli',
        ]);
        $response = new SlimResponse();

        $this->container->get('bookmarkService')
            ->expects(static::once())
            ->method('search')
            ->with(
                ['searchtags' => '', 'searchterm' => ''],
                null,
                false,
                false,
                false,
                ['offset' => 0, 'limit' => 2]
            )
            ->willReturn(SearchResult::getSearchResult([
                (new Bookmark())->setId(1)->setUrl('http://url1.tld')->setTitle('Title 1'),
                (new Bookmark())->setId(2)->setUrl('http://url2.tld')->setTitle('Title 2'),
                (new Bookmark())->setId(3)->setUrl('http://url3.tld')->setTitle('Title 3'),
            ], 0, 2));

        $this->container->get('sessionManager')
            ->method('getSessionParameter')
            ->willReturnCallback(function (string $parameter, $default = null) {
                if ('LINKS_PER_PAGE' === $parameter) {
                    return 2;
                }

                return $default;
            })
        ;

        $result = $this->controller->index($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('linklist', (string) $result->getBody());

        static::assertSame('Shaarli', $assignedVariables['pagetitle']);
        static::assertSame('?page=2', $assignedVariables['previous_page_url']);
        static::assertSame('', $assignedVariables['next_page_url']);
        static::assertSame(2, $assignedVariables['page_max']);
        static::assertSame('', $assignedVariables['search_tags']);
        static::assertSame(3, $assignedVariables['result_count']);
        static::assertSame(1, $assignedVariables['page_current']);
        static::assertSame('', $assignedVariables['search_term']);
        static::assertNull($assignedVariables['visibility']);
        static::assertCount(2, $assignedVariables['links']);

        $link = $assignedVariables['links'][0];

        static::assertSame(1, $link['id']);
        static::assertSame('http://url1.tld', $link['url']);
        static::assertSame('Title 1', $link['title']);

        $link = $assignedVariables['links'][1];

        static::assertSame(2, $link['id']);
        static::assertSame('http://url2.tld', $link['url']);
        static::assertSame('Title 2', $link['title']);
    }

    /**
     * Test rendering list of bookmarks with default parameters (second page).
     */
    public function testIndexDefaultSecondPage(): void
    {
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $request = (new FakeRequest(
            'GET',
            (new Uri('', ''))->withQuery('page=2')
        ))->withServerParams([
            'SERVER_PORT' => 80,
            'SERVER_NAME' => 'shaarli',
        ]);
        $response = new SlimResponse();

        $this->container->get('bookmarkService')
            ->expects(static::once())
            ->method('search')
            ->with(
                ['searchtags' => '', 'searchterm' => ''],
                null,
                false,
                false,
                false,
                ['offset' => 2, 'limit' => 2]
            )
            ->willReturn(SearchResult::getSearchResult([
                (new Bookmark())->setId(1)->setUrl('http://url1.tld')->setTitle('Title 1'),
                (new Bookmark())->setId(2)->setUrl('http://url2.tld')->setTitle('Title 2'),
                (new Bookmark())->setId(3)->setUrl('http://url3.tld')->setTitle('Title 3'),
            ], 2, 2))
        ;

        $this->container->get('sessionManager')
            ->method('getSessionParameter')
            ->willReturnCallback(function (string $parameter, $default = null) {
                if ('LINKS_PER_PAGE' === $parameter) {
                    return 2;
                }

                return $default;
            })
        ;

        $result = $this->controller->index($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('linklist', (string) $result->getBody());

        static::assertSame('Shaarli', $assignedVariables['pagetitle']);
        static::assertSame('', $assignedVariables['previous_page_url']);
        static::assertSame('?page=1', $assignedVariables['next_page_url']);
        static::assertSame(2, $assignedVariables['page_max']);
        static::assertSame('', $assignedVariables['search_tags']);
        static::assertSame(3, $assignedVariables['result_count']);
        static::assertSame(2, $assignedVariables['page_current']);
        static::assertSame('', $assignedVariables['search_term']);
        static::assertNull($assignedVariables['visibility']);
        static::assertCount(1, $assignedVariables['links']);

        $link = $assignedVariables['links'][2];

        static::assertSame(3, $link['id']);
        static::assertSame('http://url3.tld', $link['url']);
        static::assertSame('Title 3', $link['title']);
    }

    /**
     * Test rendering list of bookmarks with filters.
     */
    public function testIndexDefaultWithFilters(): void
    {
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $request = (new FakeRequest(
            'GET',
            (new Uri('', ''))->withQuery(
                http_build_query(['searchtags' => 'abc@def', 'searchterm' => 'ghi jkl'])
            )
        ))->withServerParams([
            'SERVER_PORT' => 80,
            'SERVER_NAME' => 'shaarli',
        ]);

        $response = new SlimResponse();

        $this->container->get('sessionManager')
            ->method('getSessionParameter')
            ->willReturnCallback(function (string $key, $default) {
                if ('LINKS_PER_PAGE' === $key) {
                    return 2;
                }
                if ('visibility' === $key) {
                    return 'private';
                }
                if ('untaggedonly' === $key) {
                    return true;
                }

                return $default;
            })
        ;

        $this->container->get('bookmarkService')
            ->expects(static::once())
            ->method('search')
            ->with(
                ['searchtags' => 'abc@def', 'searchterm' => 'ghi jkl'],
                'private',
                false,
                true,
                false,
                ['offset' => 0, 'limit' => 2]
            )
            ->willReturn(SearchResult::getSearchResult([
                (new Bookmark())->setId(1)->setUrl('http://url1.tld')->setTitle('Title 1'),
                (new Bookmark())->setId(2)->setUrl('http://url2.tld')->setTitle('Title 2'),
                (new Bookmark())->setId(3)->setUrl('http://url3.tld')->setTitle('Title 3'),
            ], 0, 2))
        ;

        $result = $this->controller->index($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('linklist', (string) $result->getBody());

        static::assertSame('Search: ghi jkl [abc] [def] - Shaarli', $assignedVariables['pagetitle']);
        static::assertSame('?page=2&searchterm=ghi+jkl&searchtags=abc%40def', $assignedVariables['previous_page_url']);
    }

    /**
     * Test displaying a permalink with valid parameters
     */
    public function testPermalinkValid(): void
    {
        $hash = 'abcdef';

        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $request = (new FakeRequest(
            'GET',
            (new Uri('', ''))
        ))->withServerParams([
            'SERVER_PORT' => 80,
            'SERVER_NAME' => 'shaarli',
        ]);
        $response = new SlimResponse();

        $this->container->get('bookmarkService')
            ->expects(static::once())
            ->method('findByHash')
            ->with($hash)
            ->willReturn((new Bookmark())->setId(123)->setTitle('Title 1')->setUrl('http://url1.tld'))
        ;

        $result = $this->controller->permalink($request, $response, ['hash' => $hash]);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('linklist', (string) $result->getBody());

        static::assertSame('Title 1 - Shaarli', $assignedVariables['pagetitle']);
        static::assertCount(1, $assignedVariables['links']);

        $link = $assignedVariables['links'][0];

        static::assertSame(123, $link['id']);
        static::assertSame('http://url1.tld', $link['url']);
        static::assertSame('Title 1', $link['title']);
    }

    /**
     * Test displaying a permalink with an unknown small hash : renders a 404 template error
     */
    public function testPermalinkNotFound(): void
    {
        $hash = 'abcdef';

        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $request = new FakeRequest();
        $response = new SlimResponse();

        $this->container->get('bookmarkService')
            ->expects(static::once())
            ->method('findByHash')
            ->with($hash)
            ->willThrowException(new BookmarkNotFoundException())
        ;

        $result = $this->controller->permalink($request, $response, ['hash' => $hash]);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('404', (string) $result->getBody());

        static::assertSame(
            'The link you are trying to reach does not exist or has been deleted.',
            $assignedVariables['error_message']
        );
    }

    /**
     * Test GET /shaare/{hash}?key={key} - Find a link by hash using a private link.
     */
    public function testPermalinkWithPrivateKey(): void
    {
        $hash = 'abcdef';
        $privateKey = 'this is a private key';

        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $request = (new FakeRequest(
            'GET',
            (new Uri('', ''))->withQuery(http_build_query(['key' => $privateKey]))
        ))->withServerParams([
            'SERVER_PORT' => 80,
            'SERVER_NAME' => 'shaarli',
        ]);
        $response = new SlimResponse();

        $this->container->get('bookmarkService')
            ->expects(static::once())
            ->method('findByHash')
            ->with($hash, $privateKey)
            ->willReturn((new Bookmark())->setId(123)->setTitle('Title 1')->setUrl('http://url1.tld'))
        ;

        $result = $this->controller->permalink($request, $response, ['hash' => $hash]);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('linklist', (string) $result->getBody());
        static::assertCount(1, $assignedVariables['links']);
    }

    /**
     * Test getting link list with thumbnail updates.
     *   -> 2 thumbnails update, only 1 datastore write
     */
    public function testThumbnailUpdateFromLinkList(): void
    {
        $request = (new FakeRequest(
            'GET',
            (new Uri('', ''))
        ))->withServerParams([
            'SERVER_PORT' => 80,
            'SERVER_NAME' => 'shaarli',
        ]);
        $response = new SlimResponse();

        $this->container->set('loginManager', $this->createMock(LoginManager::class));
        $this->container->get('loginManager')->method('isLoggedIn')->willReturn(true);

        $this->container->set('conf', $this->createMock(ConfigManager::class));
        $this->container->get('conf')
            ->method('get')
            ->willReturnCallback(function (string $key, $default) {
                if ($key === 'thumbnails.mode') {
                    return Thumbnailer::MODE_ALL;
                } elseif ($key === 'general.enable_async_metadata') {
                    return false;
                }

                return $default;
            })
        ;

        $this->container->set('thumbnailer', $this->createMock(Thumbnailer::class));
        $this->container->get('thumbnailer')
            ->expects(static::exactly(2))
            ->method('get')
            ->withConsecutive(['https://url2.tld'], ['https://url4.tld'])
        ;

        $this->container->get('bookmarkService')
            ->expects(static::once())
            ->method('search')
            ->willReturn(SearchResult::getSearchResult([
                (new Bookmark())->setId(1)->setUrl('https://url1.tld')->setTitle('Title 1')->setThumbnail(false),
                $b1 = (new Bookmark())->setId(2)->setUrl('https://url2.tld')->setTitle('Title 2'),
                (new Bookmark())->setId(3)->setUrl('https://url3.tld')->setTitle('Title 3')->setThumbnail(false),
                $b2 = (new Bookmark())->setId(2)->setUrl('https://url4.tld')->setTitle('Title 4'),
                (new Bookmark())->setId(2)->setUrl('ftp://url5.tld', ['ftp'])->setTitle('Title 5'),
            ]))
        ;
        $this->container->get('bookmarkService')
            ->expects(static::exactly(2))
            ->method('set')
            ->withConsecutive([$b1, false], [$b2, false])
        ;
        $this->container->get('bookmarkService')->expects(static::once())->method('save');

        $result = $this->controller->index($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('linklist', (string) $result->getBody());
    }

    /**
     * Test getting a permalink with thumbnail update.
     */
    public function testThumbnailUpdateFromPermalink(): void
    {
        $request = (new FakeRequest(
            'GET',
            (new Uri('', ''))
        ))->withServerParams([
            'SERVER_PORT' => 80,
            'SERVER_NAME' => 'shaarli',
        ]);
        $response = new SlimResponse();

        $this->container->set('loginManager', $this->createMock(LoginManager::class));
        $this->container->get('loginManager')->method('isLoggedIn')->willReturn(true);

        $this->container->set('conf', $this->createMock(ConfigManager::class));
        $this->container->get('conf')
            ->method('get')
            ->willReturnCallback(function (string $key, $default) {
                if ($key === 'thumbnails.mode') {
                    return Thumbnailer::MODE_ALL;
                } elseif ($key === 'general.enable_async_metadata') {
                    return false;
                }

                return $default;
            })
        ;

        $this->container->set('thumbnailer', $this->createMock(Thumbnailer::class));
        $this->container->get('thumbnailer')->expects(static::once())->method('get')
            ->withConsecutive(['https://url.tld']);

        $this->container->get('bookmarkService')
            ->expects(static::once())
            ->method('findByHash')
            ->willReturn($bookmark = (new Bookmark())->setId(2)->setUrl('https://url.tld')->setTitle('Title 1'))
        ;
        $this->container->get('bookmarkService')->expects(static::once())->method('set')->with($bookmark, true);
        $this->container->get('bookmarkService')->expects(static::never())->method('save');

        $result = $this->controller->permalink($request, $response, ['hash' => 'abc']);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('linklist', (string) $result->getBody());
    }

    /**
     * Test getting a permalink with thumbnail update with async setting: no update should run.
     */
    public function testThumbnailUpdateFromPermalinkAsync(): void
    {
        $request = (new FakeRequest(
            'GET',
            (new Uri('', ''))
        ))->withServerParams([
            'SERVER_PORT' => 80,
            'SERVER_NAME' => 'shaarli',
        ]);
        $response = new SlimResponse();

        $this->container->set('loginManager', $this->createMock(LoginManager::class));
        $this->container->get('loginManager')->method('isLoggedIn')->willReturn(true);

        $this->container->set('conf', $this->createMock(ConfigManager::class));
        $this->container->get('conf')
            ->method('get')
            ->willReturnCallback(function (string $key, $default) {
                if ($key === 'thumbnails.mode') {
                    return Thumbnailer::MODE_ALL;
                } elseif ($key === 'general.enable_async_metadata') {
                    return true;
                }

                return $default;
            })
        ;

        $this->container->set('thumbnailer', $this->createMock(Thumbnailer::class));
        $this->container->get('thumbnailer')->expects(static::never())->method('get');

        $this->container->get('bookmarkService')
            ->expects(static::once())
            ->method('findByHash')
            ->willReturn((new Bookmark())->setId(2)->setUrl('https://url.tld')->setTitle('Title 1'))
        ;
        $this->container->get('bookmarkService')->expects(static::never())->method('set');
        $this->container->get('bookmarkService')->expects(static::never())->method('save');

        $result = $this->controller->permalink($request, $response, ['hash' => 'abc']);

        static::assertSame(200, $result->getStatusCode());
    }

    /**
     * Trigger legacy controller in link list controller: permalink
     */
    public function testLegacyControllerPermalink(): void
    {
        $hash = 'abcdef';
        $request = (new FakeRequest(
            'GET',
            (new Uri('', ''))
        ))->withServerParams([
            'SERVER_PORT' => 80,
            'SERVER_NAME' => 'shaarli',
            'QUERY_STRING' => $hash,
        ]);
        $response = new SlimResponse();

        $result = $this->controller->index($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame('/subfolder/shaare/' . $hash, $result->getHeader('location')[0]);
    }

    /**
     * Trigger legacy controller in link list controller: ?do= query parameter
     */
    public function testLegacyControllerDoPage(): void
    {
        $request = (new FakeRequest(
            'GET',
            (new Uri('', ''))->withQuery(http_build_query(['do' => 'picwall']))
        ))->withServerParams([
            'SERVER_PORT' => 80,
            'SERVER_NAME' => 'shaarli'
        ]);
        $response = new SlimResponse();

        $result = $this->controller->index($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame('/subfolder/picture-wall', $result->getHeader('location')[0]);
    }

    /**
     * Trigger legacy controller in link list controller: ?do= query parameter with unknown legacy route
     */
    public function testLegacyControllerUnknownDoPage(): void
    {
        $request = (new FakeRequest(
            'GET',
            (new Uri('', ''))->withQuery(http_build_query(['do' => 'nope']))
        ))->withServerParams([
            'SERVER_PORT' => 80,
            'SERVER_NAME' => 'shaarli'
        ]);
        $response = new SlimResponse();

        $result = $this->controller->index($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('linklist', (string) $result->getBody());
    }

    /**
     * Trigger legacy controller in link list controller: other GET route (e.g. ?post)
     */
    public function testLegacyControllerGetParameter(): void
    {
        $url = 'http://url.tld';
        $request = (new FakeRequest(
            'GET',
            (new Uri('', ''))->withQuery(http_build_query(['post' => $url]))
        ))->withServerParams([
            'SERVER_PORT' => 80,
            'SERVER_NAME' => 'shaarli'
        ]);
        $response = new SlimResponse();

        $this->container->set('loginManager', $this->createMock(LoginManager::class));
        $this->container->get('loginManager')->method('isLoggedIn')->willReturn(true);

        $result = $this->controller->index($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(
            '/subfolder/admin/shaare?post=' . urlencode($url),
            $result->getHeader('location')[0]
        );
    }
}
