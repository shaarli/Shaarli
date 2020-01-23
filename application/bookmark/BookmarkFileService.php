<?php


namespace Shaarli\Bookmark;


use Exception;
use Shaarli\Bookmark\Exception\BookmarkNotFoundException;
use Shaarli\Bookmark\Exception\EmptyDataStoreException;
use Shaarli\Config\ConfigManager;
use Shaarli\Formatter\BookmarkMarkdownFormatter;
use Shaarli\History;
use Shaarli\Legacy\LegacyLinkDB;
use Shaarli\Legacy\LegacyUpdater;
use Shaarli\Render\PageCacheManager;
use Shaarli\Updater\UpdaterUtils;

/**
 * Class BookmarksService
 *
 * This is the entry point to manipulate the bookmark DB.
 * It manipulates loads links from a file data store containing all bookmarks.
 *
 * It also triggers the legacy format (bookmarks as arrays) migration.
 */
class BookmarkFileService implements BookmarkServiceInterface
{
    /** @var Bookmark[] instance */
    protected $bookmarks;

    /** @var BookmarkIO instance */
    protected $bookmarksIO;

    /** @var BookmarkFilter */
    protected $bookmarkFilter;

    /** @var ConfigManager instance */
    protected $conf;

    /** @var History instance */
    protected $history;

    /** @var PageCacheManager instance */
    protected $pageCacheManager;

    /** @var bool true for logged in users. Default value to retrieve private bookmarks. */
    protected $isLoggedIn;

    /**
     * @inheritDoc
     */
    public function __construct(ConfigManager $conf, History $history, $isLoggedIn)
    {
        $this->conf = $conf;
        $this->history = $history;
        $this->pageCacheManager = new PageCacheManager($this->conf->get('resource.page_cache'));
        $this->bookmarksIO = new BookmarkIO($this->conf);
        $this->isLoggedIn = $isLoggedIn;

        if (!$this->isLoggedIn && $this->conf->get('privacy.hide_public_links', false)) {
            $this->bookmarks = [];
        } else {
            try {
                $this->bookmarks = $this->bookmarksIO->read();
            } catch (EmptyDataStoreException $e) {
                $this->bookmarks = new BookmarkArray();
                if ($isLoggedIn) {
                    $this->save();
                }
            }

            if (! $this->bookmarks instanceof BookmarkArray) {
                $this->migrate();
                exit(
                    'Your data store has been migrated, please reload the page.'. PHP_EOL .
                    'If this message keeps showing up, please delete data/updates.txt file.'
                );
            }
        }

        $this->bookmarkFilter = new BookmarkFilter($this->bookmarks);
    }

    /**
     * @inheritDoc
     */
    public function findByHash($hash)
    {
        $bookmark = $this->bookmarkFilter->filter(BookmarkFilter::$FILTER_HASH, $hash);
        // PHP 7.3 introduced array_key_first() to avoid this hack
        $first = reset($bookmark);
        if (! $this->isLoggedIn && $first->isPrivate()) {
            throw new Exception('Not authorized');
        }

        return $bookmark;
    }

    /**
     * @inheritDoc
     */
    public function findByUrl($url)
    {
        return $this->bookmarks->getByUrl($url);
    }

    /**
     * @inheritDoc
     */
    public function search($request = [], $visibility = null, $caseSensitive = false, $untaggedOnly = false)
    {
        if ($visibility === null) {
            $visibility = $this->isLoggedIn ? BookmarkFilter::$ALL : BookmarkFilter::$PUBLIC;
        }

        // Filter bookmark database according to parameters.
        $searchtags = isset($request['searchtags']) ? $request['searchtags'] : '';
        $searchterm = isset($request['searchterm']) ? $request['searchterm'] : '';

        return $this->bookmarkFilter->filter(
            BookmarkFilter::$FILTER_TAG | BookmarkFilter::$FILTER_TEXT,
            [$searchtags, $searchterm],
            $caseSensitive,
            $visibility,
            $untaggedOnly
        );
    }

    /**
     * @inheritDoc
     */
    public function get($id, $visibility = null)
    {
        if (! isset($this->bookmarks[$id])) {
            throw new BookmarkNotFoundException();
        }

        if ($visibility === null) {
            $visibility = $this->isLoggedIn ? BookmarkFilter::$ALL : BookmarkFilter::$PUBLIC;
        }

        $bookmark = $this->bookmarks[$id];
        if (($bookmark->isPrivate() && $visibility != 'all' && $visibility != 'private')
            || (! $bookmark->isPrivate() && $visibility != 'all' && $visibility != 'public')
        ) {
            throw new Exception('Unauthorized');
        }

        return $bookmark;
    }

    /**
     * @inheritDoc
     */
    public function set($bookmark, $save = true)
    {
        if ($this->isLoggedIn !== true) {
            throw new Exception(t('You\'re not authorized to alter the datastore'));
        }
        if (! $bookmark instanceof Bookmark) {
            throw new Exception(t('Provided data is invalid'));
        }
        if (! isset($this->bookmarks[$bookmark->getId()])) {
            throw new BookmarkNotFoundException();
        }
        $bookmark->validate();

        $bookmark->setUpdated(new \DateTime());
        $this->bookmarks[$bookmark->getId()] = $bookmark;
        if ($save === true) {
            $this->save();
            $this->history->updateLink($bookmark);
        }
        return $this->bookmarks[$bookmark->getId()];
    }

