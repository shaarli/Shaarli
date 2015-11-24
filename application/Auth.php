<?php
/**
 * Authentication process
 */

class Auth {

    /**
     * Check that user/password is correct.
     *
     * @param string $login username from post array
     * @param string $password from post array
     *
     * @return bool
     */
    public static function checkAuth($login, $password)
    {
        $hash = sha1($password . $login . $GLOBALS['salt']);

        if ($login == $GLOBALS['login'] && $hash == $GLOBALS['hash']) {
            fillSessionInfo();
            logm('Login successful');
            return true;
        }
        logm('Login failed for user '.$login);
        return false;
    }

    /**
     * Purges all cached pages
     *
     * @return true if the user is logged in.
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
