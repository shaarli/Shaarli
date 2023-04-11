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
use Shaarli\Tests\Utils\FakeRouteCollector;
use Shaarli\Tests\Utils\ReferenceHistory;
use Shaarli\Tests\Utils\ReferenceLinkDB;
use Slim\Http\Environment;
use Slim\Psr7\Response as SlimResponse;
use Slim\Psr7\Uri;
use Slim\Routing\RouteContext;

class PutLinkTest extends \Shaarli\TestCase
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
     * @var RouteParser instance.
     */
    protected $routeParser;

    /**
     * @var Container instance.
     */
    protected $container;

    /**
     * @var Links controller instance.
     */
    protected $controller;

    /**
     * Number of JSON field per link.
     */
    protected const NB_FIELDS_LINK = 9;

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
        $pluginManager = new PluginManager($this->conf);
        $this->bookmarkService = new BookmarkFileService(
            $this->conf,
            $pluginManager,
            $this->history,
            $mutex,
            true
        );
        $this->container = new DIContainer();
        $this->container->set('conf', $this->conf);
        $this->container->set('db', $this->bookmarkService);
        $this->container->set('history', $this->history);

        $this->controller = new Links($this->container);

        $this->routeParser = (new FakeRouteCollector())
            ->addRoute('POST', '/api/v1/bookmarks/{id:[\d]+}', 'getLink')
            ->getRouteParser();
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
     * Test link update without value: reset the link to default values
     */
    public function testPutLinkMinimal()
    {
        $id = '41';
        $request = (new FakeRequest(
            'PUT',
            new Uri('', '')
        ))->withServerParams(['SERVER_NAME' => 'domain.tld', 'SERVER_PORT' => 80])
            ->withAttribute(RouteContext::ROUTE_PARSER, $this->routeParser);

        $response = $this->controller->putLink($request, new SlimResponse(), ['id' => $id]);
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals(self::NB_FIELDS_LINK, count($data));
        $this->assertEquals($id, $data['id']);
        $this->assertEquals('WDWyig', $data['shorturl']);
        $this->assertEquals('http://domain.tld/shaare/WDWyig', $data['url']);
        $this->assertEquals('/shaare/WDWyig', $data['title']);
        $this->assertEquals('', $data['description']);
        $this->assertEquals([], $data['tags']);
        $this->assertEquals(true, $data['private']);
        $this->assertEquals(
            \DateTime::createFromFormat('Ymd_His', '20150310_114651'),
            \DateTime::createFromFormat(\DateTime::ATOM, $data['created'])
        );
        $this->assertTrue(
            new \DateTime('5 seconds ago') < \DateTime::createFromFormat(\DateTime::ATOM, $data['updated'])
        );

        $historyEntry = $this->history->getHistory()[0];
        $this->assertEquals(History::UPDATED, $historyEntry['event']);
        $this->assertTrue(
            (new \DateTime())->add(\DateInterval::createFromDateString('-5 seconds')) < $historyEntry['datetime']
        );
        $this->assertEquals($id, $historyEntry['id']);
    }

    /**
     * Test link update with new values
     */
    public function testPutLinkWithValues()
    {
        $id = 41;
        $update = [
            'url' => 'http://somewhere.else',
            'title' => 'Le Cid',
            'description' => 'Percé jusques au fond du cœur [...]',
            'tags' => ['corneille', 'rodrigue'],
            'private' => true,
        ];
        $request = (new FakeRequest(
            'PUT',
            new Uri('', '')
        ))->withServerParams(['SERVER_NAME' => 'domain.tld', 'SERVER_PORT' => 80])
            ->withAttribute(RouteContext::ROUTE_PARSER, $this->routeParser);
        $request = $request->withParsedBody($update);

        $response = $this->controller->putLink($request, new SlimResponse(), ['id' => $id]);
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals(self::NB_FIELDS_LINK, count($data));
        $this->assertEquals($id, $data['id']);
        $this->assertEquals('WDWyig', $data['shorturl']);
        $this->assertEquals('http://somewhere.else', $data['url']);
        $this->assertEquals('Le Cid', $data['title']);
        $this->assertEquals('Percé jusques au fond du cœur [...]', $data['description']);
        $this->assertEquals(['corneille', 'rodrigue'], $data['tags']);
        $this->assertEquals(true, $data['private']);
        $this->assertEquals(
            \DateTime::createFromFormat('Ymd_His', '20150310_114651'),
            \DateTime::createFromFormat(\DateTime::ATOM, $data['created'])
        );
        $this->assertTrue(
            new \DateTime('5 seconds ago') < \DateTime::createFromFormat(\DateTime::ATOM, $data['updated'])
        );
    }

    /**
     * Test link update with an existing URL: 409 Conflict with the existing link as body
     */
    public function testPutLinkDuplicate()
    {
        $link = [
            'url' => 'mediagoblin.org/',
            'title' => 'new entry',
            'description' => 'shaare description',
            'tags' => ['one', 'two'],
            'private' => true,
        ];

        $request = (new FakeRequest(
            'PUT',
            new Uri('', '')
        ))->withServerParams(['SERVER_NAME' => 'domain.tld', 'SERVER_PORT' => 80])
            ->withAttribute(RouteContext::ROUTE_PARSER, $this->routeParser);
        $request = $request->withParsedBody($link);
        $response = $this->controller->putLink($request, new SlimResponse(), ['id' => 41]);

        $this->assertEquals(409, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals(self::NB_FIELDS_LINK, count($data));
        $this->assertEquals(7, $data['id']);
        $this->assertEquals('IuWvgA', $data['shorturl']);
        $this->assertEquals('http://mediagoblin.org/', $data['url']);
        $this->assertEquals('MediaGoblin', $data['title']);
        $this->assertEquals('A free software media publishing platform #hashtagOther', $data['description']);
        $this->assertEquals(['gnu', 'media', 'web', '.hidden', 'hashtag'], $data['tags']);
        $this->assertEquals(false, $data['private']);
        $this->assertEquals(
            \DateTime::createFromFormat(Bookmark::LINK_DATE_FORMAT, '20130614_184135'),
            \DateTime::createFromFormat(\DateTime::ATOM, $data['created'])
        );
        $this->assertEquals(
            \DateTime::createFromFormat(Bookmark::LINK_DATE_FORMAT, '20130615_184230'),
            \DateTime::createFromFormat(\DateTime::ATOM, $data['updated'])
        );
    }

    /**
     * Test link update on non existent link => ApiLinkNotFoundException.
     */
    public function testGetLink404()
    {
        $this->expectException(\Shaarli\Api\Exceptions\ApiLinkNotFoundException::class);
        $this->expectExceptionMessage('Link not found');

        $request = (new FakeRequest(
            'PUT',
            new Uri('', '')
        ))->withServerParams(['SERVER_NAME' => 'domain.tld', 'SERVER_PORT' => 80])
            ->withAttribute(RouteContext::ROUTE_PARSER, $this->routeParser);

        $this->controller->putLink($request, new SlimResponse(), ['id' => -1]);
    }

    /**
     * Test link creation with a tag string provided
     */
    public function testPutLinkWithTagString(): void
    {
        $link = [
            'tags' => 'one two',
        ];
        $id = '41';

        $request = (new FakeRequest(
            'PUT',
            new Uri('', '')
        ))->withServerParams(['SERVER_NAME' => 'domain.tld', 'SERVER_PORT' => 80])
            ->withAttribute(RouteContext::ROUTE_PARSER, $this->routeParser);
        $request = $request->withParsedBody($link);
        $response = $this->controller->putLink($request, new SlimResponse(), ['id' => $id]);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals(self::NB_FIELDS_LINK, count($data));
        $this->assertEquals(['one', 'two'], $data['tags']);
    }

    /**
     * Test link creation with a tag string provided
     */
    public function testPutLinkWithTagString2(): void
    {
        $link = [
            'tags' => ['one two'],
        ];
        $id = '41';

        $request = (new FakeRequest(
            'DELETE',
            new Uri('', '')
        ))->withServerParams(['SERVER_NAME' => 'domain.tld', 'SERVER_PORT' => 80]);
        $request = $request->withParsedBody($link);
        $response = $this->controller->putLink($request, new SlimResponse(), ['id' => $id]);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals(self::NB_FIELDS_LINK, count($data));
        $this->assertEquals(['one', 'two'], $data['tags']);
    }
}
