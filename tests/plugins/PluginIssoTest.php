<?php

require_once 'plugins/isso/isso.php';

/**
 * Class PluginIssoTest
 *
 * Test the Isso plugin (comment system).
 */
class PluginIssoTest extends PHPUnit_Framework_TestCase
{
    /**
     * Reset plugin path
     */
    public function setUp()
    {
        PluginManager::$PLUGINS_PATH = 'plugins';
    }

    /**
     * Test Isso init without errors.
     */
    public function testWallabagInitNoError()
    {
        $conf = new ConfigManager('');
        $conf->set('plugins.ISSO_SERVER', 'value');
        $errors = isso_init($conf);
        $this->assertEmpty($errors);
    }

    /**
     * Test Isso init with errors.
     */
    public function testWallabagInitError()
    {
        $conf = new ConfigManager('');
        $errors = isso_init($conf);
        $this->assertNotEmpty($errors);
    }

    /**
     * Test render_linklist hook with valid settings to display the comment form.
     */
    public function testIssoDisplayed()
    {
        $conf = new ConfigManager('');
        $conf->set('plugins.ISSO_SERVER', 'value');

        $str = 'http://randomstr.com/test';
        $date = '20161118_100001';
        $data = array(
            'title' => $str,
            'links' => array(
                array(
                    'id' => 12,
                    'url' => $str,
                    'created' => DateTime::createFromFormat(LinkDB::LINK_DATE_FORMAT, $date),
                )
            )
        );

        $data = hook_isso_render_linklist($data, $conf);

        // data shouldn't be altered
        $this->assertEquals($str, $data['title']);
        $this->assertEquals($str, $data['links'][0]['url']);

        // plugin data
        $this->assertEquals(1, count($data['plugin_end_zone']));
        $this->assertNotFalse(strpos(
            $data['plugin_end_zone'][0],
            'data-isso-id="'. $data['links'][0]['id'] .'"'
        ));
        $this->assertNotFalse(strpos(
            $data['plugin_end_zone'][0],
            'data-title="'. $data['links'][0]['id'] .'"'
        ));
        $this->assertNotFalse(strpos($data['plugin_end_zone'][0], 'embed.min.js'));
    }

    /**
     * Test isso plugin when multiple links are displayed (shouldn't be displayed).
     */
    public function testIssoMultipleLinks()
    {
        $conf = new ConfigManager('');
        $conf->set('plugins.ISSO_SERVER', 'value');

        $str = 'http://randomstr.com/test';
        $date1 = '20161118_100001';
        $date2 = '20161118_100002';
        $data = array(
            'title' => $str,
            'links' => array(
                array(
                    'id' => 12,
                    'url' => $str,
                    'created' => DateTime::createFromFormat(LinkDB::LINK_DATE_FORMAT, $date1),
                ),
                array(
                    'id' => 13,
                    'url' => $str . '2',
                    'created' => DateTime::createFromFormat(LinkDB::LINK_DATE_FORMAT, $date2),
                ),
            )
        );

        $processed = hook_isso_render_linklist($data, $conf);
        // data shouldn't be altered
        $this->assertEquals($data, $processed);
    }

    /**
     * Test isso plugin when using search (shouldn't be displayed).
     */
    public function testIssoNotDisplayedWhenSearch()
    {
        $conf = new ConfigManager('');
        $conf->set('plugins.ISSO_SERVER', 'value');

        $str = 'http://randomstr.com/test';
        $date = '20161118_100001';
        $data = array(
            'title' => $str,
            'links' => array(
                array(
                    'id' => 12,
                    'url' => $str,
                    'created' => DateTime::createFromFormat(LinkDB::LINK_DATE_FORMAT, $date),
                )
            ),
            'search_term' => $str
        );

        $processed = hook_isso_render_linklist($data, $conf);

        // data shouldn't be altered
        $this->assertEquals($data, $processed);
    }

    /**
     * Test isso plugin without server configuration (shouldn't be displayed).
     */
    public function testIssoWithoutConf()
    {
        $data = 'abc';
        $conf = new ConfigManager('');
        $processed = hook_isso_render_linklist($data, $conf);
        $this->assertEquals($data, $processed);
    }
}
