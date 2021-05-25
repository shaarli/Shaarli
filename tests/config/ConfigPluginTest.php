<?php

namespace Shaarli\Config;

use Shaarli\Config\Exception\PluginConfigOrderException;
use Shaarli\Plugin\PluginManager;
use Shaarli\TestCase;

/**
 * Unitary tests for Shaarli config related functions
 */
class ConfigPluginTest extends TestCase
{
    /**
     * Test save_plugin_config with valid data.
     *
     * @throws PluginConfigOrderException
     */
    public function testSavePluginConfigValid()
    {
        $data = [
            'order_plugin1' => 2,   // no plugin related
            'plugin2' => 0,         // new - at the end
            'plugin3' => 0,         // 2nd
            'order_plugin3' => 8,
            'plugin4' => 0,         // 1st
            'order_plugin4' => 5,
        ];

        $expected = [
            'plugin3',
            'plugin4',
            'plugin2',
        ];

        mkdir($path = __DIR__ . '/folder');
        PluginManager::$PLUGINS_PATH = $path;
        array_map(function (string $plugin) use ($path) {
            touch($path . '/' . $plugin);
        }, $expected);

        $out = save_plugin_config($data);
        $this->assertEquals($expected, $out);

        array_map(function (string $plugin) use ($path) {
            unlink($path . '/' . $plugin);
        }, $expected);
        rmdir($path);
    }

    /**
     * Test save_plugin_config with invalid data.
     */
    public function testSavePluginConfigInvalid()
    {
        $this->expectException(PluginConfigOrderException::class);

        $data = [
            'plugin2' => 0,
            'plugin3' => 0,
            'order_plugin3' => 0,
            'plugin4' => 0,
            'order_plugin4' => 0,
        ];

        save_plugin_config($data);
    }

    /**
     * Test save_plugin_config without data.
     */
    public function testSavePluginConfigEmpty()
    {
        $this->assertEquals([], save_plugin_config([]));
    }

    /**
     * Test validate_plugin_order with valid data.
     */
    public function testValidatePluginOrderValid()
    {
        $data = [
            'order_plugin1' => 2,
            'plugin2' => 0,
            'plugin3' => 0,
            'order_plugin3' => 1,
            'plugin4' => 0,
            'order_plugin4' => 5,
        ];

        $this->assertTrue(validate_plugin_order($data));
    }

    /**
     * Test validate_plugin_order with invalid data.
     */
    public function testValidatePluginOrderInvalid()
    {
        $data = [
            'order_plugin1' => 2,
            'order_plugin3' => 1,
            'order_plugin4' => 1,
        ];

        $this->assertFalse(validate_plugin_order($data));
    }

    /**
     * Test load_plugin_parameter_values.
     */
    public function testLoadPluginParameterValues()
    {
        $plugins = [
            'plugin_name' => [
                'parameters' => [
                    'param1' => ['value' => false],
                    'param2' => ['value' => false],
                    'param3' => ['value' => ''],
                ]
            ]
        ];

        $parameters = [
            'param1' => 'value1',
            'param2' => 'value2',
        ];

        $result = load_plugin_parameter_values($plugins, $parameters);
        $this->assertEquals('value1', $result['plugin_name']['parameters']['param1']['value']);
        $this->assertEquals('value2', $result['plugin_name']['parameters']['param2']['value']);
        $this->assertEquals('', $result['plugin_name']['parameters']['param3']['value']);
    }
}
