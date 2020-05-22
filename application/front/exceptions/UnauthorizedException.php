<?php

declare(strict_types=1);

namespace Shaarli\Front\Exception;

/**
 * Class UnauthorizedException
 *
 * Exception raised if the user tries to access a ShaarliAdminController while logged out.
 */
class UnauthorizedException extends \Exception
{

}
