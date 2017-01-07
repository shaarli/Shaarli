<?php

require_once 'application/config/ConfigManager.php';
require_once 'tests/Updater/DummyUpdater.php';

/**
 * Class UpdaterTest.
 * Runs unit tests against the Updater class.
 */
class UpdaterTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var string Path to test datastore.
     */
    protected static $testDatastore = 'sandbox/datastore.php';

    /**
     * @var string Config file path (without extension).
     */
    protected static $configFile = 'tests/utils/config/configJson';

    /**
     * @var ConfigManager
     */
    protected $conf;

    /**
     * Executed before each test.
     */
    public function setUp()
    {
        $this->conf = new ConfigManager(self::$configFile);
    }

    /**
     * Test read_updates_file with an empty/missing file.
     */
    public function testReadEmptyUpdatesFile()
    {
        $this->assertEquals(array(), read_updates_file(''));
        $updatesFile = $this->conf->get('resource.data_dir') . '/updates.txt';
        touch($updatesFile);
        $this->assertEquals(array(), read_updates_file($updatesFile));
        unlink($updatesFile);
    }

    /**
     * Test read/write updates file.
     */
    public function testReadWriteUpdatesFile()
    {
        $updatesFile = $this->conf->get('resource.data_dir') . '/updates.txt';
        $updatesMethods = array('m1', 'm2', 'm3');

        write_updates_file($updatesFile, $updatesMethods);
        $readMethods = read_updates_file($updatesFile);
        $this->assertEquals($readMethods, $updatesMethods);

        // Update
        $updatesMethods[] = 'm4';
        write_updates_file($updatesFile, $updatesMethods);
        $readMethods = read_updates_file($updatesFile);
        $this->assertEquals($readMethods, $updatesMethods);
        unlink($updatesFile);
    }

    /**
     * Test errors in write_updates_file(): empty updates file.
     *
     * @expectedException              Exception
     * @expectedExceptionMessageRegExp /Updates file path is not set(.*)/
     */
    public function testWriteEmptyUpdatesFile()
    {
        write_updates_file('', array('test'));
    }

    /**
     * Test errors in write_updates_file(): not writable updates file.
     *
     * @expectedException              Exception
     * @expectedExceptionMessageRegExp /Unable to write(.*)/
     */
    public function testWriteUpdatesFileNotWritable()
    {
        $updatesFile = $this->conf->get('resource.data_dir') . '/updates.txt';
        touch($updatesFile);
        chmod($updatesFile, 0444);
        try {
            @write_updates_file($updatesFile, array('test'));
        } catch (Exception $e) {
            unlink($updatesFile);
            throw $e;
        }
    }

    /**
     * Test the update() method, with no update to run.
     *   1. Everything already run.
     *   2. User is logged out.
     */
    public function testNoUpdates()
    {
        $updates = array(
            'updateMethodDummy1',
            'updateMethodDummy2',
            'updateMethodDummy3',
            'updateMethodException',
        );
        $updater = new DummyUpdater($updates, array(), $this->conf, true);
        $this->assertEquals(array(), $updater->update());

        $updater = new DummyUpdater(array(), array(), $this->conf, false);
        $this->assertEquals(array(), $updater->update());
    }

    /**
     * Test the update() method, with all updates to run (except the failing one).
     */
    public function testUpdatesFirstTime()
    {
        $updates = array('updateMethodException',);
        $expectedUpdates = array(
            'updateMethodDummy1',
            'updateMethodDummy2',
            'updateMethodDummy3',
        );
        $updater = new DummyUpdater($updates, array(), $this->conf, true);
        $this->assertEquals($expectedUpdates, $updater->update());
    }

    /**
     * Test the update() method, only one update to run.
     */
    public function testOneUpdate()
    {
        $updates = array(
            'updateMethodDummy1',
            'updateMethodDummy3',
            'updateMethodException',
        );
        $expectedUpdate = array('updateMethodDummy2');

        $updater = new DummyUpdater($updates, array(), $this->conf, true);
        $this->assertEquals($expectedUpdate, $updater->update());
    }

    /**
     * Test Update failed.
     *
     * @expectedException UpdaterException
     */
    public function testUpdateFailed()
    {
        $updates = array(
            'updateMethodDummy1',
            'updateMethodDummy2',
            'updateMethodDummy3',
        );

        $updater = new DummyUpdater($updates, array(), $this->conf, true);
        $updater->update();
    }

    /**
     * Test update mergeDeprecatedConfig:
     *      1. init a config file.
     *      2. init a options.php file with update value.
     *      3. merge.
     *      4. check updated value in config file.
     */
    public function testUpdateMergeDeprecatedConfig()
    {
        $this->conf->setConfigFile('tests/utils/config/configPhp');
        $this->conf->reset();

        $optionsFile = 'tests/Updater/options.php';
        $options = '<?php
$GLOBALS[\'privateLinkByDefault\'] = true;';
        file_put_contents($optionsFile, $options);

        // tmp config file.
        $this->conf->setConfigFile('tests/Updater/config');

        // merge configs
        $updater = new Updater(array(), array(), $this->conf, true);
        // This writes a new config file in tests/Updater/config.php
        $updater->updateMethodMergeDeprecatedConfigFile();

        // make sure updated field is changed
        $this->conf->reload();
        $this->assertTrue($this->conf->get('privacy.default_private_links'));
        $this->assertFalse(is_file($optionsFile));
        // Delete the generated file.
        unlink($this->conf->getConfigFileExt());
    }

    /**
     * Test mergeDeprecatedConfig in without options file.
     */
    public function testMergeDeprecatedConfigNoFile()
    {
        $updater = new Updater(array(), array(), $this->conf, true);
        $updater->updateMethodMergeDeprecatedConfigFile();

        $this->assertEquals('root', $this->conf->get('credentials.login'));
    }

    /**
     * Test renameDashTags update method.
     */
    public function testRenameDashTags()
    {
        $refDB = new ReferenceLinkDB();
        $refDB->write(self::$testDatastore);
        $linkDB = new LinkDB(self::$testDatastore, true, false);

        $this->assertEmpty($linkDB->filterSearch(array('searchtags' => 'exclude')));
        $updater = new Updater(array(), $linkDB, $this->conf, true);
        $updater->updateMethodRenameDashTags();
        $this->assertNotEmpty($linkDB->filterSearch(array('searchtags' =>  'exclude')));
    }

    /**
     * Convert old PHP config file to JSON config.
     */
    public function testConfigToJson()
    {
        $configFile = 'tests/utils/config/configPhp';
        $this->conf->setConfigFile($configFile);
        $this->conf->reset();

        // The ConfigIO is initialized with ConfigPhp.
        $this->assertTrue($this->conf->getConfigIO() instanceof ConfigPhp);

        $updater = new Updater(array(), array(), $this->conf, false);
        $done = $updater->updateMethodConfigToJson();
        $this->assertTrue($done);

        // The ConfigIO has been updated to ConfigJson.
        $this->assertTrue($this->conf->getConfigIO() instanceof ConfigJson);
        $this->assertTrue(file_exists($this->conf->getConfigFileExt()));

        // Check JSON config data.
        $this->conf->reload();
        $this->assertEquals('root', $this->conf->get('credentials.login'));
        $this->assertEquals('lala', $this->conf->get('redirector.url'));
        $this->assertEquals('data/datastore.php', $this->conf->get('resource.datastore'));
        $this->assertEquals('1', $this->conf->get('plugins.WALLABAG_VERSION'));

        rename($configFile . '.save.php', $configFile . '.php');
        unlink($this->conf->getConfigFileExt());
    }

    /**
     * Launch config conversion update with an existing JSON file => nothing to do.
     */
    public function testConfigToJsonNothingToDo()
    {
        $filetime = filemtime($this->conf->getConfigFileExt());
        $updater = new Updater(array(), array(), $this->conf, false);
        $done = $updater->updateMethodConfigToJson();
        $this->assertTrue($done);
        $expected = filemtime($this->conf->getConfigFileExt());
        $this->assertEquals($expected, $filetime);
    }

    /**
     * Test escapeUnescapedConfig with valid data.
     */
    public function testEscapeConfig()
    {
        $sandbox = 'sandbox/config';
        copy(self::$configFile . '.json.php', $sandbox . '.json.php');
        $this->conf = new ConfigManager($sandbox);
        $title = '<script>alert("title");</script>';
        $headerLink = '<script>alert("header_link");</script>';
        $redirectorUrl = '<script>alert("redirector");</script>';
        $this->conf->set('general.title', $title);
        $this->conf->set('general.header_link', $headerLink);
        $this->conf->set('redirector.url', $redirectorUrl);
        $updater = new Updater(array(), array(), $this->conf, true);
        $done = $updater->updateMethodEscapeUnescapedConfig();
        $this->assertTrue($done);
        $this->conf->reload();
        $this->assertEquals(escape($title), $this->conf->get('general.title'));
        $this->assertEquals(escape($headerLink), $this->conf->get('general.header_link'));
        $this->assertEquals(escape($redirectorUrl), $this->conf->get('redirector.url'));
        unlink($sandbox . '.json.php');
    }

    /**
     * Test updateMethodApiSettings(): create default settings for the API (enabled + secret).
     */
    public function testUpdateApiSettings()
    {
        $confFile = 'sandbox/config';
        copy(self::$configFile .'.json.php', $confFile .'.json.php');
        $conf = new ConfigManager($confFile);
        $updater = new Updater(array(), array(), $conf, true);

        $this->assertFalse($conf->exists('api.enabled'));
        $this->assertFalse($conf->exists('api.secret'));
        $updater->updateMethodApiSettings();
        $conf->reload();
        $this->assertTrue($conf->get('api.enabled'));
        $this->assertTrue($conf->exists('api.secret'));
        unlink($confFile .'.json.php');
    }

    /**
     * Test updateMethodApiSettings(): already set, do nothing.
     */
    public function testUpdateApiSettingsNothingToDo()
    {
        $confFile = 'sandbox/config';
        copy(self::$configFile .'.json.php', $confFile .'.json.php');
        $conf = new ConfigManager($confFile);
        $conf->set('api.enabled', false);
        $conf->set('api.secret', '');
        $updater = new Updater(array(), array(), $conf, true);
        $updater->updateMethodApiSettings();
        $this->assertFalse($conf->get('api.enabled'));
        $this->assertEmpty($conf->get('api.secret'));
        unlink($confFile .'.json.php');
    }

    /**
     * Test updateMethodDatastoreIds().
     */
    public function testDatastoreIds()
    {
        $links = array(
            '20121206_182539' => array(
                'linkdate' => '20121206_182539',
                'title' => 'Geek and Poke',
                'url' => 'http://geek-and-poke.com/',
                'description' => 'desc',
                'tags' => 'dev cartoon tag1  tag2   tag3  tag4   ',
                'updated' => '20121206_190301',
                'private' => false,
            ),
            '20121206_172539' => array(
                'linkdate' => '20121206_172539',
                'title' => 'UserFriendly - Samba',
                'url' => 'http://ars.userfriendly.org/cartoons/?id=20010306',
                'description' => '',
                'tags' => 'samba cartoon web',
                'private' => false,
            ),
            '20121206_142300' => array(
                'linkdate' => '20121206_142300',
                'title' => 'UserFriendly - Web Designer',
                'url' => 'http://ars.userfriendly.org/cartoons/?id=20121206',
                'description' => 'Naming conventions... #private',
                'tags' => 'samba cartoon web',
                'private' => true,
            ),
        );
        $refDB = new ReferenceLinkDB();
        $refDB->setLinks($links);
        $refDB->write(self::$testDatastore);
        $linkDB = new LinkDB(self::$testDatastore, true, false);

        $checksum = hash_file('sha1', self::$testDatastore);

        $this->conf->set('resource.data_dir', 'sandbox');
        $this->conf->set('resource.datastore', self::$testDatastore);

        $updater = new Updater(array(), $linkDB, $this->conf, true);
        $this->assertTrue($updater->updateMethodDatastoreIds());

        $linkDB = new LinkDB(self::$testDatastore, true, false);

        $backup = glob($this->conf->get('resource.data_dir') . '/datastore.'. date('YmdH') .'*.php');
        $backup = $backup[0];

        $this->assertFileExists($backup);
        $this->assertEquals($checksum, hash_file('sha1', $backup));
        unlink($backup);

        $this->assertEquals(3, count($linkDB));
        $this->assertTrue(isset($linkDB[0]));
        $this->assertFalse(isset($linkDB[0]['linkdate']));
        $this->assertEquals(0, $linkDB[0]['id']);
        $this->assertEquals('UserFriendly - Web Designer', $linkDB[0]['title']);
        $this->assertEquals('http://ars.userfriendly.org/cartoons/?id=20121206', $linkDB[0]['url']);
        $this->assertEquals('Naming conventions... #private', $linkDB[0]['description']);
        $this->assertEquals('samba cartoon web', $linkDB[0]['tags']);
        $this->assertTrue($linkDB[0]['private']);
        $this->assertEquals(DateTime::createFromFormat(LinkDB::LINK_DATE_FORMAT, '20121206_142300'), $linkDB[0]['created']);

        $this->assertTrue(isset($linkDB[1]));
        $this->assertFalse(isset($linkDB[1]['linkdate']));
        $this->assertEquals(1, $linkDB[1]['id']);
        $this->assertEquals('UserFriendly - Samba', $linkDB[1]['title']);
        $this->assertEquals(DateTime::createFromFormat(LinkDB::LINK_DATE_FORMAT, '20121206_172539'), $linkDB[1]['created']);

        $this->assertTrue(isset($linkDB[2]));
        $this->assertFalse(isset($linkDB[2]['linkdate']));
        $this->assertEquals(2, $linkDB[2]['id']);
        $this->assertEquals('Geek and Poke', $linkDB[2]['title']);
        $this->assertEquals(DateTime::createFromFormat(LinkDB::LINK_DATE_FORMAT, '20121206_182539'), $linkDB[2]['created']);
        $this->assertEquals(DateTime::createFromFormat(LinkDB::LINK_DATE_FORMAT, '20121206_190301'), $linkDB[2]['updated']);
    }

    /**
     * Test updateMethodDatastoreIds() with the update already applied: nothing to do.
     */
    public function testDatastoreIdsNothingToDo()
    {
        $refDB = new ReferenceLinkDB();
        $refDB->write(self::$testDatastore);
        $linkDB = new LinkDB(self::$testDatastore, true, false);

        $this->conf->set('resource.data_dir', 'sandbox');
        $this->conf->set('resource.datastore', self::$testDatastore);

        $checksum = hash_file('sha1', self::$testDatastore);
        $updater = new Updater(array(), $linkDB, $this->conf, true);
        $this->assertTrue($updater->updateMethodDatastoreIds());
        $this->assertEquals($checksum, hash_file('sha1', self::$testDatastore));
    }

    /**
     * Test defaultTheme update with default settings: nothing to do.
     */
    public function testDefaultThemeWithDefaultSettings()
    {
        $sandbox = 'sandbox/config';
        copy(self::$configFile . '.json.php', $sandbox . '.json.php');
        $this->conf = new ConfigManager($sandbox);
        $updater = new Updater([], [], $this->conf, true);
        $this->assertTrue($updater->updateMethodDefaultTheme());

        $this->assertEquals('tpl/', $this->conf->get('resource.raintpl_tpl'));
        $this->assertEquals('default', $this->conf->get('resource.theme'));
        $this->conf = new ConfigManager($sandbox);
        $this->assertEquals('tpl/', $this->conf->get('resource.raintpl_tpl'));
        $this->assertEquals('default', $this->conf->get('resource.theme'));
        unlink($sandbox . '.json.php');
    }

    /**
     * Test defaultTheme update with a custom theme in a subfolder
     */
    public function testDefaultThemeWithCustomTheme()
    {
        $theme = 'iamanartist';
        $sandbox = 'sandbox/config';
        copy(self::$configFile . '.json.php', $sandbox . '.json.php');
        $this->conf = new ConfigManager($sandbox);
        mkdir('sandbox/'. $theme);
        touch('sandbox/'. $theme .'/linklist.html');
        $this->conf->set('resource.raintpl_tpl', 'sandbox/'. $theme .'/');
        $updater = new Updater([], [], $this->conf, true);
        $this->assertTrue($updater->updateMethodDefaultTheme());

        $this->assertEquals('sandbox', $this->conf->get('resource.raintpl_tpl'));
        $this->assertEquals($theme, $this->conf->get('resource.theme'));
        $this->conf = new ConfigManager($sandbox);
        $this->assertEquals('sandbox', $this->conf->get('resource.raintpl_tpl'));
        $this->assertEquals($theme, $this->conf->get('resource.theme'));
        unlink($sandbox . '.json.php');
        unlink('sandbox/'. $theme .'/linklist.html');
        rmdir('sandbox/'. $theme);
    }
}
