<?php
/**
 * Config' tests
 */

require_once 'application/Config.php';

/**
 * Unitary tests for Shaarli config related functions
 */
class ConfigTest extends PHPUnit_Framework_TestCase
{
    // Configuration input set.
    private static $configFields;

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
     * Executed before each test.
     */
    public function setUp()
    {
        self::$configFields = array(
            'login' => 'login',
            'hash' => 'hash',
            'salt' => 'salt',
            'timezone' => 'Europe/Paris',
            'title' => 'title',
            'titleLink' => 'titleLink',
            'redirector' => '',
            'disablesessionprotection' => false,
            'privateLinkByDefault' => false,
            'config' => array(
                'CONFIG_FILE' => 'tests/config.php',
                'DATADIR' => 'tests',
                'config1' => 'config1data',
                'config2' => 'config2data',
            )
        );
    }

    /**
     * Executed after each test.
     *
     * @return void
     */
    public function tearDown()
    {
        if (is_file(self::$configFields['config']['CONFIG_FILE'])) {
            unlink(self::$configFields['config']['CONFIG_FILE']);
        }
        if (is_file(self::$pluginPath . '/' . self::$pluginName . '/config.php')) {
            unlink(self::$pluginPath . '/' . self::$pluginName . '/config.php');
        }
    }

    /**
     * Test writeConfig function, valid use case, while being logged in.
     */
    public function testWriteConfig()
    {
        writeConfig(self::$configFields, true);

        include self::$configFields['config']['CONFIG_FILE'];
        $this->assertEquals(self::$configFields['login'], $GLOBALS['login']);
        $this->assertEquals(self::$configFields['hash'], $GLOBALS['hash']);
        $this->assertEquals(self::$configFields['salt'], $GLOBALS['salt']);
        $this->assertEquals(self::$configFields['timezone'], $GLOBALS['timezone']);
        $this->assertEquals(self::$configFields['title'], $GLOBALS['title']);
        $this->assertEquals(self::$configFields['titleLink'], $GLOBALS['titleLink']);
        $this->assertEquals(self::$configFields['redirector'], $GLOBALS['redirector']);
        $this->assertEquals(self::$configFields['disablesessionprotection'], $GLOBALS['disablesessionprotection']);
        $this->assertEquals(self::$configFields['privateLinkByDefault'], $GLOBALS['privateLinkByDefault']);
        $this->assertEquals(self::$configFields['config']['config1'], $GLOBALS['config']['config1']);
        $this->assertEquals(self::$configFields['config']['config2'], $GLOBALS['config']['config2']);
    }

    /**
     * Test writeConfig option while logged in:
     *      1. init fields.
     *      2. update fields, add new sub config, add new root config.
     *      3. rewrite config.
     *      4. check result.
     */
    public function testWriteConfigFieldUpdate()
    {
        writeConfig(self::$configFields, true);
        self::$configFields['title'] = 'ok';
        self::$configFields['config']['config1'] = 'ok';
        self::$configFields['config']['config_new'] = 'ok';
        self::$configFields['new'] = 'should not be saved';
        writeConfig(self::$configFields, true);

        include self::$configFields['config']['CONFIG_FILE'];
        $this->assertEquals('ok', $GLOBALS['title']);
        $this->assertEquals('ok', $GLOBALS['config']['config1']);
        $this->assertEquals('ok', $GLOBALS['config']['config_new']);
        $this->assertFalse(isset($GLOBALS['new']));
    }

    /**
     * Test writeConfig function with an empty array.
     *
     * @expectedException MissingFieldConfigException
     */
    public function testWriteConfigEmpty()
    {
        writeConfig(array(), true);
    }

    /**
     * Test writeConfig function with a missing mandatory field.
     *
     * @expectedException MissingFieldConfigException
     */
    public function testWriteConfigMissingField()
    {
        unset(self::$configFields['login']);
        writeConfig(self::$configFields, true);
    }

    /**
     * Test writeConfig function while being logged out, and there is no config file existing.
     */
    public function testWriteConfigLoggedOutNoFile()
    {
        writeConfig(self::$configFields, false);
    }

    /**
     * Test writeConfig function while being logged out, and a config file already exists.
     *
     * @expectedException UnauthorizedConfigException
     */
    public function testWriteConfigLoggedOutWithFile()
    {
        file_put_contents(self::$configFields['config']['CONFIG_FILE'], '');
        writeConfig(self::$configFields, false);
    }

    /**
     * Test save_plugin_config with valid data.
     *
     * @throws PluginConfigOrderException
     */
    public function testSavePluginConfigValid()
    {
        $data = array(
            'order_plugin1' => 2,   // no plugin related
            'plugin2' => 0,         // new - at the end
            'plugin3' => 0,         // 2nd
            'order_plugin3' => 8,
            'plugin4' => 0,         // 1st
            'order_plugin4' => 5,
        );

        $expected = array(
            'plugin3',
            'plugin4',
            'plugin2',
        );

        $out = save_plugin_config($data);
        $this->assertEquals($expected, $out);
    }

    /**
     * Test save_plugin_config with invalid data.
     *
     * @expectedException              PluginConfigOrderException
     */
    public function testSavePluginConfigInvalid()
    {
        $data = array(
            'plugin2' => 0,
            'plugin3' => 0,
            'order_plugin3' => 0,
            'plugin4' => 0,
            'order_plugin4' => 0,
        );

        save_plugin_config($data);
    }

    /**
     * Test save_plugin_config without data.
     */
    public function testSavePluginConfigEmpty()
    {
        $this->assertEquals(array(), save_plugin_config(array()));
    }

    /**
     * Test validate_plugin_order with valid data.
     */
    public function testValidatePluginOrderValid()
    {
        $data = array(
            'order_plugin1' => 2,
            'plugin2' => 0,
            'plugin3' => 0,
            'order_plugin3' => 1,
            'plugin4' => 0,
            'order_plugin4' => 5,
        );

        $this->assertTrue(validate_plugin_order($data));
    }

    /**
     * Test validate_plugin_order with invalid data.
     */
    public function testValidatePluginOrderInvalid()
    {
        $data = array(
            'order_plugin1' => 2,
            'order_plugin3' => 1,
            'order_plugin4' => 1,
        );

        $this->assertFalse(validate_plugin_order($data));
    }

    /**
     * Test load_plugin_parameter_values.
     */
    public function testLoadPluginParameterValues()
    {
        $plugins = array(
            'plugin_name' => array(
                'parameters' => array(
                    'param1' => true,
                    'param2' => false,
                    'param3' => '',
                )
            )
        );

        $parameters = array(
            'param1' => 'value1',
            'param2' => 'value2',
        );

        $result = load_plugin_parameter_values($plugins, $parameters);
        $this->assertEquals('value1', $result['plugin_name']['parameters']['param1']);
        $this->assertEquals('value2', $result['plugin_name']['parameters']['param2']);
        $this->assertEquals('', $result['plugin_name']['parameters']['param3']);
    }

    /**
     * Test write_plugin_config():
     *   1. Without an existing file: don't write.
     *   2. With an existing file: write.
     */
    public function testWritePluginConfig() {
        $settings = array(
            'SETTING_1' => 'value 1',
            'SETTING_2' => 'value 2',
        );
        PluginManager::$PLUGINS_PATH = self::$pluginPath;

        $this->assertFalse(write_plugin_config(self::$pluginName, $settings));
        $settingsFile = PluginManager::$PLUGINS_PATH . '/' . self::$pluginName . '/config.php';
        touch($settingsFile);
        $this->assertTrue(write_plugin_config(self::$pluginName, $settings));
        include $settingsFile;
        $this->assertEquals($settings['SETTING_1'], $GLOBALS['plugins']['SETTING_1']);
        $this->assertEquals($settings['SETTING_2'], $GLOBALS['plugins']['SETTING_2']);
    }
}
