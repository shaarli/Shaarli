<?php

namespace Shaarli;

use Shaarli\Config\ConfigManager;
use WebThumbnailer\Application\ConfigManager as WTConfigManager;

/**
 * Class ThumbnailerTest
 *
 * We only make 1 thumb test because:
 *
 *   1. the thumbnailer library is itself tested
 *   2. we don't want to make too many external requests during the tests
 */
class ThumbnailerTest extends TestCase
{
    protected const WIDTH = 190;

    protected const HEIGHT = 210;

    /**
     * @var Thumbnailer;
     */
    protected $thumbnailer;

    /**
     * @var ConfigManager
     */
    protected $conf;

    protected function setUp(): void
    {
        $this->conf = new ConfigManager('tests/utils/config/configJson');
        $this->conf->set('thumbnails.mode', Thumbnailer::MODE_ALL);
        $this->conf->set('thumbnails.width', self::WIDTH);
        $this->conf->set('thumbnails.height', self::HEIGHT);
        $this->conf->set('dev.debug', true);

        $this->thumbnailer = new Thumbnailer($this->conf);
        // cache files in the sandbox
        WTConfigManager::addFile('tests/utils/config/wt.json');
    }

    protected function tearDown(): void
    {
        $this->rrmdirContent('sandbox/');
    }

    /**
     * Test a thumbnail with a custom size in 'all' mode.
     */
    public function testThumbnailAllValid()
    {
        $thumb = $this->thumbnailer->get('https://gitlab.com/shaarli/Shaarli');
        $this->assertNotFalse($thumb);
        $image = imagecreatefromstring(file_get_contents($thumb));
        $this->assertEquals(self::WIDTH, imagesx($image));
        $this->assertEquals(self::HEIGHT, imagesy($image));
    }

    /**
     * Test a thumbnail with a custom size in 'common' mode.
     */
    public function testThumbnailCommonValid()
    {
        $this->conf->set('thumbnails.mode', Thumbnailer::MODE_COMMON);
        $thumb = $this->thumbnailer->get('https://imgur.com/jlFgGpe');
        $this->assertNotFalse($thumb);
        $image = imagecreatefromstring(file_get_contents($thumb));
        $this->assertEquals(self::WIDTH, imagesx($image));
        $this->assertEquals(self::HEIGHT, imagesy($image));
    }

    /**
     * Test a thumbnail in 'common' mode which isn't include in common websites.
     */
    public function testThumbnailCommonInvalid()
    {
        $this->conf->set('thumbnails.mode', Thumbnailer::MODE_COMMON);
        $thumb = $this->thumbnailer->get('https://github.com/shaarli/Shaarli/');
        $this->assertFalse($thumb);
    }

    /**
     * Test a thumbnail that can't be retrieved.
     */
    public function testThumbnailNotValid()
    {
        $oldlog = ini_get('error_log');
        ini_set('error_log', '/dev/null');

        $thumbnailer = new Thumbnailer(new ConfigManager());
        $thumb = $thumbnailer->get('nope');
        $this->assertFalse($thumb);

        ini_set('error_log', $oldlog);
    }

    protected function rrmdirContent($dir)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object)) {
                        $this->rrmdirContent($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
        }
    }
}
