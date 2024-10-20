<?php

namespace Shaarli\Formatter;

use Shaarli\Config\ConfigManager;
use Shaarli\TestCase;

/**
 * Class FormatterFactoryTest
 *
 * @package Shaarli\Formatter
 */
class FormatterFactoryTest extends TestCase
{
    /** @var string Path of test config file */
    protected static $testConf = 'sandbox/config';

    /** @var FormatterFactory instance */
    protected $factory;

    /** @var ConfigManager instance */
    protected $conf;

    /**
     * Initialize FormatterFactory instance
     */
    protected function setUp(): void
    {
        copy(__DIR__ . '/../utils/config/configJson.json.php', self::$testConf . '.json.php');
        $this->conf = new ConfigManager(self::$testConf);
        $this->factory = new FormatterFactory($this->conf, true);
    }

    /**
     * Test creating an instance of BookmarkFormatter without any setting -> default formatter
     */
    public function testCreateInstanceDefault()
    {
        $this->assertInstanceOf(BookmarkDefaultFormatter::class, $this->factory->getFormatter());
    }

    /**
     * Test creating an instance of BookmarkDefaultFormatter from settings
     */
    public function testCreateInstanceDefaultSetting()
    {
        $this->conf->set('formatter', 'default');
        $this->assertInstanceOf(BookmarkDefaultFormatter::class, $this->factory->getFormatter());
    }

    /**
     * Test creating an instance of BookmarkDefaultFormatter from parameter
     */
    public function testCreateInstanceDefaultParameter()
    {
        $this->assertInstanceOf(
            BookmarkDefaultFormatter::class,
            $this->factory->getFormatter('default')
        );
    }

    /**
     * Test creating an instance of BookmarkRawFormatter from settings
     */
    public function testCreateInstanceRawSetting()
    {
        $this->conf->set('formatter', 'raw');
        $this->assertInstanceOf(BookmarkRawFormatter::class, $this->factory->getFormatter());
    }

    /**
     * Test creating an instance of BookmarkRawFormatter from parameter
     */
    public function testCreateInstanceRawParameter()
    {
        $this->assertInstanceOf(
            BookmarkRawFormatter::class,
            $this->factory->getFormatter('raw')
        );
    }

    /**
     * Test creating an instance of BookmarkMarkdownFormatter from settings
     */
    public function testCreateInstanceMarkdownSetting()
    {
        $this->conf->set('formatter', 'markdown');
        $this->assertInstanceOf(BookmarkMarkdownFormatter::class, $this->factory->getFormatter());
    }

    /**
     * Test creating an instance of BookmarkMarkdownFormatter from parameter
     */
    public function testCreateInstanceMarkdownParameter()
    {
        $this->assertInstanceOf(
            BookmarkMarkdownFormatter::class,
            $this->factory->getFormatter('markdown')
        );
    }
}
