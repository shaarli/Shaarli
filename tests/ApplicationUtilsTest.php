<?php
/**
 * ApplicationUtils' tests
 */

require_once 'application/config/ConfigManager.php';
require_once 'application/ApplicationUtils.php';

/**
 * Fake ApplicationUtils class to avoid HTTP requests
 */
class FakeApplicationUtils extends ApplicationUtils
{
    public static $VERSION_CODE = '';

    /**
     * Toggle HTTP requests, allow overriding the version code
     */
    public static function getLatestGitVersionCode($url, $timeout=0)
    {
        return self::$VERSION_CODE;
    }
}


/**
 * Unitary tests for Shaarli utilities
 */
class ApplicationUtilsTest extends PHPUnit_Framework_TestCase
{
    protected static $testUpdateFile = 'sandbox/update.txt';
    protected static $testVersion = '0.5.0';
    protected static $versionPattern = '/^\d+\.\d+\.\d+$/';

    /**
     * Reset test data for each test
     */
    public function setUp()
    {
        FakeApplicationUtils::$VERSION_CODE = '';
        if (file_exists(self::$testUpdateFile)) {
            unlink(self::$testUpdateFile);
        }
    }

    /**
     * Retrieve the latest version code available on Git
     *
     * Expected format: Semantic Versioning - major.minor.patch
     */
    public function testGetLatestGitVersionCode()
    {
        $testTimeout = 10;

        $this->assertEquals(
            '0.5.4',
            ApplicationUtils::getLatestGitVersionCode(
                'https://raw.githubusercontent.com/shaarli/Shaarli/'
               .'v0.5.4/shaarli_version.php',
                $testTimeout
            )
        );
        $this->assertRegExp(
            self::$versionPattern,
            ApplicationUtils::getLatestGitVersionCode(
                'https://raw.githubusercontent.com/shaarli/Shaarli/'
               .'master/shaarli_version.php',
                $testTimeout
            )
        );
    }

    /**
     * Attempt to retrieve the latest version from an invalid URL
     */
    public function testGetLatestGitVersionCodeInvalidUrl()
    {
        $oldlog = ini_get('error_log');
        ini_set('error_log', '/dev/null');
        $this->assertFalse(
            ApplicationUtils::getLatestGitVersionCode('htttp://null.io', 1)
        );
        ini_set('error_log', $oldlog);
    }

    /**
     * Test update checks - the user is logged off
     */
    public function testCheckUpdateLoggedOff()
    {
        $this->assertFalse(
            ApplicationUtils::checkUpdate(self::$testVersion, 'null', 0, false, false)
        );
    }

    /**
     * Test update checks - the user has disabled updates
     */
    public function testCheckUpdateUserDisabled()
    {
        $this->assertFalse(
            ApplicationUtils::checkUpdate(self::$testVersion, 'null', 0, false, true)
        );
    }

    /**
     * A newer version is available
     */
    public function testCheckUpdateNewVersionAvailable()
    {
        $newVersion = '1.8.3';
        FakeApplicationUtils::$VERSION_CODE = $newVersion;

        $version = FakeApplicationUtils::checkUpdate(
            self::$testVersion,
            self::$testUpdateFile,
            100,
            true,
            true
        );

        $this->assertEquals($newVersion, $version);
    }

    /**
     * No available information about versions
     */
    public function testCheckUpdateNewVersionUnavailable()
    {
        $version = FakeApplicationUtils::checkUpdate(
            self::$testVersion,
            self::$testUpdateFile,
            100,
            true,
            true
        );

        $this->assertFalse($version);
    }

    /**
     * Test update checks - invalid Git branch
     * @expectedException              Exception
     * @expectedExceptionMessageRegExp /Invalid branch selected for updates/
     */
    public function testCheckUpdateInvalidGitBranch()
    {
        ApplicationUtils::checkUpdate('', 'null', 0, true, true, 'unstable');
    }

    /**
     * Shaarli is up-to-date
     */
    public function testCheckUpdateNewVersionUpToDate()
    {
        FakeApplicationUtils::$VERSION_CODE = self::$testVersion;

        $version = FakeApplicationUtils::checkUpdate(
            self::$testVersion,
            self::$testUpdateFile,
            100,
            true,
            true
        );

        $this->assertFalse($version);
    }

    /**
     * Time-traveller's Shaarli
     */
    public function testCheckUpdateNewVersionMaartiMcFly()
    {
        FakeApplicationUtils::$VERSION_CODE = '0.4.1';

        $version = FakeApplicationUtils::checkUpdate(
            self::$testVersion,
            self::$testUpdateFile,
            100,
            true,
            true
        );

        $this->assertFalse($version);
    }

