<?php

declare(strict_types=1);

namespace Shaarli\Front\Exception;

/**
 * Class OpenShaarliPasswordException
 *
 * Raised if the user tries to change the admin password on an open shaarli instance.
 */
class OpenShaarliPasswordException extends ShaarliFrontException
{
    public function __construct()
    {
        parent::__construct(t('You are not supposed to change a password on an Open Shaarli.'), 403);
    }
}
