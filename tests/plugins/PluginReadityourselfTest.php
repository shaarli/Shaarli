<?php

/**
 * PluginReadityourselfTest.php.php
 */

require_once 'plugins/readityourself/readityourself.php';

/**
 * Class PluginWallabagTest
 * Unit test for the Wallabag plugin
 */
class PluginReadityourselfTest extends PHPUnit_Framework_TestCase
{
    /**
     * Reset plugin path
     */
    public function setUp()
    {
        PluginManager::$PLUGINS_PATH = 'plugins';
    }

    /**
     * Test Readityourself init without errors.
     */
    public function testReadityourselfInitNoError()
    {
        $conf = new ConfigManager('');
        $conf->set('plugins.READITYOUSELF_URL', 'value');
        $errors = readityourself_init($conf);
        $this->assertEmpty($errors);
    }

    /**
     * Test Readityourself init with errors.
     */
    public function testReadityourselfInitError()
    {
        $conf = new ConfigManager('');
        $errors = readityourself_init($conf);
        $this->assertNotEmpty($errors);
    }

    /**
     * Test render_linklist hook.
     */
    public function testReadityourselfLinklist()
    {
        $conf = new ConfigManager('');
        $conf->set('plugins.READITYOUSELF_URL', 'value');
        $str = 'http://randomstr.com/test';
        $data = array(
            'title' => $str,
            'links' => array(
                array(
                    'url' => $str,
                )
            )
        );

        $data = hook_readityourself_render_linklist($data, $conf);
        $link = $data['links'][0];
        // data shouldn't be altered
        $this->assertEquals($str, $data['title']);
        $this->assertEquals($str, $link['url']);

        // plugin data
        $this->assertEquals(1, count($link['link_plugin']));
        $this->assertNotFalse(strpos($link['link_plugin'][0], $str));
    }

    /**
     * Test without config: nothing should happened.
     */
    public function testReadityourselfLinklistWithoutConfig()
    {
        $conf = new ConfigManager('');
        $conf->set('plugins.READITYOUSELF_URL', null);
        $str = 'http://randomstr.com/test';
        $data = array(
            'title' => $str,
            'links' => array(
                array(
                    'url' => $str,
                )
            )
        );

        $data = hook_readityourself_render_linklist($data, $conf);
        $link = $data['links'][0];
        // data shouldn't be altered
        $this->assertEquals($str, $data['title']);
        $this->assertEquals($str, $link['url']);

        // plugin data
        $this->assertArrayNotHasKey('link_plugin', $link);
    }
}
