<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin;

use Shaarli\Front\Controller\Visitor\FrontControllerMockHelper;
use Shaarli\History;

/**
 * Trait FrontControllerMockHelper
 *
 * Helper trait used to initialize the Container and mock its services for admin controller tests.
 *
 * @property Container $container
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

        $this->container->set('history', $this->createMock(History::class));
        $this->container->get('loginManager')->method('isLoggedIn')->willReturn(true);
        $this->container->get('sessionManager')->method('checkToken')->willReturn(true);
    }


    /**
     * Pass a reference of an array which will be populated by `sessionManager->setSessionParameter`
     * calls during execution.
     *
     * @param mixed $variables Array reference to populate.
     */
    protected function assignSessionVars(array &$variables): void
    {
        $this->container->get('sessionManager')
            ->expects(static::atLeastOnce())
            ->method('setSessionParameter')
            ->willReturnCallback(function ($key, $value) use (&$variables) {
                $variables[$key] = $value;

                return $this->container->get('sessionManager');
            })
        ;
    }
}
