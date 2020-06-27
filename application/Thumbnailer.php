<?php

namespace Shaarli;

use Shaarli\Config\ConfigManager;
use WebThumbnailer\Application\ConfigManager as WTConfigManager;
use WebThumbnailer\WebThumbnailer;

/**
 * Class Thumbnailer
 *
 * Utility class used to retrieve thumbnails using web-thumbnailer dependency.
 */
class Thumbnailer
{
    const COMMON_MEDIA_DOMAINS = [
        'imgur.com',
        'flickr.com',
        'youtube.com',
        'wikimedia.org',
        'redd.it',
        'gfycat.com',
        'media.giphy.com',
        'twitter.com',
        'twimg.com',
        'instagram.com',
        'pinterest.com',
        'pinterest.fr',
        'soundcloud.com',
        'tumblr.com',
        'deviantart.com',
    ];

    const MODE_ALL = 'all';
    const MODE_COMMON = 'common';
    const MODE_NONE = 'none';

    /**
     * @var WebThumbnailer instance.
     */
    protected $wt;

    /**
     * @var ConfigManager instance.
     */
    protected $conf;

    /**
     * Thumbnailer constructor.
     *
     * @param ConfigManager $conf instance.
     */
    public function __construct($conf)
    {
        $this->conf = $conf;

        if (! $this->checkRequirements()) {
            $this->conf->set('thumbnails.mode', Thumbnailer::MODE_NONE);
            $this->conf->write(true);
            // TODO: create a proper error handling system able to catch exceptions...
            die(t(
                'php-gd extension must be loaded to use thumbnails. '
                .'Thumbnails are now disabled. Please reload the page.'
            ));
        }

        $this->wt = new WebThumbnailer();
        WTConfigManager::addFile('inc/web-thumbnailer.json');
        $this->wt->maxWidth($this->conf->get('thumbnails.width'))
                 ->maxHeight($this->conf->get('thumbnails.height'))
                 ->crop(true)
                 ->debug($this->conf->get('dev.debug', false));
    }

    /**
     * Retrieve a thumbnail for given URL
     *
     * @param string $url where to look for a thumbnail.
     *
     * @return bool|string The thumbnail relative cache file path, or false if none has been found.
     */
    public function get($url)
    {
        if ($this->conf->get('thumbnails.mode') === self::MODE_COMMON
            && ! $this->isCommonMediaOrImage($url)
        ) {
            return false;
        }

        try {
            return $this->wt->thumbnail($url);
        } catch (\Throwable $e) {
            // Exceptions are only thrown in debug mode.
            error_log(get_class($e) . ': ' . $e->getMessage());
        }
        return false;
    }

    /**
     * We check weather the given URL is from a common media domain,
     * or if the file extension is an image.
     *
     * @param string $url to check
     *
     * @return bool true if it's an image or from a common media domain, false otherwise.
     */
    public function isCommonMediaOrImage($url)
    {
        foreach (self::COMMON_MEDIA_DOMAINS as $domain) {
            if (strpos($url, $domain) !== false) {
                return true;
            }
        }

        if (endsWith($url, '.jpg') || endsWith($url, '.png') || endsWith($url, '.jpeg')) {
            return true;
        }

        return false;
    }

    /**
     * Make sure that requirements are match to use thumbnails:
     *   - php-gd is loaded
     */
    protected function checkRequirements()
    {
        return extension_loaded('gd');
    }
}
