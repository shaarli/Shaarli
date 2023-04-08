<?php

namespace Shaarli\Helper;

use Shaarli\Config\ConfigManager;
use Shaarli\Tests\Utils\FakeApplicationUtils;

/**
 * Unitary tests for Shaarli utilities
 */
class ApplicationUtilsTest extends \Shaarli\TestCase
{
    protected static $testUpdateFile = 'sandbox/update.txt';
    protected static $testVersion = '0.5.0';
    protected static $versionPattern = '/^\d+\.\d+\.\d+$/';

    /**
     * Reset test data for each test
     */
    protected function setUp(): void
    {
        FakeApplicationUtils::$VERSION_CODE = '';
        if (file_exists(self::$testUpdateFile)) {
            unlink(self::$testUpdateFile);
        }
    }

    /**
     * Remove test version file if it exists
     */
    protected function tearDown(): void
    {
        if (is_file('sandbox/version.php')) {
            unlink('sandbox/version.php');
        }
    }

    /**
     * Retrieve the latest version code available on Git
     *
     * Expected format: Semantic Versioning - major.minor.patch
     */
    public function testGetVersionCode()
    {
        $testTimeout = 10;

        $this->assertEquals(
            '0.5.4',
            ApplicationUtils::getVersion(
                'https://raw.githubusercontent.com/shaarli/Shaarli/'
                . 'v0.5.4/shaarli_version.php',
                $testTimeout
            )
        );
        $this->assertRegExp(
            self::$versionPattern,
            ApplicationUtils::getVersion(
                'https://raw.githubusercontent.com/shaarli/Shaarli/'
                . 'latest/shaarli_version.php',
                $testTimeout
            )
        );
    }

    /**
     * Attempt to retrieve the latest version from an invalid File
     */
    public function testGetVersionCodeFromFile()
    {
        file_put_contents('sandbox/version.php', '<?php /* 1.2.3 */ ?>' . PHP_EOL);
        $this->assertEquals(
            '1.2.3',
            ApplicationUtils::getVersion('sandbox/version.php', 1)
        );
    }

    /**
     * Attempt to retrieve the latest version from an invalid File
     */
    public function testGetVersionCodeInvalidFile()
    {
        $oldlog = ini_get('error_log');
        ini_set('error_log', '/dev/null');
        $this->assertFalse(
            ApplicationUtils::getVersion('idontexist', 1)
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
        $this->assertTrue(ApplicationUtils::checkPHPVersion($minVersion, '5.4.32'));
        $this->assertTrue(ApplicationUtils::checkPHPVersion($minVersion, '5.5'));
        $this->assertTrue(ApplicationUtils::checkPHPVersion($minVersion, '5.6.10'));
    }

    /**
     * Check a unsupported PHP version
     */
    public function testCheckSupportedPHPVersion51()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageRegExp('/Your PHP version is obsolete/');

        $this->assertTrue(ApplicationUtils::checkPHPVersion('5.3', '5.1.0'));
    }

    /**
     * Check another unsupported PHP version
     */
    public function testCheckSupportedPHPVersion52()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageRegExp('/Your PHP version is obsolete/');

        $this->assertTrue(ApplicationUtils::checkPHPVersion('5.3', '5.2'));
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
            [],
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
            [
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
            ],
            ApplicationUtils::checkResourcePermissions($conf)
        );
    }

    /**
     * Checks resource permissions in minimal mode.
     */
    public function testCheckCurrentResourcePermissionsErrorsMinimalMode(): void
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

        static::assertSame(
            [
                '"null/tpl" directory is not readable',
                '"null/tpl/default" directory is not readable',
                '"null/tmp" directory is not readable',
                '"null/tmp" directory is not writable'
            ],
            ApplicationUtils::checkResourcePermissions($conf, true)
        );
    }

    /**
     * Check update with 'dev' as curent version (master branch).
     * It should always return false.
     */
    public function testCheckUpdateDev()
    {
        $this->assertFalse(
            ApplicationUtils::checkUpdate('dev', self::$testUpdateFile, 100, true, true)
        );
    }

    /**
     * Check update with a short git object name as curent version (Docker build).
     * It should always return false.
     */
    public function testCheckUpdateDevHash()
    {
        $this->assertFalse(
            ApplicationUtils::checkUpdate('abc123d', self::$testUpdateFile, 100, true, true)
        );
    }

    /**
     * Basic test of getPhpExtensionsRequirement()
     */
    public function testGetPhpExtensionsRequirementSimple(): void
    {
        static::assertCount(8, ApplicationUtils::getPhpExtensionsRequirement());
        static::assertSame([
            'name' => 'json',
            'required' => true,
            'desc' => 'Configuration parsing',
            'loaded' => true,
        ], ApplicationUtils::getPhpExtensionsRequirement()[0]);
    }

    /**
     * Test getPhpEol with a known version: 7.4 -> 2022
     */
    public function testGetKnownPhpEol(): void
    {
        static::assertSame('2022-11-28', ApplicationUtils::getPhpEol('7.4.7'));
    }

    /**
     * Test getPhpEol with an unknown version: 7.4 -> 2022
     */
    public function testGetUnknownPhpEol(): void
    {
        static::assertSame(
            (((int) (new \DateTime())->format('Y')) + 2) . (new \DateTime())->format('-m-d'),
            ApplicationUtils::getPhpEol('7.51.34')
        );
    }
}
