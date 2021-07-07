<?php

namespace Shaarli\Plugin\ReadItLater;

use Shaarli\Bookmark\Bookmark;
use Shaarli\Config\ConfigManager;
use Shaarli\Plugin\PluginManager;
use Shaarli\TestCase;

require_once 'plugins/readitlater/readitlater.php';

/**
 * Class PluginQrcodeTest
 * Unit test for the ReadItLater plugin
 */
class PluginQrcodeTest extends TestCase
{
    /** @var ConfigManager */
    protected $confDefaultTheme;

    /** @var ConfigManager */
    protected $confOtherTheme;

    /**
     * Reset plugin path
     */
    protected function setUp(): void
    {
        PluginManager::$PLUGINS_PATH = 'plugins';
        $this->confDefaultTheme = $this->createMock(ConfigManager::class);
        $this->confDefaultTheme->method('get')->willReturnCallback(function (string $parameter, $default) {
            if ($parameter === 'resource.theme') {
                return 'default';
            }

            return $default;
        });

        $this->confOtherTheme = $this->createMock(ConfigManager::class);
        $this->confDefaultTheme->method('get')->willReturnCallback(function (string $parameter, $default) {
            if ($parameter === 'resource.theme') {
                return 'other';
            }

            return $default;
        });
    }

    /**
     * Test hook_readitlater_render_linklist while logged in.
     */
    public function testReadItLaterLinklistLoggedInDefaultTheme(): void
    {
        $url = 'http://randomstr.com/test';
        $data = [
            '_LOGGEDIN_' => true,
            'links' => [
                [
                    'id' => 1,
                    'url' => $url . '1',
                ],
                [
                    'id' => 2,
                    'url' => $url . '2',
                    'additional_content' => [
                        'readitlater' => false,
                    ],
                ],
                [
                    'id' => 3,
                    'url' => $url . '3',
                    'additional_content' => [
                        'readitlater' => true,
                    ],
                ],
            ],
        ];

        $data = hook_readitlater_render_linklist($data, $this->confDefaultTheme);

        $link = $data['links'][0];
        static::assertEquals($url . '1', $link['url']);
        static::assertNotEmpty($link['link_plugin']);
        static::assertContainsPolyfill('Read it later', $link['link_plugin'][0]);

        $link = $data['links'][1];
        static::assertEquals($url . '2', $link['url']);
        static::assertNotEmpty($link['link_plugin']);
        static::assertContainsPolyfill('Read it later', $link['link_plugin'][0]);

        $link = $data['links'][2];
        static::assertEquals($url . '3', $link['url']);
        static::assertNotEmpty($link['link_plugin']);
        static::assertContainsPolyfill('Mark as Read', $link['link_plugin'][0]);

        static::assertNotEmpty($data['action_plugin']);
        static::assertContainsPolyfill('readitlater/toggle-filter', $data['action_plugin'][0]['attr']['href']);
    }

    /**
     * Test hook_readitlater_render_linklist while logged in.
     */
    public function testReadItLaterLinklistLoggedInOtherTheme(): void
    {
        $url = 'http://randomstr.com/test';
        $data = [
            '_LOGGEDIN_' => true,
            'links' => [
                [
                    'id' => 1,
                    'url' => $url . '1',
                ],
                [
                    'id' => 2,
                    'url' => $url . '2',
                    'additional_content' => [
                        'readitlater' => false,
                    ],
                ],
                [
                    'id' => 3,
                    'url' => $url . '3',
                    'additional_content' => [
                        'readitlater' => true,
                    ],
                ],
            ],
        ];

        $data = hook_readitlater_render_linklist($data, $this->confOtherTheme);

        $link = $data['links'][0];
        static::assertEquals($url . '1', $link['url']);
        static::assertNotEmpty($link['link_plugin']);
        static::assertContainsPolyfill('Read it later', $link['link_plugin'][0]);

        $link = $data['links'][1];
        static::assertEquals($url . '2', $link['url']);
        static::assertNotEmpty($link['link_plugin']);
        static::assertContainsPolyfill('Read it later', $link['link_plugin'][0]);

        $link = $data['links'][2];
        static::assertEquals($url . '3', $link['url']);
        static::assertNotEmpty($link['link_plugin']);
        static::assertContainsPolyfill('Mark as Read', $link['link_plugin'][0]);

        static::assertNotEmpty($data['action_plugin']);
        static::assertContainsPolyfill('readitlater/toggle-filter', $data['action_plugin'][0]['attr']['href']);
    }

