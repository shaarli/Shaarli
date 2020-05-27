<?php

declare(strict_types=1);

namespace Shaarli\Front\Exception;

use Throwable;

/**
 * Class ShaarliException
 *
 * Exception class used to defined any custom exception thrown during front rendering.
 *
 * @package Front\Exception
 */
class ShaarliFrontException extends \Exception
{
    /** Override parent constructor to force $message and $httpCode parameters to be set. */
    public function __construct(string $message, int $httpCode, Throwable $previous = null)
    {
        parent::__construct($message, $httpCode, $previous);
    }
}
