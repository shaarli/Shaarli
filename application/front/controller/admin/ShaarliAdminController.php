<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin;

use Shaarli\Container\ShaarliContainer;
use Shaarli\Front\Controller\Visitor\ShaarliVisitorController;
use Shaarli\Front\Exception\UnauthorizedException;

abstract class ShaarliAdminController extends ShaarliVisitorController
{
    public function __construct(ShaarliContainer $container)
    {
        parent::__construct($container);

        if (true !== $this->container->loginManager->isLoggedIn()) {
            throw new UnauthorizedException();
        }
    }
}
