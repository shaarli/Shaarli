<?php

/**
 * HttpUtils' tests
 */

namespace Shaarli\Http;

/**
 * Unitary tests for server_url()
 */
class ServerUrlTest extends \Shaarli\TestCase
{
    /**
     * Detect if the server uses SSL
     */
    public function testHttpsScheme()
    {
        $this->assertEquals(
            'https://host.tld',
            server_url(
                [
                    'HTTPS' => 'ON',
                    'SERVER_NAME' => 'host.tld',
                    'SERVER_PORT' => '443'
                ]
            )
        );

        $this->assertEquals(
            'https://host.tld:8080',
            server_url(
                [
                    'HTTPS' => 'ON',
                    'SERVER_NAME' => 'host.tld',
                    'SERVER_PORT' => '8080'
                ]
            )
        );
    }

    /**
     * Use the Host header when the web server sets no server name
     */
    public function testEmptyServerNameUsesHost()
    {
        $this->assertEquals(
            'http://127.0.0.1:8000',
            server_url(
                [
                    'SERVER_NAME' => '',
                    'SERVER_PORT' => '80',
                    'HTTP_HOST' => '127.0.0.1:8000'
                ]
            )
        );
    }

    /**
     * Detect a Proxy that sets Forwarded-Host
     */
    public function testHttpsProxyForwardedHost()
    {
        $this->assertEquals(
            'https://host.tld:8080',
            server_url(
                [
                    'HTTP_X_FORWARDED_PROTO' => 'https',
                    'HTTP_X_FORWARDED_PORT' => '8080',
                    'HTTP_X_FORWARDED_HOST' => 'host.tld'
                ]
            )
        );

        $this->assertEquals(
            'https://host.tld:4974',
            server_url(
                [
                    'HTTP_X_FORWARDED_PROTO' => 'https, https',
                    'HTTP_X_FORWARDED_PORT' => '4974, 80',
                    'HTTP_X_FORWARDED_HOST' => 'host.tld, example.com'
                ]
            )
        );
    }

    /**
     * Detect a Proxy with SSL enabled
     */
    public function testHttpsProxyForward()
    {
        $this->assertEquals(
            'https://host.tld:8080',
            server_url(
                [
                    'HTTPS' => 'Off',
                    'SERVER_NAME' => 'host.tld',
                    'SERVER_PORT' => '80',
                    'HTTP_X_FORWARDED_PROTO' => 'https',
                    'HTTP_X_FORWARDED_PORT' => '8080'
                ]
            )
        );

        $this->assertEquals(
            'https://host.tld',
            server_url(
                [
                    'HTTPS' => 'Off',
                    'SERVER_NAME' => 'host.tld',
                    'SERVER_PORT' => '80',
                    'HTTP_X_FORWARDED_PROTO' => 'https'
                ]
            )
        );

        $this->assertEquals(
            'https://host.tld',
            server_url(
                [
                    'HTTPS' => 'Off',
                    'SERVER_NAME' => 'host.tld',
                    'SERVER_PORT' => '80',
                    'HTTP_X_FORWARDED_PROTO' => 'https',
                    'HTTP_X_FORWARDED_PORT' => '443'
                ]
            )
        );

        $this->assertEquals(
            'https://host.tld:4974',
            server_url(
                [
                    'HTTPS' => 'Off',
                    'SERVER_NAME' => 'host.tld',
                    'SERVER_PORT' => '80',
                    'HTTP_X_FORWARDED_PROTO' => 'https, https',
                    'HTTP_X_FORWARDED_PORT' => '4974, 80'
                ]
            )
        );
    }

    /**
     * Detect if the server uses a specific port (!= 80)
     */
    public function testPort()
    {
        // HTTP
        $this->assertEquals(
            'http://host.tld:8080',
            server_url(
                [
                    'HTTPS' => 'OFF',
                    'SERVER_NAME' => 'host.tld',
                    'SERVER_PORT' => '8080'
                ]
            )
        );

        // HTTPS
        $this->assertEquals(
            'https://host.tld:8080',
            server_url(
                [
                    'HTTPS' => 'ON',
                    'SERVER_NAME' => 'host.tld',
                    'SERVER_PORT' => '8080'
                ]
            )
        );
    }

    /**
     * HTTP server on port 80
     */
    public function testStandardHttpPort()
    {
        $this->assertEquals(
            'http://host.tld',
            server_url(
                [
                    'HTTPS' => 'OFF',
                    'SERVER_NAME' => 'host.tld',
                    'SERVER_PORT' => '80'
                ]
            )
        );
    }

    /**
     * HTTPS server on port 443
     */
    public function testStandardHttpsPort()
    {
        $this->assertEquals(
            'https://host.tld',
            server_url(
                [
                    'HTTPS' => 'ON',
                    'SERVER_NAME' => 'host.tld',
                    'SERVER_PORT' => '443'
                ]
            )
        );
    }

    /**
     * Misconfigured server (see #1022): Proxy HTTP but 443
     */
    public function testHttpWithPort433()
    {
        $this->assertEquals(
            'https://host.tld',
            server_url(
                [
                    'HTTPS' => 'Off',
                    'SERVER_NAME' => 'host.tld',
                    'SERVER_PORT' => '80',
                    'HTTP_X_FORWARDED_PROTO' => 'http',
                    'HTTP_X_FORWARDED_PORT' => '443'
                ]
            )
        );

        $this->assertEquals(
            'https://host.tld',
            server_url(
                [
                    'HTTPS' => 'Off',
                    'SERVER_NAME' => 'host.tld',
                    'SERVER_PORT' => '80',
                    'HTTP_X_FORWARDED_PROTO' => 'https, http',
                    'HTTP_X_FORWARDED_PORT' => '443, 80'
                ]
            )
        );
    }
}
