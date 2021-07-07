<?php

namespace Shaarli\Formatter;

use DateTimeInterface;
use Shaarli\Bookmark\Bookmark;
use Shaarli\Config\ConfigManager;

/**
 * Class BookmarkFormatter
 *
 * Abstract class processing all bookmark attributes through methods designed to be overridden.
 *
 * List of available formatted fields:
 *   - id                 ID
 *   - shorturl           Unique identifier, used in permalinks
 *   - url                URL, can be altered in some way, e.g. passing through an HTTP reverse proxy
 *   - real_url           (legacy) same as `url`
 *   - url_html           URL to be displayed in HTML content (it can contain HTML tags)
 *   - title              Title
 *   - title_html         Title to be displayed in HTML content (it can contain HTML tags)
 *   - description        Description content. It most likely contains HTML tags
 *   - thumbnail          Thumbnail: path to local cache file, false if there is none, null if hasn't been retrieved
 *   - taglist            List of tags (array)
 *   - taglist_urlencoded List of tags (array) URL encoded: it must be used to create a link to a URL containing a tag
 *   - taglist_html       List of tags (array) to be displayed in HTML content (it can contain HTML tags)
 *   - tags               Tags separated by a single whitespace
 *   - tags_urlencoded    Tags separated by a single whitespace, URL encoded: must be used to create a link
 *   - sticky             Is sticky (bool)
 *   - private            Is private (bool)
 *   - class              Additional CSS class
 *   - created            Creation DateTime
 *   - updated            Last edit DateTime
 *   - timestamp          Creation timestamp
 *   - updated_timestamp  Last edit timestamp
 *
 * @package Shaarli\Formatter
 */
abstract class BookmarkFormatter
{
    /**
     * @var ConfigManager
     */
    protected $conf;

    /** @var bool */
    protected $isLoggedIn;

    /**
     * @var array Additional parameters than can be used for specific formatting
     *            e.g. index_url for Feed formatting
     */
    protected $contextData = [];

    /**
     * LinkDefaultFormatter constructor.
     * @param ConfigManager $conf
     */
    public function __construct(ConfigManager $conf, bool $isLoggedIn)
    {
        $this->conf = $conf;
        $this->isLoggedIn = $isLoggedIn;
    }

    /**
     * Convert a Bookmark into an array usable by templates and plugins.
     *
     * All Bookmark attributes are formatted through a format method
     * that can be overridden in a formatter extending this class.
     *
     * @param Bookmark $bookmark instance
     *
     * @return array formatted representation of a Bookmark
     */
    public function format($bookmark)
    {
        $out['id'] = $this->formatId($bookmark);
        $out['shorturl'] = $this->formatShortUrl($bookmark);
        $out['url'] = $this->formatUrl($bookmark);
        $out['real_url'] = $this->formatRealUrl($bookmark);
        $out['url_html'] = $this->formatUrlHtml($bookmark);
        $out['title'] = $this->formatTitle($bookmark);
        $out['title_html'] = $this->formatTitleHtml($bookmark);
        $out['description'] = $this->formatDescription($bookmark);
        $out['thumbnail'] = $this->formatThumbnail($bookmark);
        $out['taglist'] = $this->formatTagList($bookmark);
        $out['taglist_urlencoded'] = $this->formatTagListUrlEncoded($bookmark);
        $out['taglist_html'] = $this->formatTagListHtml($bookmark);
        $out['tags'] = $this->formatTagString($bookmark);
        $out['tags_urlencoded'] = $this->formatTagStringUrlEncoded($bookmark);
        $out['sticky'] = $bookmark->isSticky();
        $out['private'] = $bookmark->isPrivate();
        $out['class'] = $this->formatClass($bookmark);
        $out['created'] = $this->formatCreated($bookmark);
        $out['updated'] = $this->formatUpdated($bookmark);
        $out['timestamp'] = $this->formatCreatedTimestamp($bookmark);
        $out['updated_timestamp'] = $this->formatUpdatedTimestamp($bookmark);
        $out['additional_content'] = $this->formatAdditionalContent($bookmark);

        return $out;
    }

    /**
     * Add additional data available to formatters.
     * This is used for example to add `index_url` in description's links.
     *
     * @param string $key   Context data key
     * @param string $value Context data value
     */
    public function addContextData($key, $value)
    {
        $this->contextData[$key] = $value;

        return $this;
    }

    /**
     * Format ID
     *
     * @param Bookmark $bookmark instance
     *
     * @return int formatted ID
     */
    protected function formatId($bookmark)
    {
        return $bookmark->getId();
    }

    /**
     * Format ShortUrl
     *
     * @param Bookmark $bookmark instance
     *
     * @return string formatted ShortUrl
     */
    protected function formatShortUrl($bookmark)
    {
        return $bookmark->getShortUrl();
    }

    /**
     * Format Url
     *
     * @param Bookmark $bookmark instance
     *
     * @return string formatted Url
     */
    protected function formatUrl($bookmark)
    {
        return $bookmark->getUrl();
    }

