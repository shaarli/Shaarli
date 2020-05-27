<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin;

use Shaarli\Container\ShaarliTestContainer;
use Shaarli\Front\Controller\Visitor\FrontControllerMockHelper;

/**
 * Trait FrontControllerMockHelper
 *
 * Helper trait used to initialize the ShaarliContainer and mock its services for admin controller tests.
 *
 * @property ShaarliTestContainer $container
 */
trait FrontAdminControllerMockHelper
{
    use FrontControllerMockHelper {
        FrontControllerMockHelper::createContainer as parentCreateContainer;
    }

    /**
     * Mock the container instance
     */
    protected function createContainer(): void
    {
        $this->parentCreateContainer();

        $this->container->loginManager->method('isLoggedIn')->willReturn(true);
        $this->container->sessionManager->method('checkToken')->willReturn(true);
    }
}
