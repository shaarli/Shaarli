<?php

require_once('application/api/ApiUtils.php');

/**
 * Class ApiUtilsTest
 */
class ApiUtilsTest extends PHPUnit_Framework_TestCase
{
    /**
     * Force the timezone for ISO datetimes.
     */
    public static function setUpBeforeClass()
    {
        date_default_timezone_set('UTC');
    }

    /**
     * Test getMethod() with valid data.
     */
    public function testGetMethod()
    {
        $this->assertEquals('getLinks', ApiUtils::getMethod('get', '/links'));
        $this->assertEquals('getLinks', ApiUtils::getMethod('get', '/links/foo/bar'));
    }

    /**
     * Test getMethod() with invalid data.
     * No error/exception is raised, but invalid data should be ignored.
     */
    public function testGetMethodInvalid()
    {
        $this->assertEquals('foo', ApiUtils::getMethod('foo', 'bar'));
        $this->assertEquals('getInvalid', ApiUtils::getMethod('get', 'links/invalid'));
        $this->assertEquals('', ApiUtils::getMethod(false, false));
        $this->assertEquals('', ApiUtils::getMethod('', ''));
    }

    /**
     * Test getPathParameters with valid data.
     */
    public function testGetPathParameters()
    {
        $this->assertEquals(array(), ApiUtils::getPathParameters('/links'));
        $this->assertEquals(array('foo'), ApiUtils::getPathParameters('/links/foo'));
        $this->assertEquals(array('foo', 'bar'), ApiUtils::getPathParameters('/links/foo/bar'));
    }

    /**
     * Test getPathParameters with invalid data.
     */
    public function testGetPathParametersInvalid()
    {
        $this->assertEquals(array(), ApiUtils::getPathParameters('bar'));
        $this->assertEquals(array(), ApiUtils::getPathParameters('links/invalid'));
        $this->assertEquals(array('path'), ApiUtils::getPathParameters('links/invalid/path'));
        $this->assertEquals(array(), ApiUtils::getPathParameters(false));
        $this->assertEquals(array(), ApiUtils::getPathParameters(''));
    }

    /**
     * Test formatLink with complete an incomplete data.
     */
    public function testFormatLink()
    {
        $link = array(
            'linkdate' => '20160718_100001',
            'url' => 'http://foobar.tld',
            'title' => 'Foo Bar',
            'description' => 'foobar',
            'tags' => 'foo bar',
            'private' => true,
        );
        $expected = array(
            'id' => '20160718_100001',
            'url' => 'http://foobar.tld',
            'title' => 'Foo Bar',
            'description' => 'foobar',
            'tags' => array('foo', 'bar'),
            'private' => true,
            'created' => '2016-07-18T10:00:01+0000',
            'updated' => '',
        );
        $this->assertEquals($expected, ApiUtils::formatLink($link));

        $link = array(
            'linkdate' => '20160718_100001',
        );
        $expected = array(
            'id' => '20160718_100001',
            'url' => '',
            'title' => '',
            'description' => '',
            'tags' => array(),
            'private' => false,
            'created' => '2016-07-18T10:00:01+0000',
            'updated' => '',
        );
        $this->assertEquals($expected, ApiUtils::formatLink($link));
        $this->assertEquals(array(), ApiUtils::formatLink(array()));
    }

    /**
     * Test formatError().
     */
    public function testFormatError()
    {
        $expected = array(
            'code' => 100,
            'message' => 'foo bar',
        );
        $this->assertEquals($expected, ApiUtils::formatError(100, 'foo bar'));
        $expected = array(
            'code' => '',
            'message' => '',
        );
        $this->assertEquals($expected, ApiUtils::formatError(false, false));
    }

    /**
     * Test buildLinkFromRequest().
     */
    public function testBuildLinkFromRequest()
    {
        $linkdate = '123456';
        $input = array(
            'url' => 'http://foobar.tld',
            'title' => 'Foo Bar',
            'description' => 'foobar',
            'tags' => array('foo', 'bar'),
            'private' => true,
        );
        $link = ApiUtils::buildLinkFromRequest($linkdate, $input, true);
        $this->assertEquals($linkdate, $link['linkdate']);
        $this->assertEquals($input['url'], $link['url']);
        $this->assertEquals($input['title'], $link['title']);
        $this->assertEquals($input['description'], $link['description']);
        $this->assertEquals('foo bar', $link['tags']);
        $this->assertEquals($input['private'], $link['private']);

        // Empty input link.
        $link = ApiUtils::buildLinkFromRequest($linkdate, array(), true);
        $this->assertEquals($linkdate, $link['linkdate']);
        $this->assertRegExp('#\?[\d \w\-_]{6}#', $link['url']);
        $this->assertEquals($link['url'], $link['title']);
        $this->assertEmpty($link['description']);
        $this->assertEmpty($link['tags']);
        $this->assertTrue($link['private']);

        $link = ApiUtils::buildLinkFromRequest($linkdate, array(), false);
        $this->assertFalse($link['private']);
    }

