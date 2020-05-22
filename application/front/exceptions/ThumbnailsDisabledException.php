<?php

declare(strict_types=1);

namespace Shaarli\Front\Exception;

class ThumbnailsDisabledException extends ShaarliFrontException
{
    public function __construct()
    {
        $message = t('Picture wall unavailable (thumbnails are disabled).');

        parent::__construct($message, 400);
    }
}
