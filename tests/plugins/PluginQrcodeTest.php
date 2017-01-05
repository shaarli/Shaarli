<?php

/**
 * PluginQrcodeTest.php
 */

require_once 'plugins/qrcode/qrcode.php';
require_once 'application/Router.php';

/**
 * Class PluginQrcodeTest
 * Unit test for the QR-Code plugin
 */
class PluginQrcodeTest extends PHPUnit_Framework_TestCase
{
    /**
     * Reset plugin path
     */
    public function setUp() {
        PluginManager::$PLUGINS_PATH = 'plugins';
    }

    /**
     * Test render_linklist hook.
     */
    public function testQrcodeLinklist()
    {
        $str = 'http://randomstr.com/test';
        $data = array(
            'title' => $str,
            'links' => array(
                array(
                    'url' => $str,
                )
            )
        );

        $data = hook_qrcode_render_linklist($data);
        $link = $data['links'][0];
        // data shouldn't be altered
        $this->assertEquals($str, $data['title']);
        $this->assertEquals($str, $link['url']);

        // plugin data
        $this->assertEquals(1, count($link['link_plugin']));
        $this->assertNotFalse(strpos($link['link_plugin'][0], $str));
    }

    /**
     * Test render_footer hook.
     */
    public function testQrcodeFooter()
    {
        $str = 'stuff';
        $data = array($str => $str);
        $data['_PAGE_'] = Router::$PAGE_LINKLIST;

        $data = hook_qrcode_render_footer($data);
        $this->assertEquals($str, $data[$str]);
        $this->assertEquals(1, count($data['js_files']));

        $data = array($str => $str);
        $data['_PAGE_'] = $str;
        $this->assertEquals($str, $data[$str]);
        $this->assertArrayNotHasKey('js_files', $data);
    }
}
