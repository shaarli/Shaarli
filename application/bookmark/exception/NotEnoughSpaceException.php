<?php

namespace Shaarli\Bookmark\Exception;

class NotEnoughSpaceException extends \Exception
{
    /**
     * NotEnoughSpaceException constructor.
     */
    public function __construct()
    {
        $this->message = 'Not enough available disk space to save the datastore.';
    }
}
