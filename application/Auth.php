<?php
/**
 * Authentication process
 */

class Auth {

    /**
     * Checking session state (i.e. is the user still logged in)
     *
     * @param array $global shaarli configuration
     *
     * @return bool
     */
    public static function loginState($globals)
    {

        if ($globals['config']['OPEN_SHAARLI']) {
            return true;
        }

        // By default, we do not consider the user as logged in;
        $userIsLoggedIn = false;

        // If set to true, every attempt to authenticate the user
        // will fail. This indicates that an important condition isn't met.
        $loginFailure = false;

        if (!isset($globals['login'])) {
            // Shaarli is not configured yet.
            $userIsLoggedIn = false;
            $loginFailure = true;
        }

        if (isset($_COOKIE['shaarli_staySignedIn']) &&
            $_COOKIE['shaarli_staySignedIn'] === STAY_SIGNED_IN_TOKEN &&
            !$loginFailure) {
            fillSessionInfo();
            $userIsLoggedIn = true;
        }

        // If session does not exist on server side, or 
        // IP address has changed, or session has expired, logout.
        if (empty($_SESSION['uid']) || 
            ($globals['disablesessionprotection'] == false &&
                $_SESSION['ip'] != allIPs()) ||
            time() >= $_SESSION['expires_on']) {
            Auth::logout();
            $userIsLoggedIn = false;
            $loginFailure = true;
        }

        if (!empty($_SESSION['longlastingsession'])) {
            // In case of "Stay signed in" checked.
            $_SESSION['expires_on']=time()+$_SESSION['longlastingsession'];
        }
        else {
            // Standard session expiration date.
            $_SESSION['expires_on']=time()+INACTIVITY_TIMEOUT;
        }

        if (!$loginFailure) {
            $userIsLoggedIn = true;
        }

        return $userIsLoggedIn;
    }

    /**
     * Check that user/password is correct.
     *
     * @param string $login username from post array
     * @param string $password from post array
     * @param array $global shaarli configuration
     *
     * @return bool
     */
    public static function checkAuth($login, $password, $globals)
    {
        $hash = sha1($password . $login . $globals['salt']);

        if ($login == $globals['login'] && $hash == $globals['hash']) {
            fillSessionInfo();
            logm('Login successful');
            return true;
        }
        logm('Login failed for user '.$login);
        return false;
    }

    /**
     * Returns true if the user is logged in.
     *
     * @return bool
     */
    public static function isLoggedIn()
    {
        global $userIsLoggedIn;
        return $userIsLoggedIn;
    }

    /**
     * Destroy Shaarli session
     *
     */
    public static function logout()
    {
        if (isset($_SESSION)) {
            unset($_SESSION['uid']);
            unset($_SESSION['ip']);
            unset($_SESSION['username']);
            unset($_SESSION['privateonly']);
        }
        setcookie('shaarli_staySignedIn', FALSE, 0, WEB_PATH);
    }

    /**
     * Returns a token.
     *
     * @return string
     */
    public static function getToken()
    {
        // We generate a random string.
        $rnd = sha1(uniqid('',true) . '_' . mt_rand() . $GLOBALS['salt']);
        // Store it on the server side.
        $_SESSION['tokens'][$rnd] = 1;
        return $rnd;
    }

    /**
     * Tells if a token is OK. Using this function will destroy the token.
     * true=token is OK.
     *
     * @param string $token 
     *
     * @return bool
     */
    public static function tokenOk($token)
    {
        if (isset($_SESSION['tokens'][$token])) {
            // Token is used: destroy it.
            unset($_SESSION['tokens'][$token]);
            return true;
        }
        return false;
    }

}