    /**
     * Test validateJwtToken() with a valid JWT token.
     */
    public function testValidateJwtTokenValid()
    {
        $secret = 'WarIsPeace';
        ApiUtils::validateJwtToken(ApiTest::generateJwtToken($secret), $secret);
    }

    /**
     * Test validateJwtToken() with a malformed JWT token.
     *
     * @expectedException ApiAuthorizationException
     * @expectedExceptionMessage Malformed JWT token
     */
    public function testValidateJwtTokenMalformed()
    {
        $token = 'ABC.DEF';
        ApiUtils::validateJwtToken($token, 'foo');
    }

    /**
     * Test validateJwtToken() with an empty JWT token.
     *
     * @expectedException ApiAuthorizationException
     * @expectedExceptionMessage Malformed JWT token
     */
    public function testValidateJwtTokenMalformedEmpty()
    {
        $token = false;
        ApiUtils::validateJwtToken($token, 'foo');
    }

    /**
     * Test validateJwtToken() with a JWT token without header.
     *
     * @expectedException ApiAuthorizationException
     * @expectedExceptionMessage Malformed JWT token
     */
    public function testValidateJwtTokenMalformedEmptyHeader()
    {
        $token = '.payload.signature';
        ApiUtils::validateJwtToken($token, 'foo');
    }

    /**
     * Test validateJwtToken() with a JWT token without payload
     *
     * @expectedException ApiAuthorizationException
     * @expectedExceptionMessage Malformed JWT token
     */
    public function testValidateJwtTokenMalformedEmptyPayload()
    {
        $token = 'header..signature';
        ApiUtils::validateJwtToken($token, 'foo');
    }

    /**
     * Test validateJwtToken() with a JWT token with an empty signature.
     *
     * @expectedException ApiAuthorizationException
     * @expectedExceptionMessage Invalid JWT signature
     */
    public function testValidateJwtTokenInvalidSignatureEmpty()
    {
        $token = 'header.payload.';
        ApiUtils::validateJwtToken($token, 'foo');
    }

    /**
     * Test validateJwtToken() with a JWT token with an invalid signature.
     *
     * @expectedException ApiAuthorizationException
     * @expectedExceptionMessage Invalid JWT signature
     */
    public function testValidateJwtTokenInvalidSignature()
    {
        $token = 'header.payload.nope';
        ApiUtils::validateJwtToken($token, 'foo');
    }

    /**
     * Test validateJwtToken() with a JWT token with a signature generated with the wrong API secret.
     *
     * @expectedException ApiAuthorizationException
     * @expectedExceptionMessage Invalid JWT signature
     */
    public function testValidateJwtTokenInvalidSignatureSecret()
    {
        ApiUtils::validateJwtToken(ApiTest::generateJwtToken('foo'), 'bar');
    }

    /**
     * Test validateJwtToken() with a JWT token with a an invalid header (not JSON).
     *
     * @expectedException ApiAuthorizationException
     * @expectedExceptionMessage Invalid JWT header
     */
    public function testValidateJwtTokenInvalidHeader()
    {
        $token = $this->generateJwtToken('notJSON', '{"JSON":1}', 'secret');
        ApiUtils::validateJwtToken($token, 'secret');
    }

    /**
     * Test validateJwtToken() with a JWT token with a an invalid payload (not JSON).
     *
     * @expectedException ApiAuthorizationException
     * @expectedExceptionMessage Invalid JWT payload
     */
    public function testValidateJwtTokenInvalidPayload()
    {
        $token = $this->generateJwtToken('{"JSON":1}', 'notJSON', 'secret');
        ApiUtils::validateJwtToken($token, 'secret');
    }

    /**
     * Test validateJwtToken() with a JWT token without issued time.
     *
     * @expectedException ApiAuthorizationException
     * @expectedExceptionMessage Invalid JWT issued time
     */
    public function testValidateJwtTokenInvalidTimeEmpty()
    {
        $token = $this->generateJwtToken('{"JSON":1}', '{"JSON":1}', 'secret');
        ApiUtils::validateJwtToken($token, 'secret');
    }