    /**
     * Format RealUrl
     * Legacy: identical to Url
     *
     * @param Bookmark $bookmark instance
     *
     * @return string formatted RealUrl
     */
    protected function formatRealUrl($bookmark)
    {
        return $this->formatUrl($bookmark);
    }

    /**
     * Format Url Html: to be displayed in HTML content, it can contains HTML tags.
     *
     * @param Bookmark $bookmark instance
     *
     * @return string formatted Url HTML
     */
    protected function formatUrlHtml($bookmark)
    {
        return $this->formatUrl($bookmark);
    }

    /**
     * Format Title
     *
     * @param Bookmark $bookmark instance
     *
     * @return string formatted Title
     */
    protected function formatTitle($bookmark)
    {
        return $bookmark->getTitle();
    }

    /**
     * Format Title HTML: to be displayed in HTML content, it can contains HTML tags.
     *
     * @param Bookmark $bookmark instance
     *
     * @return string formatted Title
     */
    protected function formatTitleHtml($bookmark)
    {
        return $bookmark->getTitle();
    }

    /**
     * Format Description
     *
     * @param Bookmark $bookmark instance
     *
     * @return string formatted Description
     */
    protected function formatDescription($bookmark)
    {
        return $bookmark->getDescription();
    }

    /**
     * Format Thumbnail
     *
     * @param Bookmark $bookmark instance
     *
     * @return string formatted Thumbnail
     */
    protected function formatThumbnail($bookmark)
    {
        return $bookmark->getThumbnail();
    }

    /**
     * Format Tags
     *
     * @param Bookmark $bookmark instance
     *
     * @return array formatted Tags
     */
    protected function formatTagList($bookmark)
    {
        return $this->filterTagList($bookmark->getTags());
    }

    /**
     * Format Url Encoded Tags
     *
     * @param Bookmark $bookmark instance
     *
     * @return array formatted Tags
     */
    protected function formatTagListUrlEncoded($bookmark)
    {
        return array_map('urlencode', $this->filterTagList($bookmark->getTags()));
    }

    /**
     * Format Tags HTML: to be displayed in HTML content, it can contains HTML tags.
     *
     * @param Bookmark $bookmark instance
     *
     * @return array formatted Tags
     */
    protected function formatTagListHtml($bookmark)
    {
        return $this->formatTagList($bookmark);
    }

    /**
     * Format TagString
     *
     * @param Bookmark $bookmark instance
     *
     * @return string formatted TagString
     */
    protected function formatTagString($bookmark)
    {
        return implode($this->conf->get('general.tags_separator', ' '), $this->formatTagList($bookmark));
    }

    /**
     * Format TagString
     *
     * @param Bookmark $bookmark instance
     *
     * @return string formatted TagString
     */
    protected function formatTagStringUrlEncoded($bookmark)
    {
        return implode(' ', $this->formatTagListUrlEncoded($bookmark));
    }

    /**
     * Format Class
     * Used to add specific CSS class for a link
     *
     * @param Bookmark $bookmark instance
     *
     * @return string formatted Class
     */
    protected function formatClass($bookmark)
    {
        return $bookmark->isPrivate() ? 'private' : '';
    }

    /**
     * Format Created
     *
     * @param Bookmark $bookmark instance
     *
     * @return DateTimeInterface instance
     */
    protected function formatCreated(Bookmark $bookmark)
    {
        return $bookmark->getCreated();
    }

    /**
     * Format Updated
     *
     * @param Bookmark $bookmark instance
     *
     * @return DateTimeInterface instance
     */
    protected function formatUpdated(Bookmark $bookmark)
    {
        return $bookmark->getUpdated();
    }

    /**
     * Format CreatedTimestamp
     *
     * @param Bookmark $bookmark instance
     *
     * @return int formatted CreatedTimestamp
     */
    protected function formatCreatedTimestamp(Bookmark $bookmark)
    {
        if (! empty($bookmark->getCreated())) {
            return $bookmark->getCreated()->getTimestamp();
        }
        return 0;
    }

    /**
     * Format UpdatedTimestamp
     *
     * @param Bookmark $bookmark instance
     *
     * @return int formatted UpdatedTimestamp
     */
    protected function formatUpdatedTimestamp(Bookmark $bookmark)
    {
        if (! empty($bookmark->getUpdated())) {
            return $bookmark->getUpdated()->getTimestamp();
        }
        return 0;
    }

    /**
     * Format bookmark's additional content
     *
     * @param Bookmark $bookmark instance
     *
     * @return mixed[]
     */
    protected function formatAdditionalContent(Bookmark $bookmark): array
    {
        return $bookmark->getAdditionalContent();
    }

    /**
     * Format tag list, e.g. remove private tags if the user is not logged in.
     * TODO: this method is called multiple time to format tags, the result should be cached.
     *
     * @param array $tags
     *
     * @return array
     */
    protected function filterTagList(array $tags): array
    {
        if ($this->isLoggedIn === true) {
            return $tags;
        }

        $out = [];
        foreach ($tags as $tag) {
            if (strpos($tag, '.') === 0) {
                continue;
            }

            $out[] = $tag;
        }

        return $out;
    }
}