    /**
     * Test hook_readitlater_render_linklist while logged out: nothing should happen.
     */
    public function testReadItLaterLinklistLoggedOut(): void
    {
        $url = 'http://randomstr.com/test';
        $originalData = [
            '_LOGGEDIN_' => false,
            'links' => [
                [
                    'id' => 1,
                    'url' => $url . '1',
                ],
                [
                    'id' => 2,
                    'url' => $url . '2',
                    'additional_content' => [
                        'readitlater' => false,
                    ],
                ],
                [
                    'id' => 3,
                    'url' => $url . '3',
                    'additional_content' => [
                        'readitlater' => true,
                    ],
                ],
            ],
        ];

        $data = hook_readitlater_render_linklist($originalData, $this->confDefaultTheme);

        static::assertSame($originalData, $data);

        unset($originalData['_LOGGEDIN_']);

        $data = hook_readitlater_render_linklist($originalData, $this->confDefaultTheme);

        static::assertSame($originalData, $data);
    }

    /**
     * Test readitlater_register_routes
     */
    public function testReadItLaterRoutesRegister(): void
    {
        $routes = readitlater_register_routes();

        static::assertCount(2, $routes);
        foreach ($routes as $route) {
            static::assertSame('GET', $route['method']);
            static::assertContainsPolyfill('ReadItLaterController', $route['callable']);
        }
    }

    /**
     * Test hook_readitlater_render_includes while logged in
     */
    public function testReadItLaterRenderIncludesLoggedInDefaultTheme(): void
    {
        $data = hook_readitlater_render_includes(['_LOGGEDIN_' => true], $this->confDefaultTheme);

        static::assertSame('plugins/readitlater/readitlater.default.css', $data['css_files'][0]);
    }

    /**
     * Test hook_readitlater_render_includes while logged in
     */
    public function testReadItLaterRenderIncludesLoggedInOtherTheme(): void
    {
        $data = hook_readitlater_render_includes($originalData = ['_LOGGEDIN_' => true], $this->confOtherTheme);

        static::assertSame($originalData, $data);
    }

    /**
     * Test hook_readitlater_render_includes while logged out
     */
    public function testReadItLaterRenderIncludesLoggedOut(): void
    {
        $data = hook_readitlater_render_includes([], $this->confDefaultTheme);

        static::assertSame([], $data);

        $data = hook_readitlater_render_includes($originalData = ['_LOGGEDIN_' => false], $this->confDefaultTheme);

        static::assertSame($originalData, $data);
    }

    /**
     * Test hook_readitlater_render_footer while logged in
     */
    public function testReadItLaterRenderFooterLoggedInDefaultTheme(): void
    {
        $data = hook_readitlater_render_footer(['_LOGGEDIN_' => true], $this->confDefaultTheme);

        static::assertSame('plugins/readitlater/readitlater.default.js', $data['js_files'][0]);
    }

    /**
     * Test hook_readitlater_render_footer while logged in
     */
    public function testReadItLaterRenderFooterLoggedInOtherTheme(): void
    {
        $data = hook_readitlater_render_footer($originalData = ['_LOGGEDIN_' => true], $this->confOtherTheme);

        static::assertSame($originalData, $data);
    }

    /**
     * Test hook_readitlater_render_footer while logged out
     */
    public function testReadItLaterRenderFooterLoggedOut(): void
    {
        $data = hook_readitlater_render_footer([], $this->confDefaultTheme);

        static::assertSame([], $data);
    }

    /**
     * Test hook_readitlater_render_editlink with a new link: checkbox added (unchecked)
     */
    public function testReadItLaterRenderEditLinkDefaultOff(): void
    {
        $originalData = [
            'link_is_new' => true,
            'link' => [],
        ];
        $data = hook_readitlater_render_editlink($originalData, $this->confDefaultTheme);

        static::assertContainsPolyfill(
            '<input type="checkbox" name="readitlater" id="readitlater"  />',
            $data['edit_link_plugin'][0]
        );

        $this->confDefaultTheme = $this->createMock(ConfigManager::class);
        $this->confDefaultTheme->method('get')->with('plugins.READITLATER_DEFAULT_CHECK')->willReturn('0');

        $data = hook_readitlater_render_editlink($originalData, $this->confDefaultTheme);

        static::assertContainsPolyfill(
            '<input type="checkbox" name="readitlater" id="readitlater"  />',
            $data['edit_link_plugin'][0]
        );
    }