    /**
     * @inheritDoc
     */
    public function add($bookmark, $save = true)
    {
        if ($this->isLoggedIn !== true) {
            throw new Exception(t('You\'re not authorized to alter the datastore'));
        }
        if (! $bookmark instanceof Bookmark) {
            throw new Exception(t('Provided data is invalid'));
        }
        if (! empty($bookmark->getId())) {
            throw new Exception(t('This bookmarks already exists'));
        }
        $bookmark->setId($this->bookmarks->getNextId());
        $bookmark->validate();

        $this->bookmarks[$bookmark->getId()] = $bookmark;
        if ($save === true) {
            $this->save();
            $this->history->addLink($bookmark);
        }
        return $this->bookmarks[$bookmark->getId()];
    }

    /**
     * @inheritDoc
     */
    public function addOrSet($bookmark, $save = true)
    {
        if ($this->isLoggedIn !== true) {
            throw new Exception(t('You\'re not authorized to alter the datastore'));
        }
        if (! $bookmark instanceof Bookmark) {
            throw new Exception('Provided data is invalid');
        }
        if ($bookmark->getId() === null) {
            return $this->add($bookmark, $save);
        }
        return $this->set($bookmark, $save);
    }

    /**
     * @inheritDoc
     */
    public function remove($bookmark, $save = true)
    {
        if ($this->isLoggedIn !== true) {
            throw new Exception(t('You\'re not authorized to alter the datastore'));
        }
        if (! $bookmark instanceof Bookmark) {
            throw new Exception(t('Provided data is invalid'));
        }
        if (! isset($this->bookmarks[$bookmark->getId()])) {
            throw new BookmarkNotFoundException();
        }

        unset($this->bookmarks[$bookmark->getId()]);
        if ($save === true) {
            $this->save();
            $this->history->deleteLink($bookmark);
        }
    }

    /**
     * @inheritDoc
     */
    public function exists($id, $visibility = null)
    {
        if (! isset($this->bookmarks[$id])) {
            return false;
        }

        if ($visibility === null) {
            $visibility = $this->isLoggedIn ? 'all' : 'public';
        }

        $bookmark = $this->bookmarks[$id];
        if (($bookmark->isPrivate() && $visibility != 'all' && $visibility != 'private')
            || (! $bookmark->isPrivate() && $visibility != 'all' && $visibility != 'public')
        ) {
            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function count($visibility = null)
    {
        return count($this->search([], $visibility));
    }

    /**
     * @inheritDoc
     */
    public function save()
    {
        if (!$this->isLoggedIn) {
            // TODO: raise an Exception instead
            die('You are not authorized to change the database.');
        }
        $this->bookmarks->reorder();
        $this->bookmarksIO->write($this->bookmarks);
        $this->pageCacheManager->invalidateCaches();
    }

    /**
     * @inheritDoc
     */
    public function bookmarksCountPerTag($filteringTags = [], $visibility = null)
    {
        $bookmarks = $this->search(['searchtags' => $filteringTags], $visibility);
        $tags = [];
        $caseMapping = [];
        foreach ($bookmarks as $bookmark) {
            foreach ($bookmark->getTags() as $tag) {
                if (empty($tag)
                    || (! $this->isLoggedIn && startsWith($tag, '.'))
                    || $tag === BookmarkMarkdownFormatter::NO_MD_TAG
                ) {
                    continue;
                }

                // The first case found will be displayed.
                if (!isset($caseMapping[strtolower($tag)])) {
                    $caseMapping[strtolower($tag)] = $tag;
                    $tags[$caseMapping[strtolower($tag)]] = 0;
                }
                $tags[$caseMapping[strtolower($tag)]]++;
            }
        }

        /*
         * Formerly used arsort(), which doesn't define the sort behaviour for equal values.
         * Also, this function doesn't produce the same result between PHP 5.6 and 7.
         *
         * So we now use array_multisort() to sort tags by DESC occurrences,
         * then ASC alphabetically for equal values.
         *
         * @see https://github.com/shaarli/Shaarli/issues/1142
         */
        $keys = array_keys($tags);
        $tmpTags = array_combine($keys, $keys);
        array_multisort($tags, SORT_DESC, $tmpTags, SORT_ASC, $tags);
        return $tags;
    }

    /**
     * @inheritDoc
     */
    public function days()
    {
        $bookmarkDays = [];
        foreach ($this->search() as $bookmark) {
            $bookmarkDays[$bookmark->getCreated()->format('Ymd')] = 0;
        }
        $bookmarkDays = array_keys($bookmarkDays);
        sort($bookmarkDays);

        return $bookmarkDays;
    }

    /**
     * @inheritDoc
     */
    public function filterDay($request)
    {
        return $this->bookmarkFilter->filter(BookmarkFilter::$FILTER_DAY, $request);
    }

    /**
     * @inheritDoc
     */
    public function initialize()
    {
        $initializer = new BookmarkInitializer($this);
        $initializer->initialize();
    }

    /**
     * Handles migration to the new database format (BookmarksArray).
     */
    protected function migrate()
    {
        $bookmarkDb = new LegacyLinkDB(
            $this->conf->get('resource.datastore'),
            true,
            false
        );
        $updater = new LegacyUpdater(
            UpdaterUtils::read_updates_file($this->conf->get('resource.updates')),
            $bookmarkDb,
            $this->conf,
            true
        );
        $newUpdates = $updater->update();
        if (! empty($newUpdates)) {
            UpdaterUtils::write_updates_file(
                $this->conf->get('resource.updates'),
                $updater->getDoneUpdates()
            );
        }
    }
}
