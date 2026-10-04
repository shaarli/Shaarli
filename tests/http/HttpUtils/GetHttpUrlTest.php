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
        // DNS resolution  for non.existent returns empty records, request should be blocked
        $this->assertStringContainsString('Blocked', $headers[0]);
        $this->assertFalse($content);
    }

    /**
     * Literal private/reserved IP addresses ann IPv6 addresses are blocked by is_safe_url()
     */
    public function testIsSafeUrlBlocksPrivateLiteralIPs()
    {
        // IPv4 private ranges
        $this->assertFalse(is_safe_url('http://127.0.0.1/'));
        $this->assertFalse(is_safe_url('http://192.168.1.1/'));
        $this->assertFalse(is_safe_url('http://10.0.0.1/'));
        $this->assertFalse(is_safe_url('http://172.16.0.1/'));

        // IPv6 loopback and private ranges
        $this->assertFalse(is_safe_url('http://[::1]/'));
        $this->assertFalse(is_safe_url('http://[fe80::1]/'));
        $this->assertFalse(is_safe_url('http://[fc00::1]/'));
        // Public IPv6 is also blocked (is_private_ip treats all IPv6 as private)
        $this->assertFalse(is_safe_url('http://[2001:4860:4860::8888]/'));

        // Public IPv4 passes
        $this->assertTrue(is_safe_url('http://8.8.8.8/'));
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
