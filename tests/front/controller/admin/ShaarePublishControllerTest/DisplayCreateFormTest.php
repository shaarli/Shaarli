<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin\ShaarePublishControllerTest;

use Shaarli\Bookmark\Bookmark;
use Shaarli\Config\ConfigManager;
use Shaarli\Front\Controller\Admin\FrontAdminControllerMockHelper;
use Shaarli\Front\Controller\Admin\ShaarePublishController;
use Shaarli\Http\HttpAccess;
use Shaarli\Http\MetadataRetriever;
use Shaarli\TestCase;
use Shaarli\Tests\Utils\FakeRequest;

class DisplayCreateFormTest extends TestCase
{
    use FrontAdminControllerMockHelper;

    /** @var ShaarePublishController */
    protected $controller;

    public function setUp(): void
    {
        $this->initRequestResponseFactories();
        $this->createContainer();

        $this->container->set('httpAccess', $this->createMock(HttpAccess::class));
        $this->container->set('metadataRetriever', $this->createMock(MetadataRetriever::class));
        $this->controller = new ShaarePublishController($this->container);
    }

    /**
     * Test displaying bookmark create form
     * Ensure that every step of the standard workflow works properly.
     */
    public function testDisplayCreateFormWithUrlAndWithMetadataRetrieval(): void
    {
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $url = 'http://url.tld/other?part=3&utm_ad=pay#hash';
        $expectedUrl = str_replace('&utm_ad=pay', '', $url);
        $remoteTitle = 'Remote Title';
        $remoteDesc = 'Sometimes the meta description is relevant.';
        $remoteTags = 'abc def';
        $referer = 'http://shaarli/subfolder/controller/?searchtag=abc';

        $query = http_build_query(['post' => $url]);
        $serverParams = ['HTTP_REFERER' => $referer];
        $request = $this->serverRequestFactory->createServerRequest('GET', 'http://shaarli?' . $query, $serverParams);

        /*$request->method('getParam')->willReturnCallback(function (string $key) use ($url): ?string {
            return $key === 'post' ? $url : null;
        });*/
        $response = $this->responseFactory->createResponse();

        $this->container->set('conf', $this->createMock(ConfigManager::class));
        $this->container->get('conf')->method('get')->willReturnCallback(function (string $param, $default) {
            if ($param === 'general.enable_async_metadata') {
                return false;
            }

            return $default;
        });

        $this->container->get('metadataRetriever')->expects(static::once())->method('retrieve')->willReturn([
            'title' => $remoteTitle,
            'description' => $remoteDesc,
            'tags' => $remoteTags,
        ]);

        $this->container->get('bookmarkService')
            ->expects(static::once())
            ->method('bookmarksCountPerTag')
            ->willReturn($tags = ['tag1' => 2, 'tag2' => 1])
        ;

        // Make sure that PluginManager hook is triggered
        $this->container->get('pluginManager')
            ->expects(static::atLeastOnce())
            ->method('executeHooks')
            ->withConsecutive(['render_editlink'], ['render_includes'])
            ->willReturnCallback(function (string $hook, array $data) use ($remoteTitle, $remoteDesc): array {
                if ('render_editlink' === $hook) {
                    static::assertSame($remoteTitle, $data['link']['title']);
                    static::assertSame($remoteDesc, $data['link']['description']);
                }

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
        static::assertSame($remoteTags . ' ', $assignedVariables['link']['tags']);
        static::assertFalse($assignedVariables['link']['private']);

        static::assertTrue($assignedVariables['link_is_new']);
        static::assertSame($referer, $assignedVariables['http_referer']);
        static::assertSame($tags, $assignedVariables['tags']);
        static::assertArrayHasKey('source', $assignedVariables);
        static::assertArrayHasKey('default_private_links', $assignedVariables);
        static::assertArrayHasKey('async_metadata', $assignedVariables);
        static::assertArrayHasKey('retrieve_description', $assignedVariables);
    }

    /**
     * Test displaying bookmark create form without any external metadata retrieval attempt
     */
    public function testDisplayCreateFormWithUrlAndWithoutMetadata(): void
    {

        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $url = 'http://url.tld/other?part=3&utm_ad=pay#hash';
        $referer = 'http://shaarli/subfolder/controller/?searchtag=abc';
        $expectedUrl = str_replace('&utm_ad=pay', '', $url);

        $query = http_build_query(['post' => $url]);
        $serverParams = ['HTTP_REFERER' => $referer];
        $request = $this->serverRequestFactory->createServerRequest('GET', 'http://shaarli?' . $query, $serverParams);

        $response = $this->responseFactory->createResponse();

        $this->container->get('metadataRetriever')->expects(static::never())->method('retrieve');

        $this->container->get('bookmarkService')
            ->expects(static::once())
            ->method('bookmarksCountPerTag')
            ->willReturn($tags = ['tag1' => 2, 'tag2' => 1])
        ;

        // Make sure that PluginManager hook is triggered
        $this->container->get('pluginManager')
            ->expects(static::atLeastOnce())
            ->method('executeHooks')
            ->withConsecutive(['render_editlink'], ['render_includes'])
            ->willReturnCallback(function (string $hook, array $data): array {
                if ('render_editlink' === $hook) {
                    static::assertSame('', $data['link']['title']);
                    static::assertSame('', $data['link']['description']);
                }

                return $data;
            })
        ;

        $result = $this->controller->displayCreateForm($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('editlink', (string) $result->getBody());

        static::assertSame('Shaare - Shaarli', $assignedVariables['pagetitle']);

        static::assertSame($expectedUrl, $assignedVariables['link']['url']);
        static::assertSame('', $assignedVariables['link']['title']);
        static::assertSame('', $assignedVariables['link']['description']);
        static::assertSame('', $assignedVariables['link']['tags']);
        static::assertFalse($assignedVariables['link']['private']);

        static::assertTrue($assignedVariables['link_is_new']);
        static::assertSame($referer, $assignedVariables['http_referer']);
        static::assertSame($tags, $assignedVariables['tags']);
        static::assertArrayHasKey('source', $assignedVariables);
        static::assertArrayHasKey('default_private_links', $assignedVariables);
        static::assertArrayHasKey('async_metadata', $assignedVariables);
        static::assertArrayHasKey('retrieve_description', $assignedVariables);
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
            'tags' => 'abc@def',
            'private' => '1',
            'source' => 'apps',
        ];
        $expectedUrl = str_replace('&utm_ad=pay', '', $parameters['post']);

        $query = http_build_query($parameters);
        $request = $this->requestFactory->createRequest('GET', 'http://shaarli?' . $query);
        $response = $this->responseFactory->createResponse();

        $result = $this->controller->displayCreateForm($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('editlink', (string) $result->getBody());

        static::assertSame('Shaare - Shaarli', $assignedVariables['pagetitle']);

        static::assertSame($expectedUrl, $assignedVariables['link']['url']);
        static::assertSame($parameters['title'], $assignedVariables['link']['title']);
        static::assertSame($parameters['description'], $assignedVariables['link']['description']);
        static::assertSame($parameters['tags'] . '@', $assignedVariables['link']['tags']);
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

        $request = $this->requestFactory->createRequest('GET', 'http://shaarli');
        $response = $this->responseFactory->createResponse();

        $this->container->get('httpAccess')->expects(static::never())->method('getHttpResponse');
        $this->container->get('httpAccess')->expects(static::never())->method('getCurlDownloadCallback');

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
        $query = http_build_query(['post' => $url]);
        $request = $this->requestFactory->createRequest('GET', 'http://shaarli?' . $query);
        $response = $this->responseFactory->createResponse();

        $this->container->get('httpAccess')->expects(static::never())->method('getHttpResponse');
        $this->container->get('httpAccess')->expects(static::never())->method('getCurlDownloadCallback');

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

        $this->container->set('conf', $this->createMock(ConfigManager::class));
        $this->container->get('conf')
            ->expects(static::atLeastOnce())
            ->method('get')->willReturnCallback(function (string $key): ?string {
                if ($key === 'formatter') {
                    return 'markdown';
                }

                return $key;
            })
        ;

        $request = $this->requestFactory->createRequest('GET', 'http://shaarli');
        $response = $this->responseFactory->createResponse();

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

        $query = http_build_query(['post' => $url]);
        $request = $this->requestFactory->createRequest('GET', 'http://shaarli?' . $query);
        $response = $this->responseFactory->createResponse();

        $this->container->get('httpAccess')->expects(static::never())->method('getHttpResponse');
        $this->container->get('httpAccess')->expects(static::never())->method('getCurlDownloadCallback');

        $this->container->get('bookmarkService')
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
        static::assertSame(implode('@', $tags) . '@', $assignedVariables['link']['tags']);
        static::assertTrue($assignedVariables['link']['private']);
        static::assertSame($createdAt, $assignedVariables['link']['created']);
    }
}
