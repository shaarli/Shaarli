<?php
/**
 * Auth tests
 */

// required to access $_SESSION array
session_start();

require_once 'application/Authentication.php';

/**
 * Unitary tests for authentication process
 */
class AuthenticationTest extends PHPUnit_Framework_TestCase
{

    /**
     * Test testLoginState: 
     * Valid: 
     *
     * @return void
     */
    public function testLoginStateValid($globals)
    {
        //
    }

    /**
     * Test testLoginState: 
     * Invalid: 
     *
     * @return void
     */
    public function testLoginStateInvalid($globals)
    {
        //
    }

    /**
     * Test testCheckAuth: 
     * Valid: 
     *
     * @return void
     */
    public function testCheckAuthValid($login, $password, $globals)
    {
        //
    }

    /**
     * Test testCheckAuth: 
     * Invalid: 
     *
     * @return void
     */
    public function testCheckAuthInvalid($login, $password, $globals)
    {
        //
    }

    /**
     * Test testIsLoggedIn: 
     * Valid: 
     *
     * @return void
     */
    public function testIsLoggedInValid()
    {
        //
    }

    /**
     * Test testIsLoggedIn: 
     * Invalid: 
     *
     * @return void
     */
    public function testIsLoggedInInvalid()
    {
        //
    }

    /**
     * Test testLogout: 
     * Valid: 
     *
     * @return void
     */
    public function testLogoutValid()
    {
        //
    }

    /**
     * Test testLogout: 
     * Invalid: 
     *
     * @return void
     */
    public function testLogoutInvalid()
    {
        //
    }

}
