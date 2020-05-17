<?php
/**
 * HttpUtils' tests
 */

namespace Shaarli\Http;

require_once 'application/http/HttpUtils.php';

/**
 * Unitary tests for index_url()
 */
class IndexUrlTest extends \PHPUnit\Framework\TestCase
{
    /**
     * If on the main page, remove "index.php" from the URL resource
     */
    public function testRemoveIndex()
    {
        $this->assertEquals(
            'http://host.tld/',
            index_url(
                array(
                    'HTTPS' => 'Off',
                    'SERVER_NAME' => 'host.tld',
                    'SERVER_PORT' => '80',
                    'SCRIPT_NAME' => '/index.php'
                )
            )
        );

        $this->assertEquals(
            'http://host.tld/admin/',
            index_url(
                array(
                    'HTTPS' => 'Off',
                    'SERVER_NAME' => 'host.tld',
                    'SERVER_PORT' => '80',
                    'SCRIPT_NAME' => '/admin/index.php'
                )
            )
        );
    }

    /**
     * The resource is != "index.php"
     */
    public function testOtherResource()
    {
        $this->assertEquals(
            'http://host.tld/page.php',
            page_url(
                array(
                    'HTTPS' => 'Off',
                    'SERVER_NAME' => 'host.tld',
                    'SERVER_PORT' => '80',
                    'SCRIPT_NAME' => '/page.php'
                )
            )
        );

        $this->assertEquals(
            'http://host.tld/admin/page.php',
            page_url(
                array(
                    'HTTPS' => 'Off',
                    'SERVER_NAME' => 'host.tld',
                    'SERVER_PORT' => '80',
                    'SCRIPT_NAME' => '/admin/page.php'
                )
            )
        );
    }

    /**
     * The route is stored in REQUEST_URI
     */
    public function testPageUrlWithRoute()
    {
        $this->assertEquals(
            'http://host.tld/picture-wall',
            page_url(
                array(
                    'HTTPS' => 'Off',
                    'SERVER_NAME' => 'host.tld',
                    'SERVER_PORT' => '80',
                    'SCRIPT_NAME' => '/index.php',
                    'REQUEST_URI' => '/picture-wall',
                )
            )
        );

        $this->assertEquals(
            'http://host.tld/admin/picture-wall',
            page_url(
                array(
                    'HTTPS' => 'Off',
                    'SERVER_NAME' => 'host.tld',
                    'SERVER_PORT' => '80',
                    'SCRIPT_NAME' => '/admin/index.php',
                    'REQUEST_URI' => '/admin/picture-wall',
                )
            )
        );
    }
}
