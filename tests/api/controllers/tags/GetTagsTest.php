<?php

namespace Shaarli\Api\Controllers;

use DI\Container as DIContainer;
use malkusch\lock\mutex\NoMutex;
use Psr\Container\ContainerInterface as Container;
use Shaarli\Bookmark\BookmarkFileService;
use Shaarli\Bookmark\LinkDB;
use Shaarli\Config\ConfigManager;
use Shaarli\History;
use Shaarli\Plugin\PluginManager;
use Shaarli\Tests\Utils\FakeRequest;
use Shaarli\Tests\Utils\ReferenceLinkDB;
use Slim\Http\Environment;
use Slim\Psr7\Response as SlimResponse;
use Slim\Psr7\Uri;

/**
 * Class GetTagsTest
 *
 * Test get tag list REST API service.
 *
 * @package Shaarli\Api\Controllers
 */
class GetTagsTest extends \Shaarli\TestCase
{
    /**
     * @var string datastore to test write operations
     */
    protected static $testDatastore = 'sandbox/datastore.php';

    /**
     * @var ConfigManager instance
     */
    protected $conf;

    /**
     * @var ReferenceLinkDB instance.
     */
    protected $refDB = null;

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
        $history = new History('sandbox/history.php');
        $this->pluginManager = new PluginManager($this->conf);
        $this->bookmarkService = new BookmarkFileService(
            $this->conf,
            $this->pluginManager,
            $history,
            $mutex,
            true
        );
        $this->container = new DIContainer();
        $this->container->set('conf', $this->conf);
        $this->container->set('db', $this->bookmarkService);
        $this->container->set('history', null);

        $this->controller = new Tags($this->container);
    }

    /**
     * After every test, remove the test datastore.
     */
    protected function tearDown(): void
    {
        @unlink(self::$testDatastore);
    }

    /**
     * Test basic getTags service: returns all tags.
     */
    public function testGetTagsAll()
    {
        $tags = $this->bookmarkService->bookmarksCountPerTag();
        $request = (new FakeRequest(
            'GET'
        ));

        $response = $this->controller->getTags($request, new SlimResponse());
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals(count($tags), count($data));

        // Check order
        $this->assertEquals(self::NB_FIELDS_TAG, count($data[0]));
        $this->assertEquals('web', $data[0]['name']);
        $this->assertEquals(4, $data[0]['occurrences']);
        $this->assertEquals(self::NB_FIELDS_TAG, count($data[1]));
        $this->assertEquals('cartoon', $data[1]['name']);
        $this->assertEquals(3, $data[1]['occurrences']);
        // Case insensitive
        $this->assertEquals(self::NB_FIELDS_TAG, count($data[5]));
        $this->assertEquals('sTuff', $data[5]['name']);
        $this->assertEquals(2, $data[5]['occurrences']);
        // End
        $this->assertEquals(self::NB_FIELDS_TAG, count($data[count($data) - 1]));
        $this->assertEquals('w3c', $data[count($data) - 1]['name']);
        $this->assertEquals(1, $data[count($data) - 1]['occurrences']);
    }

    /**
     * Test getTags service with offset and limit parameter:
     *   limit=1 and offset=1 should return only the second tag, cartoon with 3 occurrences
     */
    public function testGetTagsOffsetLimit()
    {
        $request = (new FakeRequest(
            'GET',
            new Uri('', '', 80, '', 'offset=1&limit=1')
        ));
        $response = $this->controller->getTags($request, new SlimResponse());
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals(1, count($data));
        $this->assertEquals(self::NB_FIELDS_TAG, count($data[0]));
        $this->assertEquals('cartoon', $data[0]['name']);
        $this->assertEquals(3, $data[0]['occurrences']);
    }

    /**
     * Test getTags with limit=all (return all tags).
     */
    public function testGetTagsLimitAll()
    {
        $tags = $this->bookmarkService->bookmarksCountPerTag();
        $request = (new FakeRequest(
            'GET',
            new Uri('', '', 80, '', 'limit=all')
        ));
        $response = $this->controller->getTags($request, new SlimResponse());
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals(count($tags), count($data));
    }

    /**
     * Test getTags service with offset and limit parameter:
     *   limit=1 and offset=1 should not return any tag
     */
    public function testGetTagsOffsetTooHigh()
    {
        $request = (new FakeRequest(
            'GET',
            new Uri('', '', 80, '', 'offset=100')
        ));
        $response = $this->controller->getTags($request, new SlimResponse());
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEmpty(count($data));
    }

    /**
     * Test getTags with visibility parameter set to private
     */
    public function testGetTagsVisibilityPrivate()
    {
        $tags = $this->bookmarkService->bookmarksCountPerTag([], 'private');
        $request = (new FakeRequest(
            'GET',
            new Uri('', '', 80, '', 'visibility=private')
        ));
        $response = $this->controller->getTags($request, new SlimResponse());
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals(count($tags), count($data));
        $this->assertEquals(self::NB_FIELDS_TAG, count($data[0]));
        $this->assertEquals('Mercurial', $data[0]['name']);
        $this->assertEquals(1, $data[0]['occurrences']);
    }

    /**
     * Test getTags with visibility parameter set to public
     */
    public function testGetTagsVisibilityPublic()
    {
        $tags = $this->bookmarkService->bookmarksCountPerTag([], 'public');

        $request = (new FakeRequest(
            'GET',
            new Uri('', '', 80, '', 'visibility=public')
        ));
        $response = $this->controller->getTags($request, new SlimResponse());
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string)$response->getBody(), true);
        $this->assertEquals(count($tags), count($data));
        $this->assertEquals(self::NB_FIELDS_TAG, count($data[0]));
        $this->assertEquals('web', $data[0]['name']);
        $this->assertEquals(3, $data[0]['occurrences']);
    }
}
