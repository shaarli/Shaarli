<?php

namespace Shaarli\Api\Controllers;

use DI\Container as DIContainer;
use malkusch\lock\mutex\NoMutex;
use Psr\Container\ContainerInterface as Container;
use Shaarli\Bookmark\Bookmark;
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
 * Class GetLinksTest
 *
 * Test get Link list REST API service.
 *
 * @see http://shaarli.github.io/api-documentation/#links-links-collection-get
 *
 * @package Shaarli\Api\Controllers
 */
class GetLinksTest extends \Shaarli\TestCase
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
     * After every test, remove the test datastore.
     */
    protected function tearDown(): void
    {
        @unlink(self::$testDatastore);
    }

    /**
     * Test basic getLinks service: returns all bookmarks.
     */
    public function testGetLinks()
    {
        $request = (new FakeRequest(
            'GET'
        ))->withServerParams(['SERVER_NAME' => 'domain.tld', 'SERVER_PORT' => '80']);

        $response = $this->controller->getLinks($request, new SlimResponse());
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals($this->refDB->countLinks(), count($data));

        // Check order
        $order = [10, 11, 41, 8, 6, 7, 0, 1, 9, 4, 42];
        $cpt = 0;
        foreach ($data as $link) {
            $this->assertEquals(self::NB_FIELDS_LINK, count($link));
            $this->assertEquals($order[$cpt++], $link['id']);
        }

        // Check first element fields
        $first = $data[2];
        $this->assertEquals('http://domain.tld/shaare/WDWyig', $first['url']);
        $this->assertEquals('WDWyig', $first['shorturl']);
        $this->assertEquals('Link title: @website', $first['title']);
        $this->assertEquals(
            'Stallman has a beard and is part of the Free Software Foundation (or not). Seriously, read this. #hashtag',
            $first['description']
        );
        $this->assertEquals('sTuff', $first['tags'][0]);
        $this->assertEquals(false, $first['private']);
        $this->assertEquals(
            \DateTime::createFromFormat(Bookmark::LINK_DATE_FORMAT, '20150310_114651')->format(\DateTime::ATOM),
            $first['created']
        );
        $this->assertEmpty($first['updated']);

        // Multi tags
        $link = $data[3];
        $this->assertEquals(7, count($link['tags']));

        // Update date
        $this->assertEquals(
            \DateTime::createFromFormat(Bookmark::LINK_DATE_FORMAT, '20160803_093033')->format(\DateTime::ATOM),
            $link['updated']
        );
    }

    /**
     * Test getLinks service with offset and limit parameter:
     *   limit=1 and offset=1 should return only the second link, ID=8 (ordered by creation date DESC).
     */
    public function testGetLinksOffsetLimit()
    {
        $request = (new FakeRequest(
            'GET',
            new Uri('', '', 80, '', 'offset=3&limit=1'),
        ))->withServerParams(['SERVER_NAME' => 'domain.tld', 'SERVER_PORT' => '80']);

        $response = $this->controller->getLinks($request, new SlimResponse());
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals(1, count($data));
        $this->assertEquals(8, $data[0]['id']);
        $this->assertEquals(self::NB_FIELDS_LINK, count($data[0]));
    }

    /**
     * Test getLinks with limit=all (return all link).
     */
    public function testGetLinksLimitAll()
    {
        $request = (new FakeRequest(
            'GET',
            new Uri('', '', 80, '', 'limit=all'),
        ))->withServerParams(['SERVER_NAME' => 'domain.tld', 'SERVER_PORT' => '80']);
        $response = $this->controller->getLinks($request, new SlimResponse());
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals($this->refDB->countLinks(), count($data));
        // Check order
        $order = [10, 11, 41, 8, 6, 7, 0, 1, 9, 4, 42];
        $cpt = 0;
        foreach ($data as $link) {
            $this->assertEquals(self::NB_FIELDS_LINK, count($link));
            $this->assertEquals($order[$cpt++], $link['id']);
        }
    }

    /**
     * Test getLinks service with offset and limit parameter:
     *   limit=1 and offset=1 should return only the second link, ID=8 (ordered by creation date DESC).
     */
    public function testGetLinksOffsetTooHigh()
    {
        $request = (new FakeRequest(
            'GET',
            new Uri('', '', 80, '', 'offset=100'),
        ))->withServerParams(['SERVER_NAME' => 'domain.tld', 'SERVER_PORT' => '80']);
        $response = $this->controller->getLinks($request, new SlimResponse());
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEmpty(count($data));
    }

    /**
     * Test getLinks with visibility parameter set to all
     */
    public function testGetLinksVisibilityAll()
    {
        $request = (new FakeRequest(
            'GET',
            new Uri('', '', 80, '', 'visibility=all'),
        ))->withServerParams(['SERVER_NAME' => 'domain.tld', 'SERVER_PORT' => '80']);
        $response = $this->controller->getLinks($request, new SlimResponse());
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string)$response->getBody(), true);
        $this->assertEquals($this->refDB->countLinks(), count($data));
        $this->assertEquals(10, $data[0]['id']);
        $this->assertEquals(41, $data[2]['id']);
        $this->assertEquals(self::NB_FIELDS_LINK, count($data[0]));
    }

    /**
     * Test getLinks with visibility parameter set to private
     */
    public function testGetLinksVisibilityPrivate()
    {
        $request = (new FakeRequest(
            'GET',
            new Uri('', '', 80, '', 'visibility=private'),
        ))->withServerParams(['SERVER_NAME' => 'domain.tld', 'SERVER_PORT' => '80']);
        $response = $this->controller->getLinks($request, new SlimResponse());
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals($this->refDB->countPrivateLinks(), count($data));
        $this->assertEquals(6, $data[0]['id']);
        $this->assertEquals(self::NB_FIELDS_LINK, count($data[0]));
    }

    /**
     * Test getLinks with visibility parameter set to public
     */
    public function testGetLinksVisibilityPublic()
    {
        $request = (new FakeRequest(
            'GET',
            new Uri('', '', 80, '', 'visibility=public'),
        ))->withServerParams(['SERVER_NAME' => 'domain.tld', 'SERVER_PORT' => '80']);
        $response = $this->controller->getLinks($request, new SlimResponse());
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string)$response->getBody(), true);
        $this->assertEquals($this->refDB->countPublicLinks(), count($data));
        $this->assertEquals(10, $data[0]['id']);
        $this->assertEquals(41, $data[2]['id']);
        $this->assertEquals(self::NB_FIELDS_LINK, count($data[0]));
    }

    /**
     * Test getLinks service with offset and limit parameter:
     *   limit=1 and offset=1 should return only the second link, ID=8 (ordered by creation date DESC).
     */
    public function testGetLinksSearchTerm()
    {
        // Only in description - 1 result
        $request = new FakeRequest(
            'GET',
            new Uri('', '', 80, '', 'searchterm=Tropical'),
            null,
            [],
            ['SERVER_NAME' => 'domain.tld', 'SERVER_PORT' => 80]
        );
        $response = $this->controller->getLinks($request, new SlimResponse());
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals(1, count($data));
        $this->assertEquals(1, $data[0]['id']);
        $this->assertEquals(self::NB_FIELDS_LINK, count($data[0]));

        // Only in tags - 1 result
        $request = (new FakeRequest(
            'GET',
            new Uri('', '', 80, '', 'searchterm=tag3'),
        ))->withServerParams(['SERVER_NAME' => 'domain.tld', 'SERVER_PORT' => '80']);
        $response = $this->controller->getLinks($request, new SlimResponse());
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals(1, count($data));
        $this->assertEquals(0, $data[0]['id']);
        $this->assertEquals(self::NB_FIELDS_LINK, count($data[0]));

        // Multiple results (2)
        $request = new FakeRequest(
            'GET',
            new Uri('', '', 80, '', 'searchterm=stallman'),
            null,
            [],
            ['SERVER_NAME' => 'domain.tld', 'SERVER_PORT' => 80]
        );
        $response = $this->controller->getLinks($request, new SlimResponse());
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals(2, count($data));
        $this->assertEquals(41, $data[0]['id']);
        $this->assertEquals(self::NB_FIELDS_LINK, count($data[0]));
        $this->assertEquals(8, $data[1]['id']);
        $this->assertEquals(self::NB_FIELDS_LINK, count($data[1]));

        // Multiword - 2 results
        $request = (new FakeRequest(
            'GET',
            new Uri('', '', 80, '', 'searchterm=stallman+software'),
        ))->withServerParams(['SERVER_NAME' => 'domain.tld', 'SERVER_PORT' => '80']);
        $response = $this->controller->getLinks($request, new SlimResponse());
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals(2, count($data));
        $this->assertEquals(41, $data[0]['id']);
        $this->assertEquals(self::NB_FIELDS_LINK, count($data[0]));
        $this->assertEquals(8, $data[1]['id']);
        $this->assertEquals(self::NB_FIELDS_LINK, count($data[1]));

        // URL encoding
        $request = (new FakeRequest(
            'GET',
            new Uri('', '', 80, '', 'searchterm=' . urlencode('@web')),
        ))->withServerParams(['SERVER_NAME' => 'domain.tld', 'SERVER_PORT' => '80']);
        $response = $this->controller->getLinks($request, new SlimResponse());
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals(2, count($data));
        $this->assertEquals(41, $data[0]['id']);
        $this->assertEquals(self::NB_FIELDS_LINK, count($data[0]));
        $this->assertEquals(8, $data[1]['id']);
        $this->assertEquals(self::NB_FIELDS_LINK, count($data[1]));
    }

    public function testGetLinksSearchTermNoResult()
    {
        $request = (new FakeRequest(
            'GET',
            new Uri('', '', 80, '', 'searchterm=nope'),
        ))->withServerParams(['SERVER_NAME' => 'domain.tld', 'SERVER_PORT' => '80']);
        $response = $this->controller->getLinks($request, new SlimResponse());
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals(0, count($data));
    }

    public function testGetLinksSearchTags()
    {
        // Single tag
        $request = (new FakeRequest(
            'GET',
            new Uri('', '', 80, '', 'searchtags=dev'),
        ))->withServerParams(['SERVER_NAME' => 'domain.tld', 'SERVER_PORT' => '80']);
        $response = $this->controller->getLinks($request, new SlimResponse());
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals(2, count($data));
        $this->assertEquals(0, $data[0]['id']);
        $this->assertEquals(self::NB_FIELDS_LINK, count($data[0]));
        $this->assertEquals(4, $data[1]['id']);
        $this->assertEquals(self::NB_FIELDS_LINK, count($data[1]));

        // Multitag + exclude
        $request = (new FakeRequest(
            'GET',
            new Uri('', '', 80, '', 'searchtags=stuff+-gnu')
        ))->withServerParams(['SERVER_NAME' => 'domain.tld', 'SERVER_PORT' => '80']);
        $response = $this->controller->getLinks($request, new SlimResponse());
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals(1, count($data));
        $this->assertEquals(41, $data[0]['id']);
        $this->assertEquals(self::NB_FIELDS_LINK, count($data[0]));

        // wildcard: placeholder at the start
        $request = new FakeRequest(
            'GET',
            new Uri('', '', 80, '', 'searchtags=*Tuff'),
            null,
            [],
            ['SERVER_NAME' => 'domain.tld', 'SERVER_PORT' => 80]
        );
        $response = $this->controller->getLinks($request, new SlimResponse());
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals(2, count($data));
        $this->assertEquals(41, $data[0]['id']);

        // wildcard: placeholder at the end
        $request = (new FakeRequest(
            'GET',
            new Uri('', '', 80, '', 'searchtags=c*')
        ))->withServerParams(['SERVER_NAME' => 'domain.tld', 'SERVER_PORT' => '80']);
        $response = $this->controller->getLinks($request, new SlimResponse());
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals(5, count($data));
        $this->assertEquals(6, $data[0]['id']);

        // wildcard: placeholder at the middle
        $request = new FakeRequest(
            'GET',
            new Uri('', '', 80, '', 'searchtags=w*b'),
            null,
            [],
            ['SERVER_NAME' => 'domain.tld', 'SERVER_PORT' => 80]
        );
        $response = $this->controller->getLinks($request, new SlimResponse());
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals(4, count($data));
        $this->assertEquals(6, $data[0]['id']);

        // wildcard: match all
        $request = (new FakeRequest(
            'GET',
            new Uri('', '', 80, '', 'searchtags=*')
        ))->withServerParams(['SERVER_NAME' => 'domain.tld', 'SERVER_PORT' => '80']);
        $response = $this->controller->getLinks($request, new SlimResponse());
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals(ReferenceLinkDB::$NB_LINKS_TOTAL, count($data));
        $this->assertEquals(10, $data[0]['id']);
        $this->assertEquals(41, $data[2]['id']);

        // wildcard: optional ('*' does not need to expand)
        $request = (new FakeRequest(
            'GET',
            new Uri('', '', 80, '', 'searchtags=*stuff*')
        ))->withServerParams(['SERVER_NAME' => 'domain.tld', 'SERVER_PORT' => '80']);
        $response = $this->controller->getLinks($request, new SlimResponse());
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals(2, count($data));
        $this->assertEquals(41, $data[0]['id']);

        // wildcard: exclusions
        $request = (new FakeRequest(
            'GET',
            new Uri('', '', 80, '', 'searchtags=*a*+-*e*')
        ))->withServerParams(['SERVER_NAME' => 'domain.tld', 'SERVER_PORT' => '80']);
        $response = $this->controller->getLinks($request, new SlimResponse());
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals(1, count($data));
        $this->assertEquals(41, $data[0]['id']); // finds '#hashtag' in descr.

        // wildcard: exclude all
        $request = (new FakeRequest(
            'GET',
            new Uri('', '', 80, '', 'searchtags=-*'),
        ))->withServerParams(['SERVER_NAME' => 'domain.tld', 'SERVER_PORT' => '80']);
        $response = $this->controller->getLinks($request, new SlimResponse());
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals(0, count($data));
    }

    /**
     * Test getLinks service with search tags+terms.
     */
    public function testGetLinksSearchTermsAndTags()
    {
        $request = (new FakeRequest(
            'GET',
            new Uri('', '', 80, '', 'searchterm=poke&searchtags=dev')
        ))->withServerParams(['SERVER_NAME' => 'domain.tld', 'SERVER_PORT' => '80']);
        $response = $this->controller->getLinks($request, new SlimResponse());
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals(1, count($data));
        $this->assertEquals(0, $data[0]['id']);
        $this->assertEquals(self::NB_FIELDS_LINK, count($data[0]));
    }
}
