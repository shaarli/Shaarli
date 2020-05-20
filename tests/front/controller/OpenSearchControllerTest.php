<?php

declare(strict_types=1);

namespace front\controller;

use PHPUnit\Framework\TestCase;
use Shaarli\Bookmark\BookmarkServiceInterface;
use Shaarli\Container\ShaarliContainer;
use Shaarli\Front\Controller\OpenSearchController;
use Shaarli\Plugin\PluginManager;
use Shaarli\Render\PageBuilder;
use Shaarli\Security\LoginManager;
use Slim\Http\Request;
use Slim\Http\Response;

class OpenSearchControllerTest extends TestCase
{
    /** @var ShaarliContainer */
    protected $container;

    /** @var OpenSearchController */
    protected $controller;

    public function setUp(): void
    {
        $this->container = $this->createMock(ShaarliContainer::class);
        $this->controller = new OpenSearchController($this->container);
    }

    public function testOpenSearchController(): void
    {
        $this->createValidContainerMockSet();

        $request = $this->createMock(Request::class);
        $response = new Response();

        // Save RainTPL assigned variables
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $result = $this->controller->index($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertStringContainsString('application/xml', $result->getHeader('Content-Type')[0]);
        static::assertSame('opensearch', (string) $result->getBody());
        static::assertSame('http://shaarli', $assignedVariables['serverurl']);
    }

    protected function createValidContainerMockSet(): void
    {
        $loginManager = $this->createMock(LoginManager::class);
        $this->container->loginManager = $loginManager;

        // PageBuilder
        $pageBuilder = $this->createMock(PageBuilder::class);
        $pageBuilder
            ->method('render')
            ->willReturnCallback(function (string $template): string {
                return $template;
            })
        ;
        $this->container->pageBuilder = $pageBuilder;

        $bookmarkService = $this->createMock(BookmarkServiceInterface::class);
        $this->container->bookmarkService = $bookmarkService;

        // Plugin Manager
        $pluginManager = $this->createMock(PluginManager::class);
        $this->container->pluginManager = $pluginManager;

        // $_SERVER
        $this->container->environment = [
            'SERVER_NAME' => 'shaarli',
            'SERVER_PORT' => '80',
            'REQUEST_URI' => '/open-search',
        ];
    }

    protected function assignTemplateVars(array &$variables): void
    {
        $this->container->pageBuilder
            ->expects(static::atLeastOnce())
            ->method('assign')
            ->willReturnCallback(function ($key, $value) use (&$variables) {
                $variables[$key] = $value;

                return $this;
            })
        ;
    }
}
