<?php

declare(strict_types=1);

namespace Shaarli\Security;

class CookieManager
{
    /** @var string Name of the cookie set after logging in **/
    public const STAY_SIGNED_IN = 'shaarli_staySignedIn';

    /** @var mixed $_COOKIE set by reference */
    protected $cookies;

    public function __construct(array &$cookies)
    {
        $this->cookies = $cookies;
    }

    public function setCookieParameter(string $key, string $value, int $expires, string $path): self
    {
        $this->cookies[$key] = $value;

        setcookie($key, $value, $expires, $path);

        return $this;
    }

    public function getCookieParameter(string $key, ?string $default = null): ?string
    {
        return $this->cookies[$key] ?? $default;
    }
}