    /**
     * Test validateJwtToken() with an expired JWT token.
     *
     * @expectedException ApiAuthorizationException
     * @expectedExceptionMessage Invalid JWT issued time
     */
    public function testValidateJwtTokenInvalidTimeExpired()
    {
        $token = $this->generateJwtToken('{"JSON":1}', '{"iat":' . (time() - 600) . '}', 'secret');
        ApiUtils::validateJwtToken($token, 'secret');
    }

    /**
     * Test validateJwtToken() with a JWT token issued in the future.
     *
     * @expectedException ApiAuthorizationException
     * @expectedExceptionMessage Invalid JWT issued time
     */
    public function testValidateJwtTokenInvalidTimeFuture()
    {
        $token = $this->generateJwtToken('{"JSON":1}', '{"iat":' . (time() + 60) . '}', 'secret');
        ApiUtils::validateJwtToken($token, 'secret');
    }

    /**
     * Test render() with a body and headers.
     *
     * This have to run in a separate process because headers
     * can't be set after anything has been output.
     *
     * Note: This is bit slower than regular tests.
     *
     * @runInSeparateProcess
     */
    public function testRender()
    {
        $code = 621;
        $headers = array(
            'Sandwich: ham, cheese, more-cheese',
            'Foo: Bar',
        );
        $data = array('key' => 'value');

        $this->expectOutputString(json_encode($data));
        ApiUtils::render(new ApiResponse($code, $headers, $data));

        $responseHeaders = xdebug_get_headers();
        foreach ($headers as $header) {
            $this->assertTrue(in_array($header, $responseHeaders));
        }
        $this->assertTrue($this->arrayContains('application/json', $responseHeaders));

        // PHP 5.4.
        if (function_exists('http_response_code')) {
            $this->assertEquals($code, http_response_code());
        } else {
            $this->assertTrue(in_array('X-PHP-Response-Code: ' . $code, $responseHeaders));
        }
    }

    /**
     * Test render() with a response without headers or a body.
     *
     * @runInSeparateProcess
     */
    public function testRenderMinimal()
    {
        $code = 123;
        $this->expectOutputString('');
        ApiUtils::render(new ApiResponse($code, array(), null));

        $responseHeaders = xdebug_get_headers();
        $this->assertTrue($this->arrayContains('application/json', $responseHeaders));

        // PHP 5.4.
        if (function_exists('http_response_code')) {
            $this->assertEquals($code, http_response_code());
        } else {
            $this->assertTrue(in_array('X-PHP-Response-Code: ' . $code, $responseHeaders));
        }
    }

    /**
     * Test parseRequestBody() with valid data.
     */
    public function testParseRequestBodyValid()
    {
        $data = array(
            'foo' => 'bar',
            false,
            'test' => array()
        );
        $this->assertEquals($data, ApiUtils::parseRequestBody(json_encode($data)));
        $data = 'string';
        $this->assertEquals($data, ApiUtils::parseRequestBody(json_encode($data)));
        $data = false;
        $this->assertEquals($data, ApiUtils::parseRequestBody(json_encode($data)));
        $data = '';
        $this->assertEquals($data, ApiUtils::parseRequestBody(json_encode($data)));
        $data = false;
        $this->assertNull(ApiUtils::parseRequestBody($data));
        $data = '';
        $this->assertNull(ApiUtils::parseRequestBody($data));
    }

    /**
     * Test parseRequestBody() with invalid data.
     *
     * @expectedException ApiBadParametersException
     * @expectedExceptionMessageRegExp /Invalid request content:/
     */
    public function testParseRequestBodyNotValid()
    {
        $data = 'syntax error (not json)';
        ApiUtils::parseRequestBody($data);
    }

    /**
     * Generate a JWT token from given header and payload.
     *
     * @param string $header  Header in JSON format.
     * @param string $payload Payload in JSON format.
     * @param string $secret  API secret used to hash the signature.
     * 
     * @return string JWT token.
     */
    public function generateJwtToken($header, $payload, $secret)
    {
        $header = base64_encode($header);
        $payload = base64_encode($payload);
        $signature = hash_hmac('sha512', $header . '.' . $payload, $secret);
        return $header . '.' . $payload . '.' . $signature;
    }

    /**
     * Helper function checking if an array contains a string.
     * in_array() only checks if the needle is equal with an array element.
     *
     * @param string $needle   String to search.
     * @param array  $haystack Array to search in.
     *
     * @return bool true if the needle is found in the haystack, false otherwise.
     */
    public function arrayContains($needle, $haystack)
    {
        foreach ($haystack as $element) {
            if (strpos($element, $needle) !== false) {
                return true;
            }
        }
        return false;
    }
}
