<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin\ShaarePublishControllerTest;

use Shaarli\Bookmark\Bookmark;
use Shaarli\Bookmark\Exception\BookmarkNotFoundException;
use Shaarli\Front\Controller\Admin\FrontAdminControllerMockHelper;
use Shaarli\Front\Controller\Admin\ShaarePublishController;
use Shaarli\Http\HttpAccess;
use Shaarli\Security\SessionManager;
use Shaarli\TestCase;
use Shaarli\Tests\Utils\FakeRequest;
use Slim\Psr7\Response as SlimResponse;

class DisplayEditFormTest extends TestCase
{
    use FrontAdminControllerMockHelper;

    /** @var ShaarePublishController */
    protected $controller;

    public function setUp(): void
    {
        $this->createContainer();

        $this->container->set('httpAccess', $this->createMock(HttpAccess::class));
        $this->controller = new ShaarePublishController($this->container);
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

        $request = new FakeRequest();
        $response = new SlimResponse();

        $this->container->get('httpAccess')->expects(static::never())->method('getHttpResponse');
        $this->container->get('httpAccess')->expects(static::never())->method('getCurlDownloadCallback');

        $this->container->get('bookmarkService')
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
        static::assertSame(implode('@', $tags) . '@', $assignedVariables['link']['tags']);
        static::assertTrue($assignedVariables['link']['private']);
        static::assertSame($createdAt, $assignedVariables['link']['created']);
    }

    /**
     * Test displaying bookmark edit form
     * Invalid ID provided.
     */
    public function testDisplayEditFormInvalidId(): void
    {
        $id = 'invalid';

        $request = new FakeRequest();
        $response = new SlimResponse();

        $this->container->get('sessionManager')
            ->expects(static::once())
            ->method('setSessionParameter')
            ->with(SessionManager::KEY_ERROR_MESSAGES, ['Bookmark with identifier invalid could not be found.'])
        ;

        $result = $this->controller->displayEditForm($request, $response, ['id' => $id]);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/'], $result->getHeader('location'));
    }

    /**
     * Test displaying bookmark edit form
     * ID not provided.
     */
    public function testDisplayEditFormIdNotProvided(): void
    {
        $request = new FakeRequest();
        $response = new SlimResponse();

        $this->container->get('sessionManager')
            ->expects(static::once())
            ->method('setSessionParameter')
            ->with(SessionManager::KEY_ERROR_MESSAGES, ['Bookmark with identifier  could not be found.'])
        ;

        $result = $this->controller->displayEditForm($request, $response, []);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/'], $result->getHeader('location'));
    }

    /**
     * Test displaying bookmark edit form
     * Bookmark not found.
     */
    public function testDisplayEditFormBookmarkNotFound(): void
    {
        $id = 123;

        $request = new FakeRequest();
        $response = new SlimResponse();

        $this->container->get('bookmarkService')
            ->expects(static::once())
            ->method('get')
            ->with($id)
            ->willThrowException(new BookmarkNotFoundException())
        ;

        $this->container->get('sessionManager')
            ->expects(static::once())
            ->method('setSessionParameter')
            ->with(SessionManager::KEY_ERROR_MESSAGES, ['Bookmark with identifier 123 could not be found.'])
        ;

        $result = $this->controller->displayEditForm($request, $response, ['id' => (string) $id]);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/'], $result->getHeader('location'));
    }
}
