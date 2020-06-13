<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin\ManageShaareControllerTest;

use PHPUnit\Framework\TestCase;
use Shaarli\Front\Controller\Admin\FrontAdminControllerMockHelper;
use Shaarli\Front\Controller\Admin\ManageShaareController;
use Shaarli\Http\HttpAccess;
use Slim\Http\Request;
use Slim\Http\Response;

class AddShaareTest extends TestCase
{
    use FrontAdminControllerMockHelper;

    /** @var ManageShaareController */
    protected $controller;

    public function setUp(): void
    {
        $this->createContainer();

        $this->container->httpAccess = $this->createMock(HttpAccess::class);
        $this->controller = new ManageShaareController($this->container);
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
}
