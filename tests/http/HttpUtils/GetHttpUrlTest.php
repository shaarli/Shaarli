<?php

/**
 * HttpUtils' tests
 */

namespace Shaarli\Http;

/**
 * Unitary tests for get_http_response()
 */
class GetHttpUrlTest extends \Shaarli\TestCase
{
    /**
     * Get an invalid local URL
     */
    public function testGetInvalidLocalUrl()
    {
        // Local
        list($headers, $content) = get_http_response('/non/existent', 1);
        $this->assertEquals('Invalid HTTP UrlUtils', $headers[0]);
        $this->assertFalse($content);

        // Non HTTP
        list($headers, $content) = get_http_response('ftp://save.tld/mysave', 1);
        $this->assertEquals('Invalid HTTP UrlUtils', $headers[0]);
        $this->assertFalse($content);
    }

    /**
     * Get an invalid remote URL
     */
    public function testGetInvalidRemoteUrl()
    {
        list($headers, $content) = @get_http_response('http://non.existent', 1);
        $this->assertFalse($headers);
        $this->assertFalse($content);
    }

    /**
     * Test getAbsoluteUrl with relative target URL.
     */
    public function testGetAbsoluteUrlWithRelative()
    {
        $origin = 'http://non.existent/blabla/?test';
        $target = '/stuff.php';

        $expected = 'http://non.existent/stuff.php';
        $this->assertEquals($expected, getAbsoluteUrl($origin, $target));

        $target = 'stuff.php';
        $expected = 'http://non.existent/blabla/stuff.php';
        $this->assertEquals($expected, getAbsoluteUrl($origin, $target));
    }

    /**
     * Test getAbsoluteUrl with absolute target URL.
     */
    public function testGetAbsoluteUrlWithAbsolute()
    {
        $origin = 'http://non.existent/blabla/?test';
        $target = 'http://other.url/stuff.php';

        $this->assertEquals($target, getAbsoluteUrl($origin, $target));
    }
}
