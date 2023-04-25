<?php

namespace Shaarli\Api\Controllers;

use DI\Container as DIContainer;
use Psr\Container\ContainerInterface as Container;
use Shaarli\Config\ConfigManager;
use Shaarli\History;
use Shaarli\TestCase;
use Shaarli\Tests\Utils\FakeRequest;
use Shaarli\Tests\Utils\ReferenceHistory;
use Slim\Http\Environment;

class HistoryTest extends TestCase
{
    /**
     * @var string datastore to test write operations
     */
    protected static $testHistory = 'sandbox/history.php';

    /**
     * @var ConfigManager instance
     */
    protected $conf;

    /**
     * @var ReferenceHistory instance.
     */
    protected $refHistory = null;

    /**
     * @var Container instance.
     */
    protected $container;

    /**
     * @var HistoryController controller instance.
     */
    protected $controller;

    /**
     * Before every test, instantiate a new Api with its config, plugins and bookmarks.
     */
    protected function setUp(): void
    {
        $this->initRequestResponseFactories();
        $this->conf = new ConfigManager('tests/utils/config/configJson');
        $this->refHistory = new ReferenceHistory();
        $this->refHistory->write(self::$testHistory);
        $this->container = new DIContainer();
        $this->container->set('conf', $this->conf);
        $this->container->set('db', true);
        $this->container->set('history', new History(self::$testHistory));

        $this->controller = new HistoryController($this->container);
    }

    /**
     * After every test, remove the test datastore.
     */
    protected function tearDown(): void
    {
        @unlink(self::$testHistory);
    }

    /**
     * Test /history service without parameter.
     */
    public function testGetHistory()
    {
        $request = $this->requestFactory->createRequest('GET', 'http://shaarli');

        $response = $this->controller->getHistory($request, $this->responseFactory->createResponse());
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);

        $this->assertEquals($this->refHistory->count(), count($data));

        $this->assertEquals(History::DELETED, $data[0]['event']);
        $this->assertEquals(
            \DateTime::createFromFormat('Ymd_His', '20170303_121216')->format(\DateTime::ATOM),
            $data[0]['datetime']
        );
        $this->assertEquals(124, $data[0]['id']);

        $this->assertEquals(History::SETTINGS, $data[1]['event']);
        $this->assertEquals(
            \DateTime::createFromFormat('Ymd_His', '20170302_121215')->format(\DateTime::ATOM),
            $data[1]['datetime']
        );
        $this->assertNull($data[1]['id']);

        $this->assertEquals(History::UPDATED, $data[2]['event']);
        $this->assertEquals(
            \DateTime::createFromFormat('Ymd_His', '20170301_121214')->format(\DateTime::ATOM),
            $data[2]['datetime']
        );
        $this->assertEquals(123, $data[2]['id']);

        $this->assertEquals(History::CREATED, $data[3]['event']);
        $this->assertEquals(
            \DateTime::createFromFormat('Ymd_His', '20170201_121214')->format(\DateTime::ATOM),
            $data[3]['datetime']
        );
        $this->assertEquals(124, $data[3]['id']);

        $this->assertEquals(History::CREATED, $data[4]['event']);
        $this->assertEquals(
            \DateTime::createFromFormat('Ymd_His', '20170101_121212')->format(\DateTime::ATOM),
            $data[4]['datetime']
        );
        $this->assertEquals(123, $data[4]['id']);
    }

    /**
     * Test /history service with limit parameter.
     */
    public function testGetHistoryLimit()
    {
        $query = http_build_query(['limit' => 1]);
        $request = $this->requestFactory->createRequest('GET', 'http://shaarli?' . $query);

        $response = $this->controller->getHistory($request, $this->responseFactory->createResponse());
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);

        $this->assertEquals(1, count($data));

        $this->assertEquals(History::DELETED, $data[0]['event']);
        $this->assertEquals(
            \DateTime::createFromFormat('Ymd_His', '20170303_121216')->format(\DateTime::ATOM),
            $data[0]['datetime']
        );
        $this->assertEquals(124, $data[0]['id']);
    }

    /**
     * Test /history service with offset parameter.
     */
    public function testGetHistoryOffset()
    {
        $query = http_build_query(['offset' => 4]);
        $request = $this->requestFactory->createRequest('GET', 'http://shaarli?' . $query);

        $response = $this->controller->getHistory($request, $this->responseFactory->createResponse());
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);

        $this->assertEquals(1, count($data));

        $this->assertEquals(History::CREATED, $data[0]['event']);
        $this->assertEquals(
            \DateTime::createFromFormat('Ymd_His', '20170101_121212')->format(\DateTime::ATOM),
            $data[0]['datetime']
        );
        $this->assertEquals(123, $data[0]['id']);
    }

    /**
     * Test /history service with since parameter.
     */
    public function testGetHistorySince()
    {
        $query = http_build_query(['since' => '2017-03-03T00:00:00+00:00']);
        $request = $this->requestFactory->createRequest('GET', 'http://shaarli?' . $query);

        $response = $this->controller->getHistory($request, $this->responseFactory->createResponse());
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);

        $this->assertEquals(1, count($data));

        $this->assertEquals(History::DELETED, $data[0]['event']);
        $this->assertEquals(
            \DateTime::createFromFormat('Ymd_His', '20170303_121216')->format(\DateTime::ATOM),
            $data[0]['datetime']
        );
        $this->assertEquals(124, $data[0]['id']);
    }

    /**
     * Test /history service with since parameter.
     */
    public function testGetHistorySinceOffsetLimit()
    {
        $query = http_build_query(['since' => '2017-02-01T00:00:00%2B00:00', 'offset' => '1', 'limit' => '1']);
        $request = $this->requestFactory->createRequest('GET', 'http://shaarli?' . $query);

        $response = $this->controller->getHistory($request, $this->responseFactory->createResponse());
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);

        $this->assertEquals(1, count($data));

        $this->assertEquals(History::SETTINGS, $data[0]['event']);
        $this->assertEquals(
            \DateTime::createFromFormat('Ymd_His', '20170302_121215')->format(\DateTime::ATOM),
            $data[0]['datetime']
        );
    }
}
