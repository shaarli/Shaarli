<?php

require_once 'DummyApi.php';

/**
 * Class ApiTest
 */
class ApiTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var string datastore to test write operations 
     */
    protected static $testDatastore = 'sandbox/datastore.php';

    /**
     * @var  ConfigManager instance 
     */
    protected $conf;

    /** 
     * @var  DummyApi instance. Api extension, with a test service. 
     */
    protected $api;

    /**
     * @var ReferenceLinkDB instance.
     */
    protected $refDB = null;

    /**
     * @var LinkDB instance.
     */
    protected $linkDB = null;

    /**
     * Before every test, instanciate a new Api with its config, plugins and links.
     */
    public function setUp()
    {
        $this->conf = new ConfigManager('tests/utils/config/configJson.json.php');
        $this->conf->set('api.secret', 'NapoleonWasALizard');

        $this->refDB = new ReferenceLinkDB();
        $this->refDB->write(self::$testDatastore);

        $this->linkDB = new LinkDB(self::$testDatastore, true, false);

        $this->api = new DummyApi($this->conf, $this->linkDB, new PluginManager($this->conf));
    }

    /**
     * After every test, remove the test datastore.
     */
    public function tearDown()
    {
        @unlink(self::$testDatastore);
    }

    /**
     * Make a valid call to the test method in DummyApi.
     * This test aims to test the call() method which validates parameters and call the appropriate service.
     */
    public function testDummyApiCallValid()
    {
        $token = self::generateJwtToken($this->conf->get('api.secret'));
        $server = array(
            'REQUEST_METHOD' => 'GET',
        );
        $headers = array('jwt' => $token);
        $get = array(
            'q' => '/dummy/path',
            'foo' => 'bar',
        );
        $body = array('pika' => 'chu');
        $response = $this->api->call($server, $headers, $get, json_encode($body));
        $this->assertEquals(666, $response->getCode());
        $this->assertEquals(array('header1', 'header2'), $response->getHeaders());
        $this->assertEquals(
            array(
                'query' => $get,
                'path' => array('path'),
                'body' => $body,
            ),
            $response->getBody()
        );

        // Minimal call.
        $get = array(
            'q' => '/dummy',
        );
        $body = null;
        $response = $this->api->call($server, $headers, $get, $body);
        $this->assertEquals(666, $response->getCode());
        $this->assertEquals(array('header1', 'header2'), $response->getHeaders());
        $this->assertEquals(
            array(
                'query' => $get,
                'path' => array(),
                'body' => $body,
            ),
            $response->getBody()
        );
    }

    /**
     * Test call() with invalid parameters (missing HTTP method, etc.).
     */
    public function testDummyApiCallInvalid()
    {
        // Missing HTTP Method
        $response = $this->api->call(array(), array(), array('q' => '/dummy'), '');
        $this->assertInvalidCallResponse($response);

        // Missing request
        $response = $this->api->call(array('REQUEST_METHOD' => 'GET'), array(), array(), '');
        $this->assertInvalidCallResponse($response);

        // Bad body
        $token = self::generateJwtToken($this->conf->get('api.secret'));
        $response = $this->api->call(
            array('REQUEST_METHOD' => 'GET'),
            array('jwt' => $token),
            array('q' => '/dummy'),
            'not json'
        );
        $this->assertErrorResponse($response, 400, 'Invalid request content');
    }

    /**
     * Make an unauthorized call().
     * FIXME! split the test methods
     */
    public function testDummyApiCallNotAuthorized()
    {
        // Keep PHPUnit execution clean.
        $oldlog = ini_get('error_log');
        ini_set('error_log', '/dev/null');

        // Missing token
        $response = $this->api->call(array('REQUEST_METHOD' => 'GET'), array(), array('q' => '/dummy'), '');
        $this->assertBadTokenResponse($response);

        // Bad token
        $response = $this->api->call(
            array('REQUEST_METHOD' => 'GET'),
            array('jwt' => 'nope.nope.nopes'),
            array('q' => '/dummy'),
            ''
        );
        $this->assertBadTokenResponse($response);
        
        // Invalid API service
        $token = self::generateJwtToken($this->conf->get('api.secret'));
        $response = $this->api->call(
            array('REQUEST_METHOD' => 'GET'),
            array('jwt' => $token),
            array('q' => '/nope'),
            ''
        );
        $this->assertBadTokenResponse($response);

        // The API is disabled
        $token = self::generateJwtToken($this->conf->get('api.secret'));
        $this->conf->set('api.enabled', false);
        $response = $this->api->call(
            array('REQUEST_METHOD' => 'GET'),
            array('jwt' => $token),
            array('q' => '/dummy'),
            ''
        );
        $this->assertBadTokenResponse($response);
        $this->conf->set('api.enabled', true);

        // Valid token but secret isn't set.
        $this->conf->set('api.secret', '');
        $response = $this->api->call(
            array('REQUEST_METHOD' => 'GET'),
            array('jwt' => $token),
            array('q' => '/dummy'),
            ''
        );
        $this->assertBadTokenResponse($response);

        ini_set('error_log', $oldlog);
    }

    /**
     * Test /info service.
     */
    public function testGetInfo()
    {
        /** @var ApiResponse $response */
        $response = $this->api->getInfo();
        $this->assertInstanceOf('ApiResponse', $response);
        $this->assertEquals(200, $response->getCode());
        $this->assertEmpty($response->getHeaders());
        $data = $response->getBody();
        $this->assertEquals(7, $data['global_counter']);
        $this->assertEquals(2, $data['private_counter']);
        $this->assertEquals('Shaarli', $data['settings']['title']);
        $this->assertEquals('?', $data['settings']['header_link']);
        $this->assertEquals('UTC', $data['settings']['timezone']);
        $this->assertEquals(ConfigManager::$DEFAULT_PLUGINS, $data['settings']['enabled_plugins']);
        $this->assertEquals(false, $data['settings']['default_private_links']);

        $title = 'My links';
        $headerLink = 'http://shaarli.tld';
        $timezone = 'Europe/Paris';
        $enabledPlugins = array('foo', 'bar');
        $defaultPrivateLinks = true;
        $this->conf->set('general.title', $title);
        $this->conf->set('general.header_link', $headerLink);
        $this->conf->set('general.timezone', $timezone);
        $this->conf->set('general.enabled_plugins', $enabledPlugins);
        $this->conf->set('privacy.default_private_links', $defaultPrivateLinks);

        $response = $this->api->getInfo();
        $this->assertEquals(200, $response->getCode());
        $this->assertEmpty($response->getHeaders());
        $data = $response->getBody();
        $this->assertInstanceOf('ApiResponse', $response);
        $this->assertEquals(7, $data['global_counter']);
        $this->assertEquals(2, $data['private_counter']);
        $this->assertEquals($title, $data['settings']['title']);
        $this->assertEquals($headerLink, $data['settings']['header_link']);
        $this->assertEquals($timezone, $data['settings']['timezone']);
        $this->assertEquals($enabledPlugins, $data['settings']['enabled_plugins']);
        $this->assertEquals($defaultPrivateLinks, $data['settings']['default_private_links']);
    }

    /**
     * Assert than the given ApiResponse equals the error.
     *
     * @param ApiResponse $response The ApiResponse to test.
     * @param int         $code     Expected HTTP code.
     * @param string      $error    Expected error string.
     */
    public function assertErrorResponse($response, $code, $error = '')
    {
        $this->assertEquals($code, $response->getCode());
        $this->assertEquals(array(), $response->getHeaders());
        $error = ApiUtils::formatError($code, $error);
        $body = $response->getBody();
        $this->assertEquals($error['code'], $body['code']);
        if (!empty($error['message'])) {
            $this->assertContains($error['message'], $body['message']);
        }
    }

    /**
     * Assert than the given response equals a default bad token response.
     *
     * @param ApiResponse $response
     */
    protected function assertBadTokenResponse($response)
    {
        $this->assertErrorResponse($response, 401, 'Not authorized');
    }

    /**
     * Assert than the given response equals a default invalid call response.
     *
     * @param ApiResponse $response
     */
    protected function assertInvalidCallResponse($response)
    {
        $this->assertErrorResponse($response, 400, 'Invalid API call');
    }

    /**
     * Generate a valid JWT token.
     * 
     * @param string $secret API secret used to generate the signature.
     * 
     * @return string Generated token.
     */
    public static function generateJwtToken($secret)
    {
        $header = base64_encode('{
            "typ": "JWT",
            "alg": "HS512"
        }');
        $payload = base64_encode('{
            "iat": '. time() .'
        }');
        $signature = hash_hmac('sha512', $header .'.'. $payload , $secret);
        return $header .'.'. $payload .'.'. $signature;
    }
}
