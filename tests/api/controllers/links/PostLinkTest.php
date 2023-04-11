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
use Shaarli\TestCase;
use Shaarli\Tests\Utils\FakeRequest;
use Shaarli\Tests\Utils\FakeRouteCollector;
use Shaarli\Tests\Utils\ReferenceHistory;
use Shaarli\Tests\Utils\ReferenceLinkDB;
use Slim\Http\Environment;
use Slim\Psr7\Response as SlimResponse;
use Slim\Psr7\Uri;
use Slim\Router;
use Slim\Routing\RouteContext;

/**
 * Class PostLinkTest
 *
 * Test POST Link REST API service.
 *
 * @package Shaarli\Api\Controllers
 */
class PostLinkTest extends TestCase
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
     * Test link creation without any field: creates a blank note.
     */
    public function testPostLinkMinimal()
    {
        $request = (new FakeRequest(
            'POST',
            new Uri('', '')
        ))->withServerParams(['SERVER_NAME' => 'domain.tld', 'SERVER_PORT' => 80])
            ->withAttribute(RouteContext::ROUTE_PARSER, $this->routeParser);

        $response = $this->controller->postLink($request, new SlimResponse());
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('/api/v1/bookmarks/43', $response->getHeader('Location')[0]);
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals(self::NB_FIELDS_LINK, count($data));
        $this->assertEquals(43, $data['id']);
        $this->assertRegExp('/[\w_-]{6}/', $data['shorturl']);
        $this->assertEquals('http://domain.tld/shaare/' . $data['shorturl'], $data['url']);
        $this->assertEquals('/shaare/' . $data['shorturl'], $data['title']);
        $this->assertEquals('', $data['description']);
        $this->assertEquals([], $data['tags']);
        $this->assertEquals(true, $data['private']);
        $dt = new \DateTime('5 seconds ago');
        $this->assertTrue(
            $dt < \DateTime::createFromFormat(\DateTime::ATOM, $data['created'])
        );
        $this->assertEquals('', $data['updated']);

        $historyEntry = $this->history->getHistory()[0];
        $this->assertEquals(History::CREATED, $historyEntry['event']);
        $this->assertTrue(
            (new \DateTime())->add(\DateInterval::createFromDateString('-5 seconds')) < $historyEntry['datetime']
        );
        $this->assertEquals(43, $historyEntry['id']);
    }

    /**
     * Test link creation with all available fields.
     */
    public function testPostLinkFull()
    {
        $link = [
            'url' => 'website.tld/test?foo=bar',
            'title' => 'new entry',
            'description' => 'shaare description',
            'tags' => ['one', 'two'],
            'private' => true,
            'created' => '2015-05-05T12:30:00+03:00',
            'updated' => '2016-06-05T14:32:10+03:00',
        ];

        $request = (new FakeRequest(
            'POST',
            new Uri('', '')
        ))->withServerParams(['SERVER_NAME' => 'website.tld', 'SERVER_PORT' => 80])
            ->withAttribute(RouteContext::ROUTE_PARSER, $this->routeParser);
        $request = $request->withParsedBody($link);
        $response = $this->controller->postLink($request, new SlimResponse());

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('/api/v1/bookmarks/43', $response->getHeader('Location')[0]);
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals(self::NB_FIELDS_LINK, count($data));
        $this->assertEquals(43, $data['id']);
        $this->assertRegExp('/[\w_-]{6}/', $data['shorturl']);
        $this->assertEquals('http://' . $link['url'], $data['url']);
        $this->assertEquals($link['title'], $data['title']);
        $this->assertEquals($link['description'], $data['description']);
        $this->assertEquals($link['tags'], $data['tags']);
        $this->assertEquals(true, $data['private']);
        $this->assertSame($link['created'], $data['created']);
        $this->assertSame($link['updated'], $data['updated']);
    }

    /**
     * Test link creation with an existing link (duplicate URL). Should return a 409 HTTP error and the existing link.
     */
    public function testPostLinkDuplicate()
    {
        $link = [
            'url' => 'mediagoblin.org/',
            'title' => 'new entry',
            'description' => 'shaare description',
            'tags' => ['one', 'two'],
            'private' => true,
        ];

        $request = (new FakeRequest(
            'POST',
            new Uri('', '')
        ))->withServerParams(['SERVER_NAME' => 'domain.tld', 'SERVER_PORT' => 80])
            ->withAttribute(RouteContext::ROUTE_PARSER, $this->routeParser);
        $request = $request->withParsedBody($link);
        $response = $this->controller->postLink($request, new SlimResponse());

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
     * Test link creation with a tag string provided
     */
    public function testPostLinkWithTagString(): void
    {
        $link = [
            'tags' => 'one two',
        ];
        $request = (new FakeRequest(
            'POST',
            new Uri('', '')
        ))->withServerParams(['SERVER_NAME' => 'domain.tld', 'SERVER_PORT' => 80])
            ->withAttribute(RouteContext::ROUTE_PARSER, $this->routeParser);
        $request = $request->withParsedBody($link);
        $response = $this->controller->postLink($request, new SlimResponse());

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('/api/v1/bookmarks/43', $response->getHeader('Location')[0]);
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals(self::NB_FIELDS_LINK, count($data));
        $this->assertEquals(['one', 'two'], $data['tags']);
    }

    /**
     * Test link creation with a tag string provided
     */
    public function testPostLinkWithTagString2(): void
    {
        $link = [
            'tags' => ['one two'],
        ];
        $request = (new FakeRequest(
            'POST',
            new Uri('', ''),
        ))->withServerParams(['SERVER_NAME' => 'domain.tld', 'SERVER_PORT' => 80])
        ->withAttribute(RouteContext::ROUTE_PARSER, $this->routeParser);
        $request = $request->withParsedBody($link);
        $response = $this->controller->postLink($request, new SlimResponse());

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('/api/v1/bookmarks/43', $response->getHeader('Location')[0]);
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals(self::NB_FIELDS_LINK, count($data));
        $this->assertEquals(['one', 'two'], $data['tags']);
    }
}
