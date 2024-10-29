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
use Slim\Psr7\Factory\ServerserverRequestFactory;

/**
 * Class GetTagNameTest
 *
 * Test getTag by tag name API service.
 *
 * @package Shaarli\Api\Controllers
 */
class GetTagNameTest extends \Shaarli\TestCase
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
     * @var Tags controller instance.
     */
    protected $controller;

    /** @var PluginManager */
    protected $pluginManager;

    /**
     * Number of JSON fields per link.
     */
    protected const NB_FIELDS_TAG = 2;

    /**
     * Before each test, instantiate a new Api with its config, plugins and bookmarks.
     */
    protected function setUp(): void
    {
        $this->initRequestResponseFactories();
        $mutex = new NoMutex();
        $this->conf = new ConfigManager('tests/utils/config/configJson');
        $this->conf->set('resource.datastore', self::$testDatastore);
        $this->refDB = new ReferenceLinkDB();
        $this->refDB->write(self::$testDatastore);
        $history = new History('sandbox/history.php');

        $this->container = new DIContainer();
        $this->container->set('conf', $this->conf);
        $this->pluginManager = new PluginManager($this->conf);
        $this->container->set('db', new BookmarkFileService(
            $this->conf,
            $this->pluginManager,
            $history,
            $mutex,
            true
        ));
        $this->container->set('history', null);

        $this->controller = new Tags($this->container);
    }

    /**
     * After each test, remove the test datastore.
     */
    protected function tearDown(): void
    {
        @unlink(self::$testDatastore);
    }

    /**
     * Test basic getTag service: return gnu tag with 2 occurrences.
     */
    public function testGetTag()
    {
        $tagName = 'gnu';
        $serverParams = ['SERVER_NAME' => 'domain.tld', 'SERVER_PORT' => 80];
        $request = $this->serverRequestFactory->createServerRequest('GET', 'http://shaarli', $serverParams);

        $response = $this->controller->getTag(
            $request,
            $this->responseFactory->createResponse(),
            ['tagName' => $tagName]
        );
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals(self::NB_FIELDS_TAG, count($data));
        $this->assertEquals($tagName, $data['name']);
        $this->assertEquals(2, $data['occurrences']);
    }

    /**
     * Test getTag service which is not case sensitive: occurrences with both sTuff and stuff
     */
    public function testGetTagNotCaseSensitive()
    {
        $tagName = 'sTuff';
        $serverParams = ['SERVER_NAME' => 'domain.tld', 'SERVER_PORT' => 80];
        $request = $this->serverRequestFactory->createServerRequest('GET', 'http://shaarli', $serverParams);

        $response = $this->controller->getTag(
            $request,
            $this->responseFactory->createResponse(),
            ['tagName' => $tagName]
        );
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals(self::NB_FIELDS_TAG, count($data));
        $this->assertEquals($tagName, $data['name']);
        $this->assertEquals(2, $data['occurrences']);
    }

    /**
     * Test basic getTag service: get non existent tag => ApiTagNotFoundException.
     */
    public function testGetTag404()
    {
        $this->expectException(\Shaarli\Api\Exceptions\ApiTagNotFoundException::class);
        $this->expectExceptionMessage('Tag not found');

        $serverParams = ['SERVER_NAME' => 'domain.tld', 'SERVER_PORT' => 80];
        $request = $this->serverRequestFactory->createServerRequest('GET', 'http://shaarli', $serverParams);

        $this->controller->getTag($request, $this->responseFactory->createResponse(), ['tagName' => 'nopenope']);
    }
}
