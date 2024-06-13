<?php

namespace Shaarli\Updater;

use Exception;
use malkusch\lock\mutex\NoMutex;
use Shaarli\Bookmark\BookmarkFileService;
use Shaarli\Bookmark\BookmarkServiceInterface;
use Shaarli\Config\ConfigManager;
use Shaarli\History;
use Shaarli\Plugin\PluginManager;
use Shaarli\TestCase;
use Shaarli\Tests\updater\DummyUpdater;
use Shaarli\Tests\Utils\ReferenceLinkDB;

/**
 * Class UpdaterTest.
 * Runs unit tests against the updater class.
 */
class UpdaterTest extends TestCase
{
    /**
     * @var string Path to test datastore.
     */
    protected static $testDatastore = 'sandbox/datastore.php';

    /**
     * @var string Config file path (without extension).
     */
    protected static $configFile = 'sandbox/config';

    /**
     * @var ConfigManager
     */
    protected $conf;

    /** @var BookmarkServiceInterface */
    protected $bookmarkService;

    /** @var ReferenceLinkDB */
    protected $refDB;

    /** @var Updater */
    protected $updater;

    /**
     * Executed before each test.
     */
    protected function setUp(): void
    {
        $mutex = new NoMutex();
        $this->refDB = new ReferenceLinkDB();
        $this->refDB->write(self::$testDatastore);

        copy(__DIR__ . '/../../tests/utils/config/configJson.json.php', self::$configFile . '.json.php');
        $this->conf = new ConfigManager(self::$configFile);
        $this->bookmarkService = new BookmarkFileService(
            $this->conf,
            $this->createMock(PluginManager::class),
            $this->createMock(History::class),
            $mutex,
            true
        );
        $this->updater = new Updater([], $this->bookmarkService, $this->conf, true);
    }

    /**
     * Test UpdaterUtils::read_updates_file with an empty/missing file.
     */
    public function testReadEmptyUpdatesFile()
    {
        $this->assertEquals([], UpdaterUtils::readUpdatesFile(''));
        $updatesFile = $this->conf->get('resource.data_dir') . '/updates.txt';
        touch($updatesFile);
        $this->assertEquals([], UpdaterUtils::readUpdatesFile($updatesFile));
        unlink($updatesFile);
    }

    /**
     * Test read/write updates file.
     */
    public function testReadWriteUpdatesFile()
    {
        $updatesFile = $this->conf->get('resource.data_dir') . '/updates.txt';
        $updatesMethods = ['m1', 'm2', 'm3'];

        UpdaterUtils::writeUpdatesFile($updatesFile, $updatesMethods);
        $readMethods = UpdaterUtils::readUpdatesFile($updatesFile);
        $this->assertEquals($readMethods, $updatesMethods);

        // Update
        $updatesMethods[] = 'm4';
        UpdaterUtils::writeUpdatesFile($updatesFile, $updatesMethods);
        $readMethods = UpdaterUtils::readUpdatesFile($updatesFile);
        $this->assertEquals($readMethods, $updatesMethods);
        unlink($updatesFile);
    }

    /**
     * Test errors in UpdaterUtils::write_updates_file(): empty updates file.
     */
    public function testWriteEmptyUpdatesFile()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageRegExp('/Updates file path is not set(.*)/');

        UpdaterUtils::writeUpdatesFile('', ['test']);
    }

    /**
     * Test errors in UpdaterUtils::write_updates_file(): not writable updates file.
     */
    public function testWriteUpdatesFileNotWritable()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageRegExp('/Unable to write(.*)/');

        $updatesFile = $this->conf->get('resource.data_dir') . '/updates.txt';
        touch($updatesFile);
        chmod($updatesFile, 0444);
        try {
            @UpdaterUtils::writeUpdatesFile($updatesFile, ['test']);
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
        $updates = [
            'updateMethodDummy1',
            'updateMethodDummy2',
            'updateMethodDummy3',
            'updateMethodException',
        ];
        $updater = new DummyUpdater($updates, [], $this->conf, true);
        $this->assertEquals([], $updater->update());

        $updater = new DummyUpdater([], [], $this->conf, false);
        $this->assertEquals([], $updater->update());
    }

    /**
     * Test the update() method, with all updates to run (except the failing one).
     */
    public function testUpdatesFirstTime()
    {
        $updates = ['updateMethodException',];
        $expectedUpdates = [
            'updateMethodDummy1',
            'updateMethodDummy2',
            'updateMethodDummy3',
        ];
        $updater = new DummyUpdater($updates, [], $this->conf, true);
        $this->assertEquals($expectedUpdates, $updater->update());
    }

    /**
     * Test the update() method, only one update to run.
     */
    public function testOneUpdate()
    {
        $updates = [
            'updateMethodDummy1',
            'updateMethodDummy3',
            'updateMethodException',
        ];
        $expectedUpdate = ['updateMethodDummy2'];

        $updater = new DummyUpdater($updates, [], $this->conf, true);
        $this->assertEquals($expectedUpdate, $updater->update());
    }

    /**
     * Test Update failed.
     */
    public function testUpdateFailed()
    {
        $this->expectException(\Exception::class);

        $updates = [
            'updateMethodDummy1',
            'updateMethodDummy2',
            'updateMethodDummy3',
        ];

        $updater = new DummyUpdater($updates, [], $this->conf, true);
        $updater->update();
    }

    public function testUpdateMethodRelativeHomeLinkRename(): void
    {
        $this->updater->setBasePath('/subfolder');
        $this->conf->set('general.header_link', '?');

        $this->updater->updateMethodRelativeHomeLink();

        static::assertSame('/subfolder/', $this->conf->get('general.header_link'));
    }

    public function testUpdateMethodRelativeHomeLinkDoNotRename(): void
    {
        $this->conf->set('general.header_link', '~/my-blog');

        $this->updater->updateMethodRelativeHomeLink();

        static::assertSame('~/my-blog', $this->conf->get('general.header_link'));
    }

    public function testUpdateMethodMigrateExistingNotesUrl(): void
    {
        $this->updater->updateMethodMigrateExistingNotesUrl();

        static::assertSame($this->refDB->getLinks()[0]->getUrl(), $this->bookmarkService->get(0)->getUrl());
        static::assertSame($this->refDB->getLinks()[1]->getUrl(), $this->bookmarkService->get(1)->getUrl());
        static::assertSame($this->refDB->getLinks()[4]->getUrl(), $this->bookmarkService->get(4)->getUrl());
        static::assertSame($this->refDB->getLinks()[6]->getUrl(), $this->bookmarkService->get(6)->getUrl());
        static::assertSame($this->refDB->getLinks()[7]->getUrl(), $this->bookmarkService->get(7)->getUrl());
        static::assertSame($this->refDB->getLinks()[8]->getUrl(), $this->bookmarkService->get(8)->getUrl());
        static::assertSame($this->refDB->getLinks()[9]->getUrl(), $this->bookmarkService->get(9)->getUrl());
        static::assertSame('/shaare/WDWyig', $this->bookmarkService->get(42)->getUrl());
        static::assertSame('/shaare/WDWyig', $this->bookmarkService->get(41)->getUrl());
        static::assertSame('/shaare/0gCTjQ', $this->bookmarkService->get(10)->getUrl());
        static::assertSame('/shaare/PCRizQ', $this->bookmarkService->get(11)->getUrl());
    }
}
