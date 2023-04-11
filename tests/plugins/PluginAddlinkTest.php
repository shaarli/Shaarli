<?php

namespace Shaarli\Plugin\Addlink;

use Shaarli\Plugin\PluginManager;
use Shaarli\Render\TemplatePage;

require_once __DIR__ . '/../../plugins/addlink_toolbar/addlink_toolbar.php';

/**
 * Unit test for the Addlink toolbar plugin
 */
class PluginAddlinkTest extends \Shaarli\TestCase
{
    /**
     * Reset plugin path.
     */
    protected function setUp(): void
    {
        PluginManager::$PLUGINS_PATH = 'plugins';
    }

    /**
     * Test render_header hook while logged in.
     */
    public function testAddlinkHeaderLoggedIn()
    {
        $str = 'stuff';
        $data = [$str => $str];
        $data['_PAGE_'] = TemplatePage::LINKLIST;
        $data['_LOGGEDIN_'] = true;
        $data['_BASE_PATH_'] = '/subfolder';

        $data = hook_addlink_toolbar_render_header($data);
        $this->assertEquals($str, $data[$str]);
        $this->assertEquals(1, count($data['fields_toolbar']));

        $data = [$str => $str];
        $data['_PAGE_'] = $str;
        $data['_LOGGEDIN_'] = true;
        $data['_BASE_PATH_'] = '/subfolder';

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
        $data = [$str => $str];
        $data['_PAGE_'] = TemplatePage::LINKLIST;
        $data['_LOGGEDIN_'] = false;
        $data['_BASE_PATH_'] = '/subfolder';

        $data = hook_addlink_toolbar_render_header($data);
        $this->assertEquals($str, $data[$str]);
        $this->assertArrayNotHasKey('fields_toolbar', $data);
    }
}
