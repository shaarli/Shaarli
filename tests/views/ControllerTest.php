<?php

require_once 'application/config/ConfigManager.php';
require_once 'application/PageBuilder.php';
require_once 'application/PluginManager.php';
require_once 'tests/utils/ReferenceLinkDB.php';
require_once 'application/LinkDB.php';

/**
 * Class ControllerTest
 *
 * Parent class for controller test classes.
 * It instantiate controllers parameters.
 */
abstract class ControllerTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var string path to test datastore.
     */
    public static $testDatastore = 'sandbox/datastore.php';

    /**
     * @var Controller
     */
    protected $controller;

    /**
     * @var PageBuilder
     */
    protected $pageBuilder;

    /**
     * @var ConfigManager
     */
    protected $conf;

    /**
     * @var PluginManager
     */
    protected $pluginManager;

    /**
     * @var LinkDB
     */
    protected $linkDB;

    /**
     * Called before every test.
     * Instantiate default objects used in the controller.
     */
    public function setUp()
    {
        $this->conf = new ConfigManager('tests/utils/config/configJson');
        $this->pageBuilder = new PageBuilder($this->conf);
        $this->pluginManager = new PluginManager($this->conf);

        $refDB = new ReferenceLinkDB();
        $refDB->write(self::$testDatastore);

        $this->linkDB = new LinkDB(self::$testDatastore, true, false);
    }

    /**
     * Called after every test.
     * Delete the test datastore.
     */
    public function tearDown()
    {
        if (file_exists(self::$testDatastore)) {
            unlink(self::$testDatastore);
        }
    }

    /**
     * Test if a header has been set.
     *
     * @param string   $expected Expected header regex, without delimiters.
     * @param array    $headers  Headers set.
     *
     * @return bool true if the header has been set, false otherwise.
     */
    public function isHeaderSetRegex($expected, $headers)
    {
        foreach ($headers as $header) {
            if (preg_match('/'. $expected .'/', $header)) {
                return true;
            }
        }
        return false;
    }
}