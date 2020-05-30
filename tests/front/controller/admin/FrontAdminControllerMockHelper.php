<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin;

use Shaarli\Container\ShaarliTestContainer;
use Shaarli\Front\Controller\Visitor\FrontControllerMockHelper;
use Shaarli\History;

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

        $this->container->history = $this->createMock(History::class);

        $this->container->loginManager->method('isLoggedIn')->willReturn(true);
        $this->container->sessionManager->method('checkToken')->willReturn(true);
    }


    /**
     * Pass a reference of an array which will be populated by `sessionManager->setSessionParameter`
     * calls during execution.
     *
     * @param mixed $variables Array reference to populate.
     */
    protected function assignSessionVars(array &$variables): void
    {
        $this->container->sessionManager
            ->expects(static::atLeastOnce())
            ->method('setSessionParameter')
            ->willReturnCallback(function ($key, $value) use (&$variables) {
                $variables[$key] = $value;

                return $this->container->sessionManager;
            })
        ;
    }
}