    /**
     * The version has been checked recently and Shaarli is up-to-date
     */
    public function testCheckUpdateNewVersionTwiceUpToDate()
    {
        FakeApplicationUtils::$VERSION_CODE = self::$testVersion;

        // Create the update file
        $version = FakeApplicationUtils::checkUpdate(
            self::$testVersion,
            self::$testUpdateFile,
            100,
            true,
            true
        );

        $this->assertFalse($version);

        // Reuse the update file
        $version = FakeApplicationUtils::checkUpdate(
            self::$testVersion,
            self::$testUpdateFile,
            100,
            true,
            true
        );

        $this->assertFalse($version);
    }

    /**
     * The version has been checked recently and Shaarli is outdated
     */
    public function testCheckUpdateNewVersionTwiceOutdated()
    {
        $newVersion = '1.8.3';
        FakeApplicationUtils::$VERSION_CODE = $newVersion;

        // Create the update file
        $version = FakeApplicationUtils::checkUpdate(
            self::$testVersion,
            self::$testUpdateFile,
            100,
            true,
            true
        );
        $this->assertEquals($newVersion, $version);

        // Reuse the update file
        $version = FakeApplicationUtils::checkUpdate(
            self::$testVersion,
            self::$testUpdateFile,
            100,
            true,
            true
        );
        $this->assertEquals($newVersion, $version);
    }

    /**
     * Check supported PHP versions
     */
    public function testCheckSupportedPHPVersion()
    {
        $minVersion = '5.3';
        ApplicationUtils::checkPHPVersion($minVersion, '5.4.32');
        ApplicationUtils::checkPHPVersion($minVersion, '5.5');
        ApplicationUtils::checkPHPVersion($minVersion, '5.6.10');
    }

    /**
     * Check a unsupported PHP version
     * @expectedException              Exception
     * @expectedExceptionMessageRegExp /Your PHP version is obsolete/
     */
    public function testCheckSupportedPHPVersion51()
    {
        ApplicationUtils::checkPHPVersion('5.3', '5.1.0');
    }

    /**
     * Check another unsupported PHP version
     * @expectedException              Exception
     * @expectedExceptionMessageRegExp /Your PHP version is obsolete/
     */
    public function testCheckSupportedPHPVersion52()
    {
        ApplicationUtils::checkPHPVersion('5.3', '5.2');
    }

    /**
     * Checks resource permissions for the current Shaarli installation
     */
    public function testCheckCurrentResourcePermissions()
    {
        $conf = new ConfigManager('');
        $conf->set('resource.thumbnails_cache', 'cache');
        $conf->set('resource.config', 'data/config.php');
        $conf->set('resource.data_dir', 'data');
        $conf->set('resource.datastore', 'data/datastore.php');
        $conf->set('resource.ban_file', 'data/ipbans.php');
        $conf->set('resource.log', 'data/log.txt');
        $conf->set('resource.page_cache', 'pagecache');
        $conf->set('resource.raintpl_tmp', 'tmp');
        $conf->set('resource.raintpl_tpl', 'tpl');
        $conf->set('resource.theme', 'default');
        $conf->set('resource.update_check', 'data/lastupdatecheck.txt');

        $this->assertEquals(
            array(),
            ApplicationUtils::checkResourcePermissions($conf)
        );
    }

    /**
     * Checks resource permissions for a non-existent Shaarli installation
     */
    public function testCheckCurrentResourcePermissionsErrors()
    {
        $conf = new ConfigManager('');
        $conf->set('resource.thumbnails_cache', 'null/cache');
        $conf->set('resource.config', 'null/data/config.php');
        $conf->set('resource.data_dir', 'null/data');
        $conf->set('resource.datastore', 'null/data/store.php');
        $conf->set('resource.ban_file', 'null/data/ipbans.php');
        $conf->set('resource.log', 'null/data/log.txt');
        $conf->set('resource.page_cache', 'null/pagecache');
        $conf->set('resource.raintpl_tmp', 'null/tmp');
        $conf->set('resource.raintpl_tpl', 'null/tpl');
        $conf->set('resource.raintpl_theme', 'null/tpl/default');
        $conf->set('resource.update_check', 'null/data/lastupdatecheck.txt');
        $this->assertEquals(
            array(
                '"null/tpl" directory is not readable',
                '"null/tpl/default" directory is not readable',
                '"null/cache" directory is not readable',
                '"null/cache" directory is not writable',
                '"null/data" directory is not readable',
                '"null/data" directory is not writable',
                '"null/pagecache" directory is not readable',
                '"null/pagecache" directory is not writable',
                '"null/tmp" directory is not readable',
                '"null/tmp" directory is not writable'
            ),
            ApplicationUtils::checkResourcePermissions($conf)
        );
    }
}