    /**
     * Test hook_readitlater_render_editlink with a new link: checkbox added (checked)
     */
    public function testReadItLaterRenderEditLinkDefaultOn(): void
    {
        $this->confDefaultTheme = $this->createMock(ConfigManager::class);
        $this->confDefaultTheme->method('get')->with('plugins.READITLATER_DEFAULT_CHECK')->willReturn('1');
        $originalData = [
            'link_is_new' => true,
            'link' => [],
        ];
        $data = hook_readitlater_render_editlink($originalData, $this->confDefaultTheme);

        static::assertContainsPolyfill(
            '<input type="checkbox" name="readitlater" id="readitlater" checked />',
            $data['edit_link_plugin'][0]
        );
    }

    /**
     * Test hook_readitlater_render_editlink with an existing link: we don't do anything
     */
    public function testReadItLaterRenderEditLinkNotNew(): void
    {
        $originalData = [
            'link_is_new' => false,
            'link' => [],
        ];
        $data = hook_readitlater_render_editlink($originalData, $this->confDefaultTheme);

        static::assertSame($originalData, $data);
    }

    /**
     * Test hook_readitlater_save_link with readitlater not already set and multiple values (defaults to false).
     */
    public function testReadItLaterSaveLinkNewSetting(): void
    {
        $_POST['readitlater'] = true;
        $data = hook_readitlater_save_link([]);

        static::assertTrue($data['additional_content']['readitlater']);

        $_POST['readitlater'] = 'on';
        $data = hook_readitlater_save_link([]);

        static::assertTrue($data['additional_content']['readitlater']);

        $_POST['readitlater'] = false;
        $data = hook_readitlater_save_link([]);

        static::assertFalse($data['additional_content']['readitlater']);

        unset($_POST['readitlater']);
        $data = hook_readitlater_save_link([]);

        static::assertFalse($data['additional_content']['readitlater']);
    }

    /**
     * Test hook_readitlater_save_link with readitlater setting already set.
     */
    public function testReadItLaterSaveLinkExistingSetting(): void
    {
        $data = hook_readitlater_save_link(['additional_content' => ['readitlater' => true]]);
        static::assertTrue($data['additional_content']['readitlater']);

        $data = hook_readitlater_save_link(['additional_content' => ['readitlater' => false]]);
        static::assertFalse($data['additional_content']['readitlater']);
    }

    /**
     * Test hook_readitlater_filter_search_entry
     */
    public function testReadItLaterFilterSearchEntry(): void
    {
        $_SESSION['readitlater-only'] = true;

        $bookmark = new Bookmark();
        static::assertFalse(hook_readitlater_filter_search_entry($bookmark, []));

        $bookmark = new Bookmark();
        $bookmark->setAdditionalContentEntry('readitlater', false);
        static::assertFalse(hook_readitlater_filter_search_entry($bookmark, []));

        $bookmark = new Bookmark();
        $bookmark->setAdditionalContentEntry('readitlater', true);
        static::assertTrue(hook_readitlater_filter_search_entry($bookmark, []));

        $_SESSION['readitlater-only'] = false;

        $bookmark = new Bookmark();
        static::assertTrue(hook_readitlater_filter_search_entry($bookmark, []));

        $bookmark = new Bookmark();
        $bookmark->setAdditionalContentEntry('readitlater', false);
        static::assertTrue(hook_readitlater_filter_search_entry($bookmark, []));

        $bookmark = new Bookmark();
        $bookmark->setAdditionalContentEntry('readitlater', true);
        static::assertTrue(hook_readitlater_filter_search_entry($bookmark, []));

        unset($_SESSION['readitlater-only']);
    }

    public function testReadItLaterGetIconDefaultTheme(): void
    {
        $result = readitlater_get_icon($this->confDefaultTheme, true);
        static::assertSame('<i class="fa fa-eye-slash" aria-hidden="true"></i>', $result);

        $result = readitlater_get_icon($this->confDefaultTheme, false);
        static::assertSame('<i class="fa fa-eye" aria-hidden="true"></i>', $result);
    }

    public function testReadItLaterGetIconOtherTheme(): void
    {
        $result = readitlater_get_icon($this->confOtherTheme, true);
        static::assertSame('Mark as Read', $result);

        $result = readitlater_get_icon($this->confOtherTheme, false);
        static::assertSame('Read it later', $result);
    }
}
