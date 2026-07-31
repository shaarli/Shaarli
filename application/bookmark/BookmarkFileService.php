<?php

declare(strict_types=1);

namespace Shaarli\Bookmark;

use DateTime;
use Exception;
use malkusch\lock\mutex\Mutex;
use Shaarli\Bookmark\Exception\BookmarkNotFoundException;
use Shaarli\Bookmark\Exception\DatastoreNotInitializedException;
use Shaarli\Bookmark\Exception\EmptyDataStoreException;
use Shaarli\Config\ConfigManager;
use Shaarli\Formatter\BookmarkMarkdownFormatter;
use Shaarli\History;
use Shaarli\Legacy\LegacyLinkDB;
use Shaarli\Legacy\LegacyUpdater;
use Shaarli\Plugin\PluginManager;
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

    /** @var PluginManager */
    protected $pluginManager;

    /** @var History instance */
    protected $history;

    /** @var PageCacheManager instance */
    protected $pageCacheManager;

    /** @var bool true for logged in users. Default value to retrieve private bookmarks. */
    protected $isLoggedIn;

    /** @var Mutex */
    protected $mutex;

    /**
     * @inheritDoc
     */
    public function __construct(
        ConfigManager $conf,
        PluginManager $pluginManager,
        History $history,
        Mutex $mutex,
        bool $isLoggedIn
    ) {
        $this->conf = $conf;
        $this->history = $history;
        $this->mutex = $mutex;
        $this->pageCacheManager = new PageCacheManager($this->conf->get('resource.page_cache'), $isLoggedIn);
        $this->bookmarksIO = new BookmarkIO($this->conf, $this->mutex);
        $this->isLoggedIn = $isLoggedIn;

        if (!$this->isLoggedIn && $this->conf->get('privacy.hide_public_links', false)) {
            $this->bookmarks = new BookmarkArray();
        } else {
            try {
                $this->bookmarks = $this->bookmarksIO->read();
            } catch (EmptyDataStoreException | DatastoreNotInitializedException $e) {
                $this->bookmarks = new BookmarkArray();

                if ($this->isLoggedIn) {
                    // Datastore file does not exists, we initialize it with default bookmarks.
                    if ($e instanceof DatastoreNotInitializedException) {
                        $this->initialize();
                    } else {
                        $this->save();
                    }
                }
            }

            if (! $this->bookmarks instanceof BookmarkArray) {
                $this->migrate();
                exit(
                    'Your data store has been migrated, please reload the page.' . PHP_EOL .
                    'If this message keeps showing up, please delete data/updates.txt file.'
                );
            }
        }

        $this->pluginManager = $pluginManager;
        $this->bookmarkFilter = new BookmarkFilter($this->bookmarks, $this->conf, $this->pluginManager);
    }

    /**
     * @inheritDoc
     */
    public function findByHash(string $hash, ?string $privateKey = null): Bookmark
    {
        $bookmark = $this->bookmarkFilter->filter(BookmarkFilter::$FILTER_HASH, $hash);
        // PHP 7.3 introduced array_key_first() to avoid this hack
        $first = reset($bookmark);
        if (
            !$this->isLoggedIn
            && $first->isPrivate()
            && (empty($privateKey) || $privateKey !== $first->getAdditionalContentEntry('private_key'))
        ) {
            throw new BookmarkNotFoundException();
        }

        return $first;
    }

    /**
     * @inheritDoc
     */
    public function findByUrl(string $url): ?Bookmark
    {
        return $this->bookmarks->getByUrl($url);
    }

    /**
     * @inheritDoc
     */
    public function search(
        array $request = [],
        ?string $visibility = null,
        bool $caseSensitive = false,
        bool $untaggedOnly = false,
        bool $ignoreSticky = false,
        array $pagination = []
    ): SearchResult {
        if ($visibility === null) {
            $visibility = $this->isLoggedIn ? BookmarkFilter::$ALL : BookmarkFilter::$PUBLIC;
        }

        // Filter bookmark database according to parameters.
        $searchTags = isset($request['searchtags']) ? $request['searchtags'] : '';
        $searchTerm = isset($request['searchterm']) ? $request['searchterm'] : '';

        if ($ignoreSticky) {
            $this->bookmarks->reorder('DESC', true);
        }

        $bookmarks = $this->bookmarkFilter->filter(
            BookmarkFilter::$FILTER_TAG | BookmarkFilter::$FILTER_TEXT,
            [$searchTags, $searchTerm],
            $caseSensitive,
            $visibility,
            $untaggedOnly
        );

        return SearchResult::getSearchResult(
            $bookmarks,
            $pagination['offset'] ?? 0,
            $pagination['limit'] ?? null,
            $pagination['allowOutOfBounds'] ?? false
        );
    }

    /**
     * @inheritDoc
     */
    public function get(int $id, ?string $visibility = null): Bookmark
    {
        if (! isset($this->bookmarks[$id])) {
            throw new BookmarkNotFoundException();
        }

        if ($visibility === null) {
            $visibility = $this->isLoggedIn ? BookmarkFilter::$ALL : BookmarkFilter::$PUBLIC;
        }

        $bookmark = $this->bookmarks[$id];
        if (
            ($bookmark->isPrivate() && $visibility != 'all' && $visibility != 'private')
            || (! $bookmark->isPrivate() && $visibility != 'all' && $visibility != 'public')
        ) {
            throw new Exception('Unauthorized');
        }

        return $bookmark;
    }

    /**
     * @inheritDoc
     */
    public function set(Bookmark $bookmark, bool $save = true): Bookmark
    {
        if (true !== $this->isLoggedIn) {
            throw new Exception(t('You\'re not authorized to alter the datastore'));
        }
        if (! isset($this->bookmarks[$bookmark->getId()])) {
            throw new BookmarkNotFoundException();
        }
        $bookmark->validate();

        $bookmark->setUpdated(new DateTime());
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
    public function add(Bookmark $bookmark, bool $save = true): Bookmark
    {
        if (true !== $this->isLoggedIn) {
            throw new Exception(t('You\'re not authorized to alter the datastore'));
        }
        if (!empty($bookmark->getId())) {
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
    public function addOrSet(Bookmark $bookmark, bool $save = true): Bookmark
    {
        if (true !== $this->isLoggedIn) {
            throw new Exception(t('You\'re not authorized to alter the datastore'));
        }
        if ($bookmark->getId() === null) {
            return $this->add($bookmark, $save);
        }
        return $this->set($bookmark, $save);
    }

    /**
     * @inheritDoc
     */
    public function remove(Bookmark $bookmark, bool $save = true): void
    {
        if (true !== $this->isLoggedIn) {
            throw new Exception(t('You\'re not authorized to alter the datastore'));
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
    public function exists(int $id, ?string $visibility = null): bool
    {
        if (! isset($this->bookmarks[$id])) {
            return false;
        }

        if ($visibility === null) {
            $visibility = $this->isLoggedIn ? 'all' : 'public';
        }

        $bookmark = $this->bookmarks[$id];
        if (
            ($bookmark->isPrivate() && $visibility != 'all' && $visibility != 'private')
            || (! $bookmark->isPrivate() && $visibility != 'all' && $visibility != 'public')
        ) {
            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function count(?string $visibility = null): int
    {
        return $this->search([], $visibility)->getResultCount();
    }

    /**
     * @inheritDoc
     */
    public function save(): void
    {
        if (true !== $this->isLoggedIn) {
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
    public function bookmarksCountPerTag(array $filteringTags = [], ?string $visibility = null): array
    {
        $searchResult = $this->search(['searchtags' => $filteringTags], $visibility);
        $tags = [];
        $caseMapping = [];
        foreach ($searchResult->getBookmarks() as $bookmark) {
            foreach ($bookmark->getTags() as $tag) {
                if (
                    empty($tag)
                    || (! $this->isLoggedIn && startsWith($tag, '.'))
                    || $tag === BookmarkMarkdownFormatter::NO_MD_TAG
                    || in_array($tag, $filteringTags, true)
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
    public function findByDate(
        \DateTimeInterface $from,
        \DateTimeInterface $to,
        ?\DateTimeInterface &$previous,
        ?\DateTimeInterface &$next
    ): array {
        $out = [];
        $previous = null;
        $next = null;

        foreach ($this->search([], null, false, false, true)->getBookmarks() as $bookmark) {
            if ($to < $bookmark->getCreated()) {
                $next = $bookmark->getCreated();
            } elseif ($from < $bookmark->getCreated() && $to > $bookmark->getCreated()) {
                $out[] = $bookmark;
            } else {
                if ($previous !== null) {
                    break;
                }
                $previous = $bookmark->getCreated();
            }
        }

        return $out;
    }

    /**
     * @inheritDoc
     */
    public function getLatest(): ?Bookmark
    {
        foreach ($this->search([], null, false, false, true)->getBookmarks() as $bookmark) {
            return $bookmark;
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function initialize(): void
    {
        $initializer = new BookmarkInitializer($this);
        $initializer->initialize();

        if (true === $this->isLoggedIn) {
            $this->save();
        }
    }

    /**
     * Handles migration to the new database format (BookmarksArray).
     */
    protected function migrate(): void
    {
        $bookmarkDb = new LegacyLinkDB(
            $this->conf->get('resource.datastore'),
            true,
            false
        );
        $updater = new LegacyUpdater(
            UpdaterUtils::readUpdatesFile($this->conf->get('resource.updates')),
            $bookmarkDb,
            $this->conf,
            true
        );
        $newUpdates = $updater->update();
        if (! empty($newUpdates)) {
            UpdaterUtils::writeUpdatesFile(
                $this->conf->get('resource.updates'),
                $updater->getDoneUpdates()
            );
        }
    }
}
