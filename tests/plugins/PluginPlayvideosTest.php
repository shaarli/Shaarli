<?php

/**
 * PluginPlayvideosTest.php
 */

require_once 'plugins/playvideos/playvideos.php';
require_once 'application/Router.php';

/**
 * Class PluginPlayvideosTest
 * Unit test for the PlayVideos plugin
 */
class PluginPlayvideosTest extends PHPUnit_Framework_TestCase
{
    /**
     * Reset plugin path
     */
    public function setUp()
    {
        PluginManager::$PLUGINS_PATH = 'plugins';
    }

    /**
     * Test render_linklist hook.
     */
    public function testPlayvideosHeader()
    {
        $str = 'stuff';
        $data = array($str => $str);
        $data['_PAGE_'] = Router::$PAGE_LINKLIST;

        $data = hook_playvideos_render_header($data);
        $this->assertEquals($str, $data[$str]);
        $this->assertEquals(1, count($data['buttons_toolbar']));

        $data = array($str => $str);
        $data['_PAGE_'] = $str;
        $this->assertEquals($str, $data[$str]);
        $this->assertArrayNotHasKey('buttons_toolbar', $data);
    }

    /**
     * Test render_footer hook.
     */
    public function testPlayvideosFooter()
    {
        $str = 'stuff';
        $data = array($str => $str);
        $data['_PAGE_'] = Router::$PAGE_LINKLIST;

        $data = hook_playvideos_render_footer($data);
        $this->assertEquals($str, $data[$str]);
        $this->assertEquals(2, count($data['js_files']));

        $data = array($str => $str);
        $data['_PAGE_'] = $str;
        $this->assertEquals($str, $data[$str]);
        $this->assertArrayNotHasKey('js_files', $data);
    }
}
