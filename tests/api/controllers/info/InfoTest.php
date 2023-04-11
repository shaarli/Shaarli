<?php

namespace Shaarli\Api\Controllers;

use DI\Container as DIContainer;
use malkusch\lock\mutex\NoMutex;
use Psr\Container\ContainerInterface as Container;
use Shaarli\Bookmark\BookmarkFileService;
use Shaarli\Config\ConfigManager;
use Shaarli\History;
use Shaarli\Plugin\PluginManager;
use Shaarli\TestCase;
use Shaarli\Tests\Utils\FakeRequest;
use Shaarli\Tests\Utils\ReferenceLinkDB;
use Slim\Http\Environment;
use Slim\Psr7\Response as SlimResponse;

/**
 * Class InfoTest
 *
 * Test REST API controller Info.
 *
 * @package Api\Controllers
 */
class InfoTest extends TestCase
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
     * @var Info controller instance.
     */
    protected $controller;

        /**
     * @var PluginManager plugin Manager
     */
    protected $pluginManager;

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
        $this->pluginManager = new PluginManager($this->conf);
        $history = new History('sandbox/history.php');

        $this->container = new DIContainer();
        $this->container->set('conf', $this->conf);
        $this->container->set('db', new BookmarkFileService(
            $this->conf,
            $this->pluginManager,
            $history,
            $mutex,
            true
        ));
        $this->container->set('history', null);

        $this->controller = new Info($this->container);
    }

    /**
     * After every test, remove the test datastore.
     */
    protected function tearDown(): void
    {
        @unlink(self::$testDatastore);
    }

    /**
     * Test /info service.
     */
    public function testGetInfo()
    {
        $request = new FakeRequest(
            'GET'
        );

        $response = $this->controller->getInfo($request, new SlimResponse());
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);

        $this->assertEquals(ReferenceLinkDB::$NB_LINKS_TOTAL, $data['global_counter']);
        $this->assertEquals(2, $data['private_counter']);
        $this->assertEquals('Shaarli', $data['settings']['title']);
        $this->assertEquals('?', $data['settings']['header_link']);
        $this->assertEquals('Europe/Paris', $data['settings']['timezone']);
        $this->assertEquals(ConfigManager::$DEFAULT_PLUGINS, $data['settings']['enabled_plugins']);
        $this->assertEquals(true, $data['settings']['default_private_links']);

        $title = 'My bookmarks';
        $headerLink = 'http://shaarli.tld';
        $timezone = 'Europe/Paris';
        $enabledPlugins = ['foo', 'bar'];
        $defaultPrivateLinks = true;
        $this->conf->set('general.title', $title);
        $this->conf->set('general.header_link', $headerLink);
        $this->conf->set('general.timezone', $timezone);
        $this->conf->set('general.enabled_plugins', $enabledPlugins);
        $this->conf->set('privacy.default_private_links', $defaultPrivateLinks);

        $response = $this->controller->getInfo($request, new SlimResponse());
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);

        $this->assertEquals(ReferenceLinkDB::$NB_LINKS_TOTAL, $data['global_counter']);
        $this->assertEquals(2, $data['private_counter']);
        $this->assertEquals($title, $data['settings']['title']);
        $this->assertEquals($headerLink, $data['settings']['header_link']);
        $this->assertEquals($timezone, $data['settings']['timezone']);
        $this->assertEquals($enabledPlugins, $data['settings']['enabled_plugins']);
        $this->assertEquals($defaultPrivateLinks, $data['settings']['default_private_links']);
    }
}
