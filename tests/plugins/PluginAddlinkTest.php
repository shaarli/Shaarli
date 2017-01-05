<?php

/**
 * PluginPlayvideosTest.php
 */

require_once 'plugins/addlink_toolbar/addlink_toolbar.php';
require_once 'application/Router.php';

/**
 * Class PluginAddlinkTest
 * Unit test for the Addlink toolbar plugin
 */
class PluginAddlinkTest extends PHPUnit_Framework_TestCase
{
    /**
     * Reset plugin path.
     */
    public function setUp()
    {
        PluginManager::$PLUGINS_PATH = 'plugins';
    }

    /**
     * Test render_header hook while logged in.
     */
    public function testAddlinkHeaderLoggedIn()
    {
        $str = 'stuff';
        $data = array($str => $str);
        $data['_PAGE_'] = Router::$PAGE_LINKLIST;
        $data['_LOGGEDIN_'] = true;

        $data = hook_addlink_toolbar_render_header($data);
        $this->assertEquals($str, $data[$str]);
        $this->assertEquals(1, count($data['fields_toolbar']));

        $data = array($str => $str);
        $data['_PAGE_'] = $str;
        $data['_LOGGEDIN_'] = true;
        $data = hook_addlink_toolbar_render_header($data);
        $this->assertEquals($str, $data[$str]);
        $this->assertArrayNotHasKey('fields_toolbar', $data);
    }

    /**
     * Test render_header hook while logged out.
     */
    public function testAddlinkHeaderLoggedOut()
    {
        $str = 'stuff';
        $data = array($str => $str);
        $data['_PAGE_'] = Router::$PAGE_LINKLIST;
        $data['_LOGGEDIN_'] = false;

        $data = hook_addlink_toolbar_render_header($data);
        $this->assertEquals($str, $data[$str]);
        $this->assertArrayNotHasKey('fields_toolbar', $data);
    }

    /**
     * Test render_includes hook while logged in.
     */
    public function testAddlinkIncludesLoggedIn()
    {
        $str = 'stuff';
        $data = array($str => $str);
        $data['_PAGE_'] = Router::$PAGE_LINKLIST;
        $data['_LOGGEDIN_'] = true;

        $data = hook_addlink_toolbar_render_includes($data);
        $this->assertEquals($str, $data[$str]);
        $this->assertEquals(1, count($data['css_files']));

        $str = 'stuff';
        $data = array($str => $str);
        $data['_PAGE_'] = $str;
        $data['_LOGGEDIN_'] = true;

        $data = hook_addlink_toolbar_render_includes($data);
        $this->assertEquals($str, $data[$str]);
        $this->assertArrayNotHasKey('css_files', $data);
    }

    /**
     * Test render_includes hook.
     * Should not affect css files while logged out.
     */
    public function testAddlinkIncludesLoggedOut()
    {
        $str = 'stuff';
        $data = array($str => $str);
        $data['_PAGE_'] = Router::$PAGE_LINKLIST;
        $data['_LOGGEDIN_'] = false;

        $data = hook_addlink_toolbar_render_includes($data);
        $this->assertEquals($str, $data[$str]);
        $this->assertArrayNotHasKey('css_files', $data);
    }
}
