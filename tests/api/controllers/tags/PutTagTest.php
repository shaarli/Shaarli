<?php

namespace Shaarli\Api\Controllers;

use DI\Container as DIContainer;
use malkusch\lock\mutex\NoMutex;
use Psr\Container\ContainerInterface as Container;
use Shaarli\Api\Exceptions\ApiBadParametersException;
use Shaarli\Bookmark\BookmarkFileService;
use Shaarli\Bookmark\LinkDB;
use Shaarli\Config\ConfigManager;
use Shaarli\History;
use Shaarli\Plugin\PluginManager;
use Shaarli\Tests\Utils\FakeRequest;
use Shaarli\Tests\Utils\ReferenceHistory;
use Shaarli\Tests\Utils\ReferenceLinkDB;
use Slim\Http\Environment;
use Slim\Psr7\Response as SlimResponse;

class PutTagTest extends \Shaarli\TestCase
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
     * @var History instance.
     */
    protected $history;

    /**
     * @var Container instance.
     */
    protected $container;

    /**
     * @var BookmarkFileService instance.
     */
    protected $bookmarkService;

    /**
     * @var Tags controller instance.
     */
    protected $controller;

    /** @var PluginManager */
    protected $pluginManager;

    /**
     * Number of JSON field per link.
     */
    protected const NB_FIELDS_TAG = 2;

    /**
     * Before every test, instantiate a new Api with its config, plugins and bookmarks.
     */
    protected function setUp(): void
    {
        $mutex = new NoMutex();
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
            $mutex,
            true
        );

        $this->container = new DIContainer();
        $this->container->set('conf', $this->conf);
        $this->container->set('db', $this->bookmarkService);
        $this->container->set('history', $this->history);

        $this->controller = new Tags($this->container);
    }

    /**
     * After every test, remove the test datastore.
     */
    protected function tearDown(): void
    {
        @unlink(self::$testDatastore);
        @unlink(self::$testHistory);
    }

    /**
     * Test tags update
     */
    public function testPutLinkValid()
    {
        $tagName = 'gnu';
        $update = ['name' => $newName = 'newtag'];
        $request = new FakeRequest(
            'PUT'
        );
        $request = $request->withParsedBody($update);

        $response = $this->controller->putTag($request, new SlimResponse(), ['tagName' => $tagName]);
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals(self::NB_FIELDS_TAG, count($data));
        $this->assertEquals($newName, $data['name']);
        $this->assertEquals(2, $data['occurrences']);

        $tags = $this->bookmarkService->bookmarksCountPerTag();
        $this->assertNotTrue(isset($tags[$tagName]));
        $this->assertEquals(2, $tags[$newName]);

        $historyEntry = $this->history->getHistory()[0];
        $this->assertEquals(History::UPDATED, $historyEntry['event']);
        $this->assertTrue(
            (new \DateTime())->add(\DateInterval::createFromDateString('-5 seconds')) < $historyEntry['datetime']
        );
        $historyEntry = $this->history->getHistory()[1];
        $this->assertEquals(History::UPDATED, $historyEntry['event']);
        $this->assertTrue(
            (new \DateTime())->add(\DateInterval::createFromDateString('-5 seconds')) < $historyEntry['datetime']
        );
    }

    /**
     * Test tag update with an existing tag: they should be merged
     */
    public function testPutTagMerge()
    {
        $tagName = 'gnu';
        $newName = 'w3c';

        $tags = $this->bookmarkService->bookmarksCountPerTag();
        $this->assertEquals(1, $tags[$newName]);
        $this->assertEquals(2, $tags[$tagName]);

        $update = ['name' => $newName];
        $request = new FakeRequest(
            'PUT'
        );
        $request = $request->withParsedBody($update);

        $response = $this->controller->putTag($request, new SlimResponse(), ['tagName' => $tagName]);
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals(self::NB_FIELDS_TAG, count($data));
        $this->assertEquals($newName, $data['name']);
        $this->assertEquals(3, $data['occurrences']);

        $tags = $this->bookmarkService->bookmarksCountPerTag();
        $this->assertNotTrue(isset($tags[$tagName]));
        $this->assertEquals(3, $tags[$newName]);
    }

    /**
     * Test tag update with an empty new tag name => ApiBadParametersException
     */
    public function testPutTagEmpty()
    {
        $this->expectException(\Shaarli\Api\Exceptions\ApiBadParametersException::class);
        $this->expectExceptionMessage('New tag name is required in the request body');

        $tagName = 'gnu';
        $newName = '';

        $tags = $this->bookmarkService->bookmarksCountPerTag();
        $this->assertEquals(2, $tags[$tagName]);

        $request = new FakeRequest(
            'PUT'
        );

        $update = ['name' => $newName];
        $request = $request->withParsedBody($update);

        try {
            $this->controller->putTag($request, new SlimResponse(), ['tagName' => $tagName]);
        } catch (ApiBadParametersException $e) {
            $tags = $this->bookmarkService->bookmarksCountPerTag();
            $this->assertEquals(2, $tags[$tagName]);
            throw $e;
        }
    }

    /**
     * Test tag update on non existent tag => ApiTagNotFoundException.
     */
    public function testPutTag404()
    {
        $this->expectException(\Shaarli\Api\Exceptions\ApiTagNotFoundException::class);
        $this->expectExceptionMessage('Tag not found');

        $request = new FakeRequest(
            'PUT'
        );

        $this->controller->putTag($request, new SlimResponse(), ['tagName' => 'nopenope']);
    }
}
