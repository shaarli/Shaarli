<?php

declare(strict_types=1);

namespace Shaarli\Front\Exception;

class LoginBannedException extends ShaarliFrontException
{
    public function __construct()
    {
        $message = t('You have been banned after too many failed login attempts. Try again later.');

        parent::__construct($message, 401);
    }
}
