<?php

require_once 'controllers/LoginController.php';

/**
 * Class LoginControllerTest
 *
 * Test the login view page
 */
class LoginControllerTest extends ControllerTest
{
    public function setUp()
    {
        parent::setUp();
        $this->controller = new LoginController(
            $this->pageBuilder,
            $this->conf,
            $this->pluginManager,
            $this->linkDB,
            array(),
            array(),
            array()
        );
    }

    /**
     * Test login redirection: should only redirect if open shaarli is enabled.
     *
     * @runInSeparateProcess
     */
    public function testLoginRedirect() {
        $this->assertFalse($this->controller->redirect());
        $headers = xdebug_get_headers();
        $this->assertFalse($this->isHeaderSetRegex('Location:.*', $headers));

        $this->conf->set('security.open_shaarli', true);
        $this->assertTrue($this->controller->redirect());
        $headers = xdebug_get_headers();
        $this->assertTrue($this->isHeaderSetRegex('Location: \?', $headers));
    }

    /**
     * Test login rendering without any data set.
     */
    public function testLoginRenderWithoutLogin() {
        $this->markTestIncomplete('This test is not doable now due to ban function relying on $_SERVER.');
        $this->expectOutputRegex('<input type="text" name="login"');
        $this->expectOutputRegex('<input type="password" name="password"');
        $this->expectOutputRegex('<input type="checkbox" name="longlastingsession"');
        $this->controller->render();
    }

    /**
     * Test login rendering with a username set in $_GET.
     */
    public function testLoginRenderWithLogin() {
        $this->markTestIncomplete('This test is not doable now due to ban function relying on $_SERVER.');
        $username = 'John Doe';
        $this->controller->setGet(array('username' => $username));
        $this->expectOutputRegex('<input type="text" name="login" value="'. $username .'"');
        $this->controller->render();
    }

    /**
     * Test login rendering with a username set in $_GET.
     */
    public function testLoginRenderBanned() {
        $this->markTestIncomplete('This test is not doable now due to ban function relying on $_SERVER.');
    }
}