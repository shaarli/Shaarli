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
     * Test mergeDeprecatedConfig while being logged in:
     *      1. init a config file.
     *      2. init a options.php file with update value.
     *      3. merge.
     *      4. check updated value in config file.
     */
    public function testMergeDeprecatedConfig()
    {
        // init
        writeConfig(self::$configFields, true);
        $configCopy = self::$configFields;
        $invert = !$configCopy['privateLinkByDefault'];
        $configCopy['privateLinkByDefault'] = $invert;

        // Use writeConfig to create a options.php
        $configCopy['config']['CONFIG_FILE'] = 'tests/options.php';
        writeConfig($configCopy, true);

        $this->assertTrue(is_file($configCopy['config']['CONFIG_FILE']));

        // merge configs
        mergeDeprecatedConfig(self::$configFields, true);

        // make sure updated field is changed
        include self::$configFields['config']['CONFIG_FILE'];
        $this->assertEquals($invert, $GLOBALS['privateLinkByDefault']);
        $this->assertFalse(is_file($configCopy['config']['CONFIG_FILE']));
    }

    /**
     * Test mergeDeprecatedConfig while being logged in without options file.
     */
    public function testMergeDeprecatedConfigNoFile()
    {
        writeConfig(self::$configFields, true);
        mergeDeprecatedConfig(self::$configFields, true);

        include self::$configFields['config']['CONFIG_FILE'];
        $this->assertEquals(self::$configFields['login'], $GLOBALS['login']);
    }
}
