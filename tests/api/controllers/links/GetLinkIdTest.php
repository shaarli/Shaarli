<?php

namespace Shaarli\Api\Controllers;

use DI\Container as DIContainer;
use malkusch\lock\mutex\NoMutex;
use Psr\Container\ContainerInterface as Container;
use Shaarli\Bookmark\Bookmark;
use Shaarli\Bookmark\BookmarkFileService;
use Shaarli\Config\ConfigManager;
use Shaarli\History;
use Shaarli\Plugin\PluginManager;
use Shaarli\Tests\Utils\FakeRequest;
use Shaarli\Tests\Utils\ReferenceLinkDB;
use Slim\Http\Environment;
use Slim\Psr7\Response as SlimResponse;

/**
 * Class GetLinkIdTest
 *
 * Test getLink by ID API service.
 *
 * @see http://shaarli.github.io/api-documentation/#links-link-get
 *
 * @package Shaarli\Api\Controllers
 */
class GetLinkIdTest extends \Shaarli\TestCase
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
     * @var Links controller instance.
     */
    protected $controller;

    /**
     * Number of JSON fields per link.
     */
    protected const NB_FIELDS_LINK = 9;

    /**
     * Before each test, instantiate a new Api with its config, plugins and bookmarks.
     */
    protected function setUp(): void
    {
        $mutex = new NoMutex();
        $this->conf = new ConfigManager('tests/utils/config/configJson');
        $this->conf->set('resource.datastore', self::$testDatastore);
        $this->refDB = new ReferenceLinkDB();
        $this->refDB->write(self::$testDatastore);
        $history = new History('sandbox/history.php');

        $this->container = new DIContainer();
        $this->container->set('conf', $this->conf);
        $pluginManager = new PluginManager($this->conf);
        $this->container->set('db', new BookmarkFileService(
            $this->conf,
            $pluginManager,
            $history,
            $mutex,
            true
        ));
        $this->container->set('history', null);

        $this->controller = new Links($this->container);
    }

    /**
     * After each test, remove the test datastore.
     */
    protected function tearDown(): void
    {
        @unlink(self::$testDatastore);
    }

    /**
     * Test basic getLink service: return link ID=41.
     */
    public function testGetLinkId()
    {
        // Used by index_url().
        $_SERVER['SERVER_NAME'] = 'domain.tld';
        $_SERVER['SERVER_PORT'] = 80;
        $_SERVER['SCRIPT_NAME'] = '/';

        $id = 41;
        $request = (new FakeRequest(
            'GET'
        ))->withServerParams(['SERVER_NAME' => 'domain.tld', 'SERVER_PORT' => '80']);

        $response = $this->controller->getLink($request, new SlimResponse(), ['id' => $id]);
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals(self::NB_FIELDS_LINK, count($data));
        $this->assertEquals($id, $data['id']);

        // Check link elements
        $this->assertEquals('http://domain.tld/shaare/WDWyig', $data['url']);
        $this->assertEquals('WDWyig', $data['shorturl']);
        $this->assertEquals('Link title: @website', $data['title']);
        $this->assertEquals(
            'Stallman has a beard and is part of the Free Software Foundation (or not). Seriously, read this. #hashtag',
            $data['description']
        );
        $this->assertEquals('sTuff', $data['tags'][0]);
        $this->assertEquals(false, $data['private']);
        $this->assertEquals(
            \DateTime::createFromFormat(Bookmark::LINK_DATE_FORMAT, '20150310_114651')->format(\DateTime::ATOM),
            $data['created']
        );
        $this->assertEmpty($data['updated']);
    }

    /**
     * Test basic getLink service: get non existent link => ApiLinkNotFoundException.
     */
    public function testGetLink404()
    {
        $this->expectException(\Shaarli\Api\Exceptions\ApiLinkNotFoundException::class);
        $this->expectExceptionMessage('Link not found');

        $request = new FakeRequest();

        $this->controller->getLink($request, new SlimResponse(), ['id' => -1]);
    }
}
