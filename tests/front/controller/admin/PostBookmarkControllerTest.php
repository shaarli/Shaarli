<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin;

use PHPUnit\Framework\TestCase;
use Shaarli\Bookmark\Bookmark;
use Shaarli\Config\ConfigManager;
use Shaarli\Front\Exception\WrongTokenException;
use Shaarli\Http\HttpAccess;
use Shaarli\Security\SessionManager;
use Shaarli\Thumbnailer;
use Slim\Http\Request;
use Slim\Http\Response;

class PostBookmarkControllerTest extends TestCase
{
    use FrontAdminControllerMockHelper;

    /** @var PostBookmarkController */
    protected $controller;

    public function setUp(): void
    {
        $this->createContainer();

        $this->container->httpAccess = $this->createMock(HttpAccess::class);
        $this->controller = new PostBookmarkController($this->container);
    }

    /**
     * Test displaying add link page
     */
    public function testAddShaare(): void
    {
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $request = $this->createMock(Request::class);
        $response = new Response();

        $result = $this->controller->addShaare($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('addlink', (string) $result->getBody());

        static::assertSame('Shaare a new link - Shaarli', $assignedVariables['pagetitle']);
    }

    /**
     * Test displaying bookmark create form
     * Ensure that every step of the standard workflow works properly.
     */
    public function testDisplayCreateFormWithUrl(): void
    {
        $this->container->environment = [
            'HTTP_REFERER' => $referer = 'http://shaarli/subfolder/controller/?searchtag=abc'
        ];

        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $url = 'http://url.tld/other?part=3&utm_ad=pay#hash';
        $expectedUrl = str_replace('&utm_ad=pay', '', $url);
        $remoteTitle = 'Remote Title';
        $remoteDesc = 'Sometimes the meta description is relevant.';
        $remoteTags = 'abc def';

        $request = $this->createMock(Request::class);
        $request->method('getParam')->willReturnCallback(function (string $key) use ($url): ?string {
            return $key === 'post' ? $url : null;
        });
        $response = new Response();

        $this->container->httpAccess
            ->expects(static::once())
            ->method('getCurlDownloadCallback')
            ->willReturnCallback(
                function (&$charset, &$title, &$description, &$tags) use (
                    $remoteTitle,
                    $remoteDesc,
                    $remoteTags
                ): callable {
                    return function () use (
                        &$charset,
                        &$title,
                        &$description,
                        &$tags,
                        $remoteTitle,
                        $remoteDesc,
                        $remoteTags
                    ): void {
                        $charset = 'ISO-8859-1';
                        $title = $remoteTitle;
                        $description = $remoteDesc;
                        $tags = $remoteTags;
                    };
                }
            )
        ;
        $this->container->httpAccess
            ->expects(static::once())
            ->method('getHttpResponse')
            ->with($expectedUrl, 30, 4194304)
            ->willReturnCallback(function($url, $timeout, $maxBytes, $callback): void {
                $callback();
            })
        ;

        $this->container->bookmarkService
            ->expects(static::once())
            ->method('bookmarksCountPerTag')
            ->willReturn($tags = ['tag1' => 2, 'tag2' => 1])
        ;

        // Make sure that PluginManager hook is triggered
        $this->container->pluginManager
            ->expects(static::at(0))
            ->method('executeHooks')
            ->willReturnCallback(function (string $hook, array $data) use ($remoteTitle, $remoteDesc): array {
                static::assertSame('render_editlink', $hook);
                static::assertSame($remoteTitle, $data['link']['title']);
                static::assertSame($remoteDesc, $data['link']['description']);

                return $data;
            })
        ;

        $result = $this->controller->displayCreateForm($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('editlink', (string) $result->getBody());

        static::assertSame('Shaare - Shaarli', $assignedVariables['pagetitle']);

        static::assertSame($expectedUrl, $assignedVariables['link']['url']);
        static::assertSame($remoteTitle, $assignedVariables['link']['title']);
        static::assertSame($remoteDesc, $assignedVariables['link']['description']);
        static::assertSame($remoteTags, $assignedVariables['link']['tags']);
        static::assertFalse($assignedVariables['link']['private']);

        static::assertTrue($assignedVariables['link_is_new']);
        static::assertSame($referer, $assignedVariables['http_referer']);
        static::assertSame($tags, $assignedVariables['tags']);
        static::assertArrayHasKey('source', $assignedVariables);
        static::assertArrayHasKey('default_private_links', $assignedVariables);
    }

    /**
     * Test displaying bookmark create form
     * Ensure all available query parameters are handled properly.
     */
    public function testDisplayCreateFormWithFullParameters(): void
    {
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $parameters = [
            'post' => 'http://url.tld/other?part=3&utm_ad=pay#hash',
            'title' => 'Provided Title',
            'description' => 'Provided description.',
            'tags' => 'abc def',
            'private' => '1',
            'source' => 'apps',
        ];
        $expectedUrl = str_replace('&utm_ad=pay', '', $parameters['post']);

        $request = $this->createMock(Request::class);
        $request
            ->method('getParam')
            ->willReturnCallback(function (string $key) use ($parameters): ?string {
            return $parameters[$key] ?? null;
        });
        $response = new Response();

        $result = $this->controller->displayCreateForm($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('editlink', (string) $result->getBody());

        static::assertSame('Shaare - Shaarli', $assignedVariables['pagetitle']);

        static::assertSame($expectedUrl, $assignedVariables['link']['url']);
        static::assertSame($parameters['title'], $assignedVariables['link']['title']);
        static::assertSame($parameters['description'], $assignedVariables['link']['description']);
        static::assertSame($parameters['tags'], $assignedVariables['link']['tags']);
        static::assertTrue($assignedVariables['link']['private']);
        static::assertTrue($assignedVariables['link_is_new']);
        static::assertSame($parameters['source'], $assignedVariables['source']);
    }

    /**
     * Test displaying bookmark create form
     * Without any parameter.
     */
    public function testDisplayCreateFormEmpty(): void
    {
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $request = $this->createMock(Request::class);
        $response = new Response();

        $this->container->httpAccess->expects(static::never())->method('getHttpResponse');
        $this->container->httpAccess->expects(static::never())->method('getCurlDownloadCallback');

        $result = $this->controller->displayCreateForm($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('editlink', (string) $result->getBody());
        static::assertSame('', $assignedVariables['link']['url']);
        static::assertSame('Note: ', $assignedVariables['link']['title']);
        static::assertSame('', $assignedVariables['link']['description']);
        static::assertSame('', $assignedVariables['link']['tags']);
        static::assertFalse($assignedVariables['link']['private']);
        static::assertTrue($assignedVariables['link_is_new']);
    }

    /**
     * Test displaying bookmark create form
     * URL not using HTTP protocol: do not try to retrieve the title
     */
    public function testDisplayCreateFormNotHttp(): void
    {
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $url = 'magnet://kubuntu.torrent';
        $request = $this->createMock(Request::class);
        $request
            ->method('getParam')
            ->willReturnCallback(function (string $key) use ($url): ?string {
                return $key === 'post' ? $url : null;
            });
        $response = new Response();

        $this->container->httpAccess->expects(static::never())->method('getHttpResponse');
        $this->container->httpAccess->expects(static::never())->method('getCurlDownloadCallback');

        $result = $this->controller->displayCreateForm($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('editlink', (string) $result->getBody());
        static::assertSame($url, $assignedVariables['link']['url']);
        static::assertTrue($assignedVariables['link_is_new']);
    }

    /**
     * Test displaying bookmark create form
     * When markdown formatter is enabled, the no markdown tag should be added to existing tags.
     */
    public function testDisplayCreateFormWithMarkdownEnabled(): void
    {
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $this->container->conf = $this->createMock(ConfigManager::class);
        $this->container->conf
            ->expects(static::atLeastOnce())
            ->method('get')->willReturnCallback(function (string $key): ?string {
                if ($key === 'formatter') {
                    return 'markdown';
                }

                return $key;
            })
        ;

        $request = $this->createMock(Request::class);
        $response = new Response();

        $result = $this->controller->displayCreateForm($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('editlink', (string) $result->getBody());
        static::assertSame(['nomarkdown' => 1], $assignedVariables['tags']);
    }

    /**
     * Test displaying bookmark create form
     * When an existing URL is submitted, we want to edit the existing link.
     */
    public function testDisplayCreateFormWithExistingUrl(): void
    {
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $url = 'http://url.tld/other?part=3&utm_ad=pay#hash';
        $expectedUrl = str_replace('&utm_ad=pay', '', $url);

        $request = $this->createMock(Request::class);
        $request
            ->method('getParam')
            ->willReturnCallback(function (string $key) use ($url): ?string {
                return $key === 'post' ? $url : null;
            });
        $response = new Response();

        $this->container->httpAccess->expects(static::never())->method('getHttpResponse');
        $this->container->httpAccess->expects(static::never())->method('getCurlDownloadCallback');

        $this->container->bookmarkService
            ->expects(static::once())
            ->method('findByUrl')
            ->with($expectedUrl)
            ->willReturn(
                (new Bookmark())
                    ->setId($id = 23)
                    ->setUrl($expectedUrl)
                    ->setTitle($title = 'Bookmark Title')
                    ->setDescription($description = 'Bookmark description.')
                    ->setTags($tags = ['abc', 'def'])
                    ->setPrivate(true)
                    ->setCreated($createdAt = new \DateTime('2020-06-10 18:45:44'))
            )
        ;

        $result = $this->controller->displayCreateForm($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('editlink', (string) $result->getBody());

        static::assertSame('Edit Shaare - Shaarli', $assignedVariables['pagetitle']);
        static::assertFalse($assignedVariables['link_is_new']);

        static::assertSame($id, $assignedVariables['link']['id']);
        static::assertSame($expectedUrl, $assignedVariables['link']['url']);
        static::assertSame($title, $assignedVariables['link']['title']);
        static::assertSame($description, $assignedVariables['link']['description']);
        static::assertSame(implode(' ', $tags), $assignedVariables['link']['tags']);
        static::assertTrue($assignedVariables['link']['private']);
        static::assertSame($createdAt, $assignedVariables['link']['created']);
    }

    /**
     * Test displaying bookmark edit form
     * When an existing ID is provided, ensure that default workflow works properly.
     */
    public function testDisplayEditFormDefault(): void
    {
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $id = 11;

        $request = $this->createMock(Request::class);
        $response = new Response();

        $this->container->httpAccess->expects(static::never())->method('getHttpResponse');
        $this->container->httpAccess->expects(static::never())->method('getCurlDownloadCallback');

        $this->container->bookmarkService
            ->expects(static::once())
            ->method('get')
            ->with($id)
            ->willReturn(
                (new Bookmark())
                    ->setId($id)
                    ->setUrl($url = 'http://domain.tld')
                    ->setTitle($title = 'Bookmark Title')
                    ->setDescription($description = 'Bookmark description.')
                    ->setTags($tags = ['abc', 'def'])
                    ->setPrivate(true)
                    ->setCreated($createdAt = new \DateTime('2020-06-10 18:45:44'))
            )
        ;

        $result = $this->controller->displayEditForm($request, $response, ['id' => (string) $id]);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('editlink', (string) $result->getBody());

        static::assertSame('Edit Shaare - Shaarli', $assignedVariables['pagetitle']);
        static::assertFalse($assignedVariables['link_is_new']);

        static::assertSame($id, $assignedVariables['link']['id']);
        static::assertSame($url, $assignedVariables['link']['url']);
        static::assertSame($title, $assignedVariables['link']['title']);
        static::assertSame($description, $assignedVariables['link']['description']);
        static::assertSame(implode(' ', $tags), $assignedVariables['link']['tags']);
        static::assertTrue($assignedVariables['link']['private']);
        static::assertSame($createdAt, $assignedVariables['link']['created']);
    }

    /**
     * Test save a new bookmark
     */
    public function testSaveBookmark(): void
    {
        $id = 21;
        $parameters = [
            'lf_url' => 'http://url.tld/other?part=3#hash',
            'lf_title' => 'Provided Title',
            'lf_description' => 'Provided description.',
            'lf_tags' => 'abc def',
            'lf_private' => '1',
            'returnurl' => 'http://shaarli.tld/subfolder/add-shaare'
        ];

        $request = $this->createMock(Request::class);
        $request
            ->method('getParam')
            ->willReturnCallback(function (string $key) use ($parameters): ?string {
                return $parameters[$key] ?? null;
            })
        ;
        $response = new Response();

        $checkBookmark = function (Bookmark $bookmark) use ($parameters) {
            static::assertSame($parameters['lf_url'], $bookmark->getUrl());
            static::assertSame($parameters['lf_title'], $bookmark->getTitle());
            static::assertSame($parameters['lf_description'], $bookmark->getDescription());
            static::assertSame($parameters['lf_tags'], $bookmark->getTagsString());
            static::assertTrue($bookmark->isPrivate());
        };

        $this->container->bookmarkService
            ->expects(static::once())
            ->method('addOrSet')
            ->willReturnCallback(function (Bookmark $bookmark, bool $save) use ($checkBookmark, $id): void {
                static::assertFalse($save);

                $checkBookmark($bookmark);

                $bookmark->setId($id);
            })
        ;
        $this->container->bookmarkService
            ->expects(static::once())
            ->method('set')
            ->willReturnCallback(function (Bookmark $bookmark, bool $save) use ($checkBookmark, $id): void {
                static::assertTrue($save);

                $checkBookmark($bookmark);

                static::assertSame($id, $bookmark->getId());
            })
        ;

        // Make sure that PluginManager hook is triggered
        $this->container->pluginManager
            ->expects(static::at(0))
            ->method('executeHooks')
            ->willReturnCallback(function (string $hook, array $data) use ($parameters, $id): array {
                static::assertSame('save_link', $hook);

                static::assertSame($id, $data['id']);
                static::assertSame($parameters['lf_url'], $data['url']);
                static::assertSame($parameters['lf_title'], $data['title']);
                static::assertSame($parameters['lf_description'], $data['description']);
                static::assertSame($parameters['lf_tags'], $data['tags']);
                static::assertTrue($data['private']);

                return $data;
            })
        ;

        $result = $this->controller->save($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertRegExp('@/subfolder/#\w{6}@', $result->getHeader('location')[0]);
    }


    /**
     * Test save an existing bookmark
     */
    public function testSaveExistingBookmark(): void
    {
        $id = 21;
        $parameters = [
            'lf_id' => (string) $id,
            'lf_url' => 'http://url.tld/other?part=3#hash',
            'lf_title' => 'Provided Title',
            'lf_description' => 'Provided description.',
            'lf_tags' => 'abc def',
            'lf_private' => '1',
            'returnurl' => 'http://shaarli.tld/subfolder/?page=2'
        ];

        $request = $this->createMock(Request::class);
        $request
            ->method('getParam')
            ->willReturnCallback(function (string $key) use ($parameters): ?string {
                return $parameters[$key] ?? null;
            })
        ;
        $response = new Response();

        $checkBookmark = function (Bookmark $bookmark) use ($parameters, $id) {
            static::assertSame($id, $bookmark->getId());
            static::assertSame($parameters['lf_url'], $bookmark->getUrl());
            static::assertSame($parameters['lf_title'], $bookmark->getTitle());
            static::assertSame($parameters['lf_description'], $bookmark->getDescription());
            static::assertSame($parameters['lf_tags'], $bookmark->getTagsString());
            static::assertTrue($bookmark->isPrivate());
        };

        $this->container->bookmarkService->expects(static::atLeastOnce())->method('exists')->willReturn(true);
        $this->container->bookmarkService
            ->expects(static::once())
            ->method('get')
            ->willReturn((new Bookmark())->setId($id)->setUrl('http://other.url'))
        ;
        $this->container->bookmarkService
            ->expects(static::once())
            ->method('addOrSet')
            ->willReturnCallback(function (Bookmark $bookmark, bool $save) use ($checkBookmark, $id): void {
                static::assertFalse($save);

                $checkBookmark($bookmark);
            })
        ;
        $this->container->bookmarkService
            ->expects(static::once())
            ->method('set')
            ->willReturnCallback(function (Bookmark $bookmark, bool $save) use ($checkBookmark, $id): void {
                static::assertTrue($save);

                $checkBookmark($bookmark);

                static::assertSame($id, $bookmark->getId());
            })
        ;

        // Make sure that PluginManager hook is triggered
        $this->container->pluginManager
            ->expects(static::at(0))
            ->method('executeHooks')
            ->willReturnCallback(function (string $hook, array $data) use ($parameters, $id): array {
                static::assertSame('save_link', $hook);

                static::assertSame($id, $data['id']);
                static::assertSame($parameters['lf_url'], $data['url']);
                static::assertSame($parameters['lf_title'], $data['title']);
                static::assertSame($parameters['lf_description'], $data['description']);
                static::assertSame($parameters['lf_tags'], $data['tags']);
                static::assertTrue($data['private']);

                return $data;
            })
        ;

        $result = $this->controller->save($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertRegExp('@/subfolder/\?page=2#\w{6}@', $result->getHeader('location')[0]);
    }

    /**
     * Test save a bookmark - try to retrieve the thumbnail
     */
    public function testSaveBookmarkWithThumbnail(): void
    {
        $parameters = ['lf_url' => 'http://url.tld/other?part=3#hash'];

        $request = $this->createMock(Request::class);
        $request
            ->method('getParam')
            ->willReturnCallback(function (string $key) use ($parameters): ?string {
                return $parameters[$key] ?? null;
            })
        ;
        $response = new Response();

        $this->container->conf = $this->createMock(ConfigManager::class);
        $this->container->conf->method('get')->willReturnCallback(function (string $key, $default) {
            return $key === 'thumbnails.mode' ? Thumbnailer::MODE_ALL : $default;
        });

        $this->container->thumbnailer = $this->createMock(Thumbnailer::class);
        $this->container->thumbnailer
            ->expects(static::once())
            ->method('get')
            ->with($parameters['lf_url'])
            ->willReturn($thumb = 'http://thumb.url')
        ;

        $this->container->bookmarkService
            ->expects(static::once())
            ->method('addOrSet')
            ->willReturnCallback(function (Bookmark $bookmark, bool $save) use ($thumb): void {
                static::assertSame($thumb, $bookmark->getThumbnail());
            })
        ;

        $result = $this->controller->save($request, $response);

        static::assertSame(302, $result->getStatusCode());
    }

    /**
     * Change the password with a wrong existing password
     */
    public function testSaveBookmarkFromBookmarklet(): void
    {
        $parameters = ['source' => 'bookmarklet'];

        $request = $this->createMock(Request::class);
        $request
            ->method('getParam')
            ->willReturnCallback(function (string $key) use ($parameters): ?string {
                return $parameters[$key] ?? null;
            })
        ;
        $response = new Response();

        $result = $this->controller->save($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('<script>self.close();</script>', (string) $result->getBody());
    }

    /**
     * Change the password with a wrong existing password
     */
    public function testSaveBookmarkWrongToken(): void
    {
        $this->container->sessionManager = $this->createMock(SessionManager::class);
        $this->container->sessionManager->method('checkToken')->willReturn(false);

        $this->container->bookmarkService->expects(static::never())->method('addOrSet');
        $this->container->bookmarkService->expects(static::never())->method('set');

        $request = $this->createMock(Request::class);
        $response = new Response();

        $this->expectException(WrongTokenException::class);

        $this->controller->save($request, $response);
    }
}
