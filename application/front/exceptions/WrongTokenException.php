<?php

declare(strict_types=1);

namespace Shaarli\Front\Exception;

/**
 * Class OpenShaarliPasswordException
 *
 * Raised if the user tries to perform an action with an invalid XSRF token.
 */
class WrongTokenException extends ShaarliFrontException
{
    public function __construct()
    {
        parent::__construct(t('Wrong token.'), 403);
    }
}
