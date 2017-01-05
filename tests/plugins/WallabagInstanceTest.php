<?php

require_once 'plugins/wallabag/WallabagInstance.php';

/**
 * Class WallabagInstanceTest
 */
class WallabagInstanceTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var string wallabag url.
     */
    private $instance;

    /**
     * Reset plugin path
     */
    public function setUp()
    {
        $this->instance = 'http://some.url';
    }

    /**
     * Test WallabagInstance with API V1.
     */
    public function testWallabagInstanceV1()
    {
        $instance = new WallabagInstance($this->instance, 1);
        $expected = $this->instance . '/?plainurl=';
        $result = $instance->getWallabagUrl();
        $this->assertEquals($expected, $result);
    }

    /**
     * Test WallabagInstance with API V2.
     */
    public function testWallabagInstanceV2()
    {
        $instance = new WallabagInstance($this->instance, 2);
        $expected = $this->instance . '/bookmarklet?url=';
        $result = $instance->getWallabagUrl();
        $this->assertEquals($expected, $result);
    }

    /**
     * Test WallabagInstance with an invalid API version.
     */
    public function testWallabagInstanceInvalidVersion()
    {
        $instance = new WallabagInstance($this->instance, false);
        $expected = $this->instance . '/?plainurl=';
        $result = $instance->getWallabagUrl();
        $this->assertEquals($expected, $result);

        $instance = new WallabagInstance($this->instance, 3);
        $expected = $this->instance . '/?plainurl=';
        $result = $instance->getWallabagUrl();
        $this->assertEquals($expected, $result);
    }
}
