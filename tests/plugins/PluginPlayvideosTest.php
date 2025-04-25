<?php

namespace Shaarli\Plugin\Playvideos;

use Shaarli\Plugin\PluginManager;
use Shaarli\Render\TemplatePage;

require_once 'plugins/playvideos/playvideos.php';

/**
 * Class PluginPlayvideosTest
 * Unit test for the PlayVideos plugin
 */
class PluginPlayvideosTest extends \Shaarli\TestCase
{
    /**
     * Reset plugin path
     */
    protected function setUp(): void
    {
        PluginManager::$PLUGINS_PATH = 'plugins';
    }

    /**
     * Test render_linklist hook.
     */
    public function testPlayvideosHeader()
    {
        $str = 'stuff';
        $data = [$str => $str];
        $data['_PAGE_'] = TemplatePage::LINKLIST;

        $data = hook_playvideos_render_header($data);
        $this->assertEquals($str, $data[$str]);
        $this->assertEquals(1, count($data['buttons_toolbar']));

        $data = [$str => $str];
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
        $data = [$str => $str];
        $data['_PAGE_'] = TemplatePage::LINKLIST;

        $data = hook_playvideos_render_footer($data);
        $this->assertEquals($str, $data[$str]);
        $this->assertEquals(1, count($data['js_files']));

        $data = [$str => $str];
        $data['_PAGE_'] = $str;
        $this->assertEquals($str, $data[$str]);
        $this->assertArrayNotHasKey('js_files', $data);
    }
}
