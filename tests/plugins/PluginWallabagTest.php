<?php

/**
 * PluginWallabagTest.php.php
 */

require_once 'plugins/wallabag/wallabag.php';

/**
 * Class PluginWallabagTest
 * Unit test for the Wallabag plugin
 */
class PluginWallabagTest extends PHPUnit_Framework_TestCase
{
    /**
     * Reset plugin path
     */
    public function setUp()
    {
        PluginManager::$PLUGINS_PATH = 'plugins';
    }

    /**
     * Test wallabag init without errors.
     */
    public function testWallabagInitNoError()
    {
        $conf = new ConfigManager('');
        $conf->set('plugins.WALLABAG_URL', 'value');
        $errors = wallabag_init($conf);
        $this->assertEmpty($errors);
    }

    /**
     * Test wallabag init with errors.
     */
    public function testWallabagInitError()
    {
        $conf = new ConfigManager('');
        $errors = wallabag_init($conf);
        $this->assertNotEmpty($errors);
    }

    /**
     * Test render_linklist hook.
     */
    public function testWallabagLinklist()
    {
        $conf = new ConfigManager('');
        $conf->set('plugins.WALLABAG_URL', 'value');
        $str = 'http://randomstr.com/test';
        $data = array(
            'title' => $str,
            'links' => array(
                array(
                    'url' => $str,
                )
            )
        );

        $data = hook_wallabag_render_linklist($data, $conf);
        $link = $data['links'][0];
        // data shouldn't be altered
        $this->assertEquals($str, $data['title']);
        $this->assertEquals($str, $link['url']);

        // plugin data
        $this->assertEquals(1, count($link['link_plugin']));
        $this->assertNotFalse(strpos($link['link_plugin'][0], urlencode($str)));
        $this->assertNotFalse(strpos($link['link_plugin'][0], $conf->get('plugins.WALLABAG_URL')));
    }
}
