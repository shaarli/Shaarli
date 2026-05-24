<?php

namespace Shaarli\Security;

use Shaarli\Config\ConfigManager;

/**
 * Manages the server-side session
 */
class SessionManager
{
    public const KEY_LINKS_PER_PAGE = 'LINKS_PER_PAGE';
    public const KEY_VISIBILITY = 'visibility';
    public const KEY_UNTAGGED_ONLY = 'untaggedonly';

    public const KEY_SUCCESS_MESSAGES = 'successes';
    public const KEY_WARNING_MESSAGES = 'warnings';
    public const KEY_ERROR_MESSAGES = 'errors';

    /** @var int Session expiration timeout, in seconds */
    public static $SHORT_TIMEOUT = 3600;    // 1 hour

    /** @var int Session expiration timeout, in seconds */
    public static $LONG_TIMEOUT = 31536000; // 1 year

    /** @var array Local reference to the global $_SESSION array */
    protected $session = [];

    /** @var ConfigManager Configuration Manager instance **/
    protected $conf = null;

    /** @var bool Whether the user should stay signed in (LONG_TIMEOUT) */
    protected $staySignedIn = false;

    /** @var string */
    protected $savePath;

    /**
     * Constructor
     *
     * @param array         $session  The $_SESSION array (reference)
     * @param ConfigManager $conf     ConfigManager instance
     * @param string        $savePath Session save path returned by builtin function session_save_path()
     */
    public function __construct(&$session, $conf, string $savePath)
    {
        $this->session = &$session;
        $this->conf = $conf;
        $this->savePath = $savePath;
    }

    /**
     * Initialize XSRF token and links per page session variables.
     */
    public function initialize(): void
    {
        if (!isset($this->session['tokens'])) {
            $this->session['tokens'] = [];
        }

        if (!isset($this->session['LINKS_PER_PAGE'])) {
            $this->session['LINKS_PER_PAGE'] = $this->conf->get('general.links_per_page', 20);
        }

        if (!empty($this->session['expires_on']) && $this->session['expires_on'] >= (time() + self::$SHORT_TIMEOUT)) {
            // We deduce from the session-stored 'expires_on' value if we are currently in a staySignedIn state:
            $this->staySignedIn = true;
        }
    }

    /**
     * Define whether the user should stay signed in across browser sessions
     *
     * @param bool $staySignedIn Keep the user signed in
     */
    public function setStaySignedIn($staySignedIn)
    {
        $this->staySignedIn = $staySignedIn;
    }

    /**
     * Generates a session token
     *
     * @return string token
     */
    public function generateToken()
    {
        $token = sha1(uniqid('', true) . '_' . mt_rand() . $this->conf->get('credentials.salt'));
        $this->session['tokens'][$token] = 1;
        return $token;
    }

    /**
     * Checks the validity of a session token, and destroys it afterwards
     *
     * @param string $token The token to check
     *
     * @return bool true if the token is valid, else false
     */
    public function checkToken($token)
    {
        if (! isset($this->session['tokens'][$token])) {
            // the token is wrong, or has already been used
            return false;
        }

        // destroy the token to prevent future use
        unset($this->session['tokens'][$token]);
        return true;
    }

    /**
     * Validate session ID to prevent Full Path Disclosure.
     *
     * See #298.
     * The session ID's format depends on the hash algorithm set in PHP settings
     *
     * @param string $sessionId Session ID
     *
     * @return true if valid, false otherwise.
     *
     * @see http://php.net/manual/en/function.hash-algos.php
     * @see http://php.net/manual/en/session.configuration.php
     */
    public static function checkId($sessionId)
    {
        if (empty($sessionId)) {
            return false;
        }

        if (!$sessionId) {
            return false;
        }

        if (!preg_match('/^[a-zA-Z0-9,-]{2,128}$/', $sessionId)) {
            return false;
        }

        return true;
    }

    /**
     * Store user login information after a successful login
     *
     * @param string $clientIpId Client IP address identifier
     */
    public function storeLoginInfo($clientIpId)
    {
        $this->session['ip'] = $clientIpId;
        $this->session['username'] = $this->conf->get('credentials.login');
        $this->extendTimeValidityBy(self::$SHORT_TIMEOUT);
    }

    /**
     * Extend session validity
     */
    public function extendSession()
    {
        if ($this->staySignedIn) {
            return $this->extendTimeValidityBy(self::$LONG_TIMEOUT);
        }
        return $this->extendTimeValidityBy(self::$SHORT_TIMEOUT);
    }

    /**
     * Extend expiration time
     *
     * @param int $duration Expiration time extension (seconds)
     *
     * @return int New session expiration time
     */
    protected function extendTimeValidityBy($duration)
    {
        $expirationTime = time() + $duration;
        $this->session['expires_on'] = $expirationTime;
        return $expirationTime;
    }

    /**
     * Logout a user by unsetting all login information
     *
     * See:
     * - https://secure.php.net/manual/en/function.setcookie.php
     */
    public function logout()
    {
        if (isset($this->session)) {
            unset($this->session['ip']);
            unset($this->session['expires_on']);
            unset($this->session['username']);
            unset($this->session['visibility']);
        }
    }

    /**
     * Check whether the session has expired
     *
     * @param string $clientIpId Client IP address identifier
     *
     * @return bool true if the session has expired, false otherwise
     */
    public function hasSessionExpired()
    {
        if (empty($this->session['expires_on'])) {
            return true;
        }
        if (time() >= $this->session['expires_on']) {
            return true;
        }
        return false;
    }

    /**
     * Check whether the client IP address has changed
     *
     * @param string $clientIpId Client IP address identifier
     *
     * @return bool true if the IP has changed, false if it has not, or
     *              if session protection has been disabled
     */
    public function hasClientIpChanged($clientIpId)
    {
        if ($this->conf->get('security.session_protection_disabled') === true) {
            return false;
        }
        if (isset($this->session['ip']) && $this->session['ip'] === $clientIpId) {
            return false;
        }
        return true;
    }

    /** @return array Local reference to the global $_SESSION array */
    public function getSession(): array
    {
        return $this->session;
    }

    /**
     * @param mixed $default value which will be returned if the $key is undefined
     *
     * @return mixed Content stored in session
     */
    public function getSessionParameter(string $key, $default = null)
    {
        return $this->session[$key] ?? $default;
    }

    /**
     * Store a variable in user session.
     *
     * @param string $key   Session key
     * @param mixed  $value Session value to store
     *
     * @return $this
     */
    public function setSessionParameter(string $key, $value): self
    {
        $this->session[$key] = $value;

        return $this;
    }

    /**
     * Delete a variable in user session.
     *
     * @param string $key   Session key
     *
     * @return $this
     */
    public function deleteSessionParameter(string $key): self
    {
        unset($this->session[$key]);

        return $this;
    }

    public function getSavePath(): string
    {
        return $this->savePath;
    }

    /*
     * Next public functions wrapping native PHP session API.
     */

    public function destroy(): bool
    {
        $this->session = [];

        return session_destroy();
    }

    public function start(): bool
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->destroy();
        }

        return session_start();
    }

    /**
     * Be careful, return type of session_set_cookie_params() changed between PHP 7.1 and 7.2.
     */
    public function cookieParameters(int $lifeTime, string $path, string $domain): void
    {
        session_set_cookie_params($lifeTime, $path, $domain);
    }

    public function regenerateId(bool $deleteOldSession = false): bool
    {
        return session_regenerate_id($deleteOldSession);
    }

    /*
     * Useful for debugging, to get the current state
     */
    public function getStaySignedIn(): bool
    {
        return $this->staySignedIn;
    }
}
