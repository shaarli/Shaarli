<?php

namespace Shaarli\Feed;

use DateTime;
use Shaarli\Bookmark\Bookmark;
use Shaarli\Bookmark\BookmarkServiceInterface;
use Shaarli\Formatter\BookmarkFormatter;

/**
 * FeedBuilder class.
 *
 * Used to build ATOM and RSS feeds data.
 */
class FeedBuilder
{
    /**
     * @var string Constant: RSS feed type.
     */
    public static $FEED_RSS = 'rss';

    /**
     * @var string Constant: ATOM feed type.
     */
    public static $FEED_ATOM = 'atom';

    /**
     * @var string Default language if the locale isn't set.
     */
    public static $DEFAULT_LANGUAGE = 'en-en';

    /**
     * @var int Number of bookmarks to display in a feed by default.
     */
    public static $DEFAULT_NB_LINKS = 50;

    /**
     * @var BookmarkServiceInterface instance.
     */
    protected $linkDB;

    /**
     * @var BookmarkFormatter instance.
     */
    protected $formatter;

    /** @var mixed[] $_SERVER */
    protected $serverInfo;

    /**
     * @var boolean True if the user is currently logged in, false otherwise.
     */
    protected $isLoggedIn;

    /**
     * @var boolean Use permalinks instead of direct bookmarks if true.
     */
    protected $usePermalinks;

    /**
     * @var boolean true to hide dates in feeds.
     */
    protected $hideDates;

    /**
     * @var string server locale.
     */
    protected $locale;
    /**
     * @var DateTime Latest item date.
     */
    protected $latestDate;

    /**
     * Feed constructor.
     *
     * @param BookmarkServiceInterface $linkDB     LinkDB instance.
     * @param BookmarkFormatter        $formatter  instance.
     * @param boolean                  $isLoggedIn True if the user is currently logged in, false otherwise.
     */
    public function __construct($linkDB, $formatter, $isLoggedIn)
    {
        $this->linkDB = $linkDB;
        $this->formatter = $formatter;
        $this->isLoggedIn = $isLoggedIn;
    }

    /**
     * Build data for feed templates.
     *
     * @param string $feedType   Type of feed (RSS/ATOM).
     * @param ?array  $userInput  $_GET.
     * @param array $serverInfo $serverInfo $_SERVER.
     * @return array Formatted data for feeds templates.
     */
    public function buildData(string $feedType, ?array $userInput, array $serverInfo)
    {
        // Search for untagged bookmarks
        if (isset($userInput['searchtags']) && empty($userInput['searchtags'])) {
            $userInput['searchtags'] = false;
        }

        $limit = $this->getLimit($userInput);

        // Optionally filter the results:
        $searchResult = $this->linkDB->search($userInput ?? [], null, false, false, true, ['limit' => $limit]);

        $pageaddr = escape(index_url($serverInfo));
        $this->formatter->addContextData('index_url', $pageaddr);
        $links = [];
        foreach ($searchResult->getBookmarks() as $key => $bookmark) {
            $links[$key] = $this->buildItem($feedType, $bookmark, $pageaddr);
        }

        $data['language'] = $this->getTypeLanguage($feedType);
        $data['last_update'] = $this->getLatestDateFormatted($feedType);
        $data['show_dates'] = !$this->hideDates || $this->isLoggedIn;
        // Remove leading path from REQUEST_URI (already contained in $pageaddr).
        $requestUri = preg_replace('#(.*?/)(feed.*)#', '$2', escape($serverInfo['REQUEST_URI']));
        $data['self_link'] = $pageaddr . $requestUri;
        $data['index_url'] = $pageaddr;
        $data['usepermalinks'] = $this->usePermalinks === true;
        $data['links'] = $links;

        return $data;
    }

    /**
     * Set this to true to use permalinks instead of direct bookmarks.
     *
     * @param boolean $usePermalinks true to force permalinks.
     */
    public function setUsePermalinks($usePermalinks)
    {
        $this->usePermalinks = $usePermalinks;
    }

    /**
     * Set this to true to hide timestamps in feeds.
     *
     * @param boolean $hideDates true to enable.
     */
    public function setHideDates($hideDates)
    {
        $this->hideDates = $hideDates;
    }

    /**
     * Set the locale. Used to show feed language.
     *
     * @param string $locale The locale (eg. 'fr_FR.UTF8').
     */
    public function setLocale($locale)
    {
        $this->locale = strtolower($locale);
    }

