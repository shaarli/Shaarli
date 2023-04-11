<?php

namespace Shaarli\Api\Controllers;

use DI\Container as DIContainer;
use malkusch\lock\mutex\NoMutex;
use Psr\Container\ContainerInterface as Container;
use Shaarli\Bookmark\BookmarkFileService;
use Shaarli\Config\ConfigManager;
use Shaarli\History;
use Shaarli\Plugin\PluginManager;
use Shaarli\Tests\Utils\FakeRequest;
use Shaarli\Tests\Utils\ReferenceHistory;
use Shaarli\Tests\Utils\ReferenceLinkDB;
use Slim\Http\Environment;
use Slim\Psr7\Response as SlimResponse;

class DeleteLinkTest extends \Shaarli\TestCase
{
    /**
     * @var string datastore to test write operations
     */
    protected static $testDatastore = 'sandbox/datastore.php';

    /**
     * @var string datastore to test write operations
     */
    protected static $testHistory = 'sandbox/history.php';

    /**
     * @var ConfigManager instance
     */
    protected $conf;

    /**
     * @var ReferenceLinkDB instance.
     */
    protected $refDB = null;

    /**
     * @var BookmarkFileService instance.
     */
    protected $bookmarkService;

    /**
     * @var History instance.
     */
    protected $history;

    /**
     * @var Container instance.
     */
    protected $container;

    /**
     * @var Links controller instance.
     */
    protected $controller;

    /** @var NoMutex */
    protected $mutex;

    /** @var PluginManager */
    protected $pluginManager;

    /**
     * Before each test, instantiate a new Api with its config, plugins and bookmarks.
     */
    protected function setUp(): void
    {
        $this->mutex = new NoMutex();
        $this->conf = new ConfigManager('tests/utils/config/configJson');
        $this->conf->set('resource.datastore', self::$testDatastore);
        $this->refDB = new ReferenceLinkDB();
        $this->refDB->write(self::$testDatastore);
        $refHistory = new ReferenceHistory();
        $refHistory->write(self::$testHistory);
        $this->history = new History(self::$testHistory);
        $this->pluginManager = new PluginManager($this->conf);
        $this->bookmarkService = new BookmarkFileService(
            $this->conf,
            $this->pluginManager,
            $this->history,
            $this->mutex,
            true
        );

        $this->container = new DIContainer();
        $this->container->set('conf', $this->conf);
        $this->container->set('db', $this->bookmarkService);
        $this->container->set('history', $this->history);

        $this->controller = new Links($this->container);
    }

    /**
     * After each test, remove the test datastore.
     */
    protected function tearDown(): void
    {
        @unlink(self::$testDatastore);
        @unlink(self::$testHistory);
    }

    /**
     * Test DELETE link endpoint: the link should be removed.
     */
    public function testDeleteLinkValid()
    {
        $id = '41';
        $this->assertTrue($this->bookmarkService->exists($id));
        $request = new FakeRequest(
            'DELETE'
        );

        $response = $this->controller->deleteLink($request, new SlimResponse(), ['id' => $id]);
        $this->assertEquals(204, $response->getStatusCode());
        $this->assertEmpty((string) $response->getBody());

        $this->bookmarkService = new BookmarkFileService(
            $this->conf,
            $this->pluginManager,
            $this->history,
            $this->mutex,
            true
        );
        $this->assertFalse($this->bookmarkService->exists($id));

        $historyEntry = $this->history->getHistory()[0];
        $this->assertEquals(History::DELETED, $historyEntry['event']);
        $this->assertTrue(
            (new \DateTime())->add(\DateInterval::createFromDateString('-5 seconds')) < $historyEntry['datetime']
        );
        $this->assertEquals($id, $historyEntry['id']);
    }

    /**
     * Test DELETE link endpoint: reach not existing ID.
     */
    public function testDeleteLink404()
    {
        $this->expectException(\Shaarli\Api\Exceptions\ApiLinkNotFoundException::class);

        $id = -1;
        $this->assertFalse($this->bookmarkService->exists($id));
        $request = new FakeRequest(
            'DELETE'
        );

        $this->controller->deleteLink($request, new SlimResponse(), ['id' => $id]);
    }
}
