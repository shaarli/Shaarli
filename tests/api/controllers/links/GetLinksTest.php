<?php
namespace Shaarli\Api\Controllers;

use Shaarli\Config\ConfigManager;

use Slim\Container;
use Slim\Http\Environment;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class GetLinksTest
 *
 * Test get Link list REST API service.
 *
 * @see http://shaarli.github.io/api-documentation/#links-links-collection-get
 *
 * @package Shaarli\Api\Controllers
 */
class GetLinksTest extends \PHPUnit_Framework_TestCase
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
     * @var \ReferenceLinkDB instance.
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
    const NB_FIELDS_LINK = 9;

    /**
     * Before every test, instantiate a new Api with its config, plugins and links.
     */
    public function setUp()
    {
        $this->conf = new ConfigManager('tests/utils/config/configJson');
        $this->refDB = new \ReferenceLinkDB();
        $this->refDB->write(self::$testDatastore);

        $this->container = new Container();
        $this->container['conf'] = $this->conf;
        $this->container['db'] = new \LinkDB(self::$testDatastore, true, false);
        $this->container['history'] = null;

        $this->controller = new Links($this->container);
    }

    /**
     * After every test, remove the test datastore.
     */
    public function tearDown()
    {
        @unlink(self::$testDatastore);
    }

    /**
     * Test basic getLinks service: returns all links.
     */
    public function testGetLinks()
    {
        // Used by index_url().
        $_SERVER['SERVER_NAME'] = 'domain.tld';
        $_SERVER['SERVER_PORT'] = 80;
        $_SERVER['SCRIPT_NAME'] = '/';

        $env = Environment::mock([
            'REQUEST_METHOD' => 'GET',
        ]);
        $request = Request::createFromEnvironment($env);

        $response = $this->controller->getLinks($request, new Response());
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
        $this->assertEquals('http://domain.tld/?WDWyig', $first['url']);
        $this->assertEquals('WDWyig', $first['shorturl']);
        $this->assertEquals('Link title: @website', $first['title']);
        $this->assertEquals(
            'Stallman has a beard and is part of the Free Software Foundation (or not). Seriously, read this. #hashtag',
            $first['description']
        );
        $this->assertEquals('sTuff', $first['tags'][0]);
        $this->assertEquals(false, $first['private']);
        $this->assertEquals(
            \DateTime::createFromFormat(\LinkDB::LINK_DATE_FORMAT, '20150310_114651')->format(\DateTime::ATOM),
            $first['created']
        );
        $this->assertEmpty($first['updated']);

        // Multi tags
        $link = $data[3];
        $this->assertEquals(7, count($link['tags']));

        // Update date
        $this->assertEquals(
            \DateTime::createFromFormat(\LinkDB::LINK_DATE_FORMAT, '20160803_093033')->format(\DateTime::ATOM),
            $link['updated']
        );
    }

    /**
     * Test getLinks service with offset and limit parameter:
     *   limit=1 and offset=1 should return only the second link, ID=8 (ordered by creation date DESC).
     */
    public function testGetLinksOffsetLimit()
    {
        $env = Environment::mock([
            'REQUEST_METHOD' => 'GET',
            'QUERY_STRING' => 'offset=3&limit=1'
        ]);
        $request = Request::createFromEnvironment($env);
        $response = $this->controller->getLinks($request, new Response());
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
        $env = Environment::mock([
            'REQUEST_METHOD' => 'GET',
            'QUERY_STRING' => 'limit=all'
        ]);
        $request = Request::createFromEnvironment($env);
        $response = $this->controller->getLinks($request, new Response());
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
        $env = Environment::mock([
            'REQUEST_METHOD' => 'GET',
            'QUERY_STRING' => 'offset=100'
        ]);
        $request = Request::createFromEnvironment($env);
        $response = $this->controller->getLinks($request, new Response());
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEmpty(count($data));
    }

    /**
     * Test getLinks with visibility parameter set to all
     */
    public function testGetLinksVisibilityAll()
    {
        $env = Environment::mock(
            [
                'REQUEST_METHOD' => 'GET',
                'QUERY_STRING' => 'visibility=all'
            ]
        );
        $request = Request::createFromEnvironment($env);
        $response = $this->controller->getLinks($request, new Response());
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
        $env = Environment::mock([
            'REQUEST_METHOD' => 'GET',
            'QUERY_STRING' => 'visibility=private'
        ]);
        $request = Request::createFromEnvironment($env);
        $response = $this->controller->getLinks($request, new Response());
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
        $env = Environment::mock(
            [
                'REQUEST_METHOD' => 'GET',
                'QUERY_STRING' => 'visibility=public'
            ]
        );
        $request = Request::createFromEnvironment($env);
        $response = $this->controller->getLinks($request, new Response());
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
        $env = Environment::mock([
            'REQUEST_METHOD' => 'GET',
            'QUERY_STRING' => 'searchterm=Tropical'
        ]);
        $request = Request::createFromEnvironment($env);
        $response = $this->controller->getLinks($request, new Response());
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals(1, count($data));
        $this->assertEquals(1, $data[0]['id']);
        $this->assertEquals(self::NB_FIELDS_LINK, count($data[0]));

        // Only in tags - 1 result
        $env = Environment::mock([
            'REQUEST_METHOD' => 'GET',
            'QUERY_STRING' => 'searchterm=tag3'
        ]);
        $request = Request::createFromEnvironment($env);
        $response = $this->controller->getLinks($request, new Response());
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals(1, count($data));
        $this->assertEquals(0, $data[0]['id']);
        $this->assertEquals(self::NB_FIELDS_LINK, count($data[0]));

        // Multiple results (2)
        $env = Environment::mock([
            'REQUEST_METHOD' => 'GET',
            'QUERY_STRING' => 'searchterm=stallman'
        ]);
        $request = Request::createFromEnvironment($env);
        $response = $this->controller->getLinks($request, new Response());
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals(2, count($data));
        $this->assertEquals(41, $data[0]['id']);
        $this->assertEquals(self::NB_FIELDS_LINK, count($data[0]));
        $this->assertEquals(8, $data[1]['id']);
        $this->assertEquals(self::NB_FIELDS_LINK, count($data[1]));

        // Multiword - 2 results
        $env = Environment::mock([
            'REQUEST_METHOD' => 'GET',
            'QUERY_STRING' => 'searchterm=stallman+software'
        ]);
        $request = Request::createFromEnvironment($env);
        $response = $this->controller->getLinks($request, new Response());
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals(2, count($data));
        $this->assertEquals(41, $data[0]['id']);
        $this->assertEquals(self::NB_FIELDS_LINK, count($data[0]));
        $this->assertEquals(8, $data[1]['id']);
        $this->assertEquals(self::NB_FIELDS_LINK, count($data[1]));

        // URL encoding
        $env = Environment::mock([
            'REQUEST_METHOD' => 'GET',
            'QUERY_STRING' => 'searchterm='. urlencode('@web')
        ]);
        $request = Request::createFromEnvironment($env);
        $response = $this->controller->getLinks($request, new Response());
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
        $env = Environment::mock([
            'REQUEST_METHOD' => 'GET',
            'QUERY_STRING' => 'searchterm=nope'
        ]);
        $request = Request::createFromEnvironment($env);
        $response = $this->controller->getLinks($request, new Response());
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals(0, count($data));
    }

    public function testGetLinksSearchTags()
    {
        // Single tag
        $env = Environment::mock([
            'REQUEST_METHOD' => 'GET',
            'QUERY_STRING' => 'searchtags=dev',
        ]);
        $request = Request::createFromEnvironment($env);
        $response = $this->controller->getLinks($request, new Response());
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals(2, count($data));
        $this->assertEquals(0, $data[0]['id']);
        $this->assertEquals(self::NB_FIELDS_LINK, count($data[0]));
        $this->assertEquals(4, $data[1]['id']);
        $this->assertEquals(self::NB_FIELDS_LINK, count($data[1]));

        // Multitag + exclude
        $env = Environment::mock([
            'REQUEST_METHOD' => 'GET',
            'QUERY_STRING' => 'searchtags=stuff+-gnu',
        ]);
        $request = Request::createFromEnvironment($env);
        $response = $this->controller->getLinks($request, new Response());
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals(1, count($data));
        $this->assertEquals(41, $data[0]['id']);
        $this->assertEquals(self::NB_FIELDS_LINK, count($data[0]));

        // wildcard: placeholder at the start
        $env = Environment::mock([
            'REQUEST_METHOD' => 'GET',
            'QUERY_STRING' => 'searchtags=*Tuff',
        ]);
        $request = Request::createFromEnvironment($env);
        $response = $this->controller->getLinks($request, new Response());
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals(2, count($data));
        $this->assertEquals(41, $data[0]['id']);

        // wildcard: placeholder at the end
        $env = Environment::mock([
            'REQUEST_METHOD' => 'GET',
            'QUERY_STRING' => 'searchtags=c*',
        ]);
        $request = Request::createFromEnvironment($env);
        $response = $this->controller->getLinks($request, new Response());
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals(4, count($data));
        $this->assertEquals(6, $data[0]['id']);

        // wildcard: placeholder at the middle
        $env = Environment::mock([
            'REQUEST_METHOD' => 'GET',
            'QUERY_STRING' => 'searchtags=w*b',
        ]);
        $request = Request::createFromEnvironment($env);
        $response = $this->controller->getLinks($request, new Response());
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals(4, count($data));
        $this->assertEquals(6, $data[0]['id']);

        // wildcard: match all
        $env = Environment::mock([
            'REQUEST_METHOD' => 'GET',
            'QUERY_STRING' => 'searchtags=*',
        ]);
        $request = Request::createFromEnvironment($env);
        $response = $this->controller->getLinks($request, new Response());
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals(\ReferenceLinkDB::$NB_LINKS_TOTAL, count($data));
        $this->assertEquals(10, $data[0]['id']);
        $this->assertEquals(41, $data[2]['id']);

        // wildcard: optional ('*' does not need to expand)
        $env = Environment::mock([
            'REQUEST_METHOD' => 'GET',
            'QUERY_STRING' => 'searchtags=*stuff*',
        ]);
        $request = Request::createFromEnvironment($env);
        $response = $this->controller->getLinks($request, new Response());
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals(2, count($data));
        $this->assertEquals(41, $data[0]['id']);

        // wildcard: exclusions
        $env = Environment::mock([
            'REQUEST_METHOD' => 'GET',
            'QUERY_STRING' => 'searchtags=*a*+-*e*',
        ]);
        $request = Request::createFromEnvironment($env);
        $response = $this->controller->getLinks($request, new Response());
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals(1, count($data));
        $this->assertEquals(41, $data[0]['id']); // finds '#hashtag' in descr.

        // wildcard: exclude all
        $env = Environment::mock([
            'REQUEST_METHOD' => 'GET',
            'QUERY_STRING' => 'searchtags=-*',
        ]);
        $request = Request::createFromEnvironment($env);
        $response = $this->controller->getLinks($request, new Response());
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals(0, count($data));
    }

    /**
     * Test getLinks service with search tags+terms.
     */
    public function testGetLinksSearchTermsAndTags()
    {
        $env = Environment::mock([
            'REQUEST_METHOD' => 'GET',
            'QUERY_STRING' => 'searchterm=poke&searchtags=dev',
        ]);
        $request = Request::createFromEnvironment($env);
        $response = $this->controller->getLinks($request, new Response());
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertEquals(1, count($data));
        $this->assertEquals(0, $data[0]['id']);
        $this->assertEquals(self::NB_FIELDS_LINK, count($data[0]));
    }
}
