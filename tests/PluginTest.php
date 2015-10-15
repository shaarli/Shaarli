<?php

/**
 * Plugin tests
 */

require_once 'application/Plugin.php';

/**
 * Unit tests for Plugins
 */
class PluginTest extends PHPUnit_Framework_TestCase
{
    /**
     * Path to tests plugin.
     * @var string $pluginPath
     */
    private static $pluginPath = 'tests/plugins';

    /**
     * Test plugin.
     * @var string $pluginName
     */
    private static $pluginName = 'test';

    /**
     * Test plugin loading and hook execution.
     *
     * @return void
     */
    public function testPlugin()
    {
        $pluginManager = PluginManager::getInstance();

        PluginManager::$PLUGINS_PATH = self::$pluginPath;
        $pluginManager->load(array(self::$pluginName));

        $this->assertTrue(function_exists('hook_test_random'));

        $data = array(0 => 'woot');
        $pluginManager->executeHooks('random', $data);
        $this->assertEquals('woot', $data[1]);

        $data = array(0 => 'woot');
        $pluginManager->executeHooks('random', $data, array('target' => 'test'));
        $this->assertEquals('page test', $data[1]);

        $data = array(0 => 'woot');
        $pluginManager->executeHooks('random', $data, array('loggedin' => true));
        $this->assertEquals('loggedin', $data[1]);
    }

    /**
     * Test missing plugin loading.
     *
     * @return void
     */
    public function testPluginNotFound()
    {
        $pluginManager = PluginManager::getInstance();

        $pluginManager->load(array());

        $pluginManager->load(array('nope', 'renope'));
    }
}