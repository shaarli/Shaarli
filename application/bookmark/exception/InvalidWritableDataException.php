<?php

namespace Shaarli\Bookmark\Exception;

class InvalidWritableDataException extends \Exception
{
    /**
     * InvalidWritableDataException constructor.
     */
    public function __construct()
    {
        $this->message = 'Couldn\'t generate bookmark data to store in the datastore. Skipping file writing.';
    }
}
