<?php

/**
 * PluginArchiveorgTest.php
 */

require_once 'plugins/archiveorg/archiveorg.php';

/**
 * Class PluginArchiveorgTest
 * Unit test for the archiveorg plugin
 */
class PluginArchiveorgTest extends PHPUnit_Framework_TestCase
{
    /**
     * Reset plugin path
     */
    public function setUp()
    {
        PluginManager::$PLUGINS_PATH = 'plugins';
    }

    /**
     * Test render_linklist hook on external links.
     */
    public function testArchiveorgLinklistOnExternalLinks()
    {
        $str = 'http://randomstr.com/test';

        $data = array(
            'title' => $str,
            'links' => array(
                array(
                    'url' => $str,
                    'private' => 0,
                    'real_url' => $str
                )
            )
        );

        $data = hook_archiveorg_render_linklist($data);

        $link = $data['links'][0];
        // data shouldn't be altered
        $this->assertEquals($str, $data['title']);
        $this->assertEquals($str, $link['url']);

        // plugin data
        $this->assertEquals(1, count($link['link_plugin']));
        $this->assertNotFalse(strpos($link['link_plugin'][0], $str));
    }

    /**
     * Test render_linklist hook on internal links.
     */
    public function testArchiveorgLinklistOnInternalLinks()
    {
        $internalLink1 = 'http://shaarli.shaarli/?qvMAqg';
        $internalLinkRealURL1 = '?qvMAqg';

        $internalLink2 = 'http://shaarli.shaarli/?2_7zww';
        $internalLinkRealURL2 = '?2_7zww';

        $internalLink3 = 'http://shaarli.shaarli/?z7u-_Q';
        $internalLinkRealURL3 = '?z7u-_Q';

        $data = array(
            'title' => $internalLink1,
            'links' => array(
                array(
                    'url' => $internalLink1,
                    'private' => 0,
                    'real_url' => $internalLinkRealURL1
                ),
                array(
                    'url' => $internalLink1,
                    'private' => 1,
                    'real_url' => $internalLinkRealURL1
                ),
                array(
                    'url' => $internalLink2,
                    'private' => 0,
                    'real_url' => $internalLinkRealURL2
                ),
                array(
                    'url' => $internalLink2,
                    'private' => 1,
                    'real_url' => $internalLinkRealURL2
                ),
                array(
                    'url' => $internalLink3,
                    'private' => 0,
                    'real_url' => $internalLinkRealURL3
                ),
                array(
                    'url' => $internalLink3,
                    'private' => 1,
                    'real_url' => $internalLinkRealURL3
                )
            )
        );

        $data = hook_archiveorg_render_linklist($data);

        // Case n°1: first link type, public
        $link = $data['links'][0];

        $this->assertEquals(1, count($link['link_plugin']));
        $this->assertNotFalse(strpos($link['link_plugin'][0], $internalLink1));

        // Case n°2: first link type, private
        $link = $data['links'][1];

        $this->assertArrayNotHasKey('link_plugin', $link);

        // Case n°3: second link type, public
        $link = $data['links'][2];

        $this->assertEquals(1, count($link['link_plugin']));
        $this->assertNotFalse(strpos($link['link_plugin'][0], $internalLink2));

        // Case n°4: second link type, private
        $link = $data['links'][3];

        $this->assertArrayNotHasKey('link_plugin', $link);

        // Case n°5: third link type, public
        $link = $data['links'][4];

        $this->assertEquals(1, count($link['link_plugin']));
        $this->assertNotFalse(strpos($link['link_plugin'][0], $internalLink3));

        // Case n°6: third link type, private
        $link = $data['links'][5];

        $this->assertArrayNotHasKey('link_plugin', $link);
    }
}