    /**
     * Build a feed item (one per shaare).
     *
     * @param string $feedType Type of feed (RSS/ATOM).
     * @param Bookmark $link     Single link array extracted from LinkDB.
     * @param string   $pageaddr Index URL.
     *
     * @return array Link array with feed attributes.
     */
    protected function buildItem(string $feedType, $link, $pageaddr)
    {
        $data = $this->formatter->format($link);
        $data['guid'] = rtrim($pageaddr, '/') . '/shaare/' . $data['shorturl'];
        if ($this->usePermalinks === true) {
            $permalink = '<a href="' . $data['url'] . '" title="' . t('Direct link') . '">' . t('Direct link') . '</a>';
        } else {
            $permalink = '<a href="' . $data['guid'] . '" title="' . t('Permalink') . '">' . t('Permalink') . '</a>';
        }
        $data['description'] .= PHP_EOL . PHP_EOL . '<br>&#8212; ' . $permalink;

        $data['pub_iso_date'] = $this->getIsoDate($feedType, $data['created']);

        // atom:entry elements MUST contain exactly one atom:updated element.
        if (!empty($link->getUpdated())) {
            $data['up_iso_date'] = $this->getIsoDate($feedType, $data['updated'], DateTime::ATOM);
        } else {
            $data['up_iso_date'] = $this->getIsoDate($feedType, $data['created'], DateTime::ATOM);
        }

        // Save the more recent item.
        if (empty($this->latestDate) || $this->latestDate < $data['created']) {
            $this->latestDate = $data['created'];
        }
        if (!empty($data['updated']) && $this->latestDate < $data['updated']) {
            $this->latestDate = $data['updated'];
        }

        return $data;
    }

    /**
     * Get the language according to the feed type, based on the locale:
     *
     *   - RSS format: en-us (default: 'en-en').
     *   - ATOM format: fr (default: 'en').
     *
     * @param string $feedType Type of feed (RSS/ATOM).
     *
     * @return string The language.
     */
    protected function getTypeLanguage(string $feedType)
    {
        // Use the locale do define the language, if available.
        if (!empty($this->locale) && preg_match('/^\w{2}[_\-]\w{2}/', $this->locale)) {
            $length = ($feedType === self::$FEED_RSS) ? 5 : 2;
            return str_replace('_', '-', substr($this->locale, 0, $length));
        }
        return ($feedType === self::$FEED_RSS) ? 'en-en' : 'en';
    }

    /**
     * Format the latest item date found according to the feed type.
     *
     * Return an empty string if invalid DateTime is passed.
     *
     * @param string $feedType Type of feed (RSS/ATOM).
     *
     * @return string Formatted date.
     */
    protected function getLatestDateFormatted(string $feedType)
    {
        if (empty($this->latestDate) || !$this->latestDate instanceof DateTime) {
            return '';
        }

        $type = ($feedType == self::$FEED_RSS) ? DateTime::RSS : DateTime::ATOM;
        return $this->latestDate->format($type);
    }

    /**
     * Get ISO date from DateTime according to feed type.
     *
     * @param string      $feedType Type of feed (RSS/ATOM).
     * @param DateTime    $date   Date to format.
     * @param string|bool $format Force format.
     *
     * @return string Formatted date.
     */
    protected function getIsoDate(string $feedType, DateTime $date, $format = false)
    {
        if ($format !== false) {
            return $date->format($format);
        }
        if ($feedType == self::$FEED_RSS) {
            return $date->format(DateTime::RSS);
        }
        return $date->format(DateTime::ATOM);
    }

    /**
     * Returns the number of link to display according to 'nb' user input parameter.
     *
     * If 'nb' not set or invalid, default value: $DEFAULT_NB_LINKS.
     * If 'nb' is set to 'all', display all filtered bookmarks (max parameter).
     *
     * @param array $userInput $_GET.
     *
     * @return int number of bookmarks to display.
     */
    protected function getLimit(?array $userInput)
    {
        if (empty($userInput['nb'])) {
            return self::$DEFAULT_NB_LINKS;
        }

        if ($userInput['nb'] == 'all') {
            return null;
        }

        $intNb = intval($userInput['nb']);
        if (!is_int($intNb) || $intNb == 0) {
            return self::$DEFAULT_NB_LINKS;
        }

        return $intNb;
    }
}
