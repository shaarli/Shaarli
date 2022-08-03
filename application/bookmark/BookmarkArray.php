<?php

declare(strict_types=1);

namespace Shaarli\Bookmark;

use Shaarli\Bookmark\Exception\InvalidBookmarkException;

/**
 * Class BookmarkArray
 *
 * Implementing ArrayAccess, this allows us to use the bookmark list
 * as an array and iterate over it.
 *
 * @package Shaarli\Bookmark
 */
class BookmarkArray implements \Iterator, \Countable, \ArrayAccess
{
    /**
     * @var Bookmark[]
     */
    protected $bookmarks;

    /**
     * @var array List of all bookmarks IDS mapped with their array offset.
     *            Map: id->offset.
     */
    protected $ids;

    /**
     * @var int Position in the $this->keys array (for the Iterator interface)
     */
    protected $position;

    /**
     * @var array List of offset keys (for the Iterator interface implementation)
     */
    protected $keys;

    /**
     * @var array List of all recorded URLs (key=url, value=bookmark offset)
     *            for fast reserve search (url-->bookmark offset)
     */
    protected $urls;

    public function __construct()
    {
        $this->ids = [];
        $this->bookmarks = [];
        $this->keys = [];
        $this->urls = [];
        $this->position = 0;
    }

    /**
     * Countable - Counts elements of an object
     *
     * @return int Number of bookmarks
     */
    public function count(): int
    {
        return count($this->bookmarks);
    }

    /**
     * ArrayAccess - Assigns a value to the specified offset
     *
     * @param int      $offset Bookmark ID
     * @param Bookmark $value  instance
     *
     * @throws InvalidBookmarkException
     */
    public function offsetSet($offset, $value): void
    {
        if (
            ! $value instanceof Bookmark
            || $value->getId() === null || empty($value->getUrl())
            || ($offset !== null && ! is_int($offset)) || ! is_int($value->getId())
            || $offset !== null && $offset !== $value->getId()
        ) {
            throw new InvalidBookmarkException($value);
        }

        // If the bookmark exists, we reuse the real offset, otherwise new entry
        if ($offset !== null) {
            $existing = $this->getBookmarkOffset($offset);
        } else {
            $existing = $this->getBookmarkOffset($value->getId());
        }

        if ($existing !== null) {
            $offset = $existing;
        } else {
            $offset = count($this->bookmarks);
        }

        $this->bookmarks[$offset] = $value;
        $this->urls[$value->getUrl()] = $offset;
        $this->ids[$value->getId()] = $offset;
    }

    /**
     * ArrayAccess - Whether or not an offset exists
     *
     * @param int $offset Bookmark ID
     *
     * @return bool true if it exists, false otherwise
     */
    public function offsetExists($offset): bool
    {
        return array_key_exists($this->getBookmarkOffset($offset), $this->bookmarks);
    }

    /**
     * ArrayAccess - Unsets an offset
     *
     * @param int $offset Bookmark ID
     */
    public function offsetUnset($offset): void
    {
        $realOffset = $this->getBookmarkOffset($offset);
        $url = $this->bookmarks[$realOffset]->getUrl();
        unset($this->urls[$url]);
        unset($this->ids[$offset]);
        unset($this->bookmarks[$realOffset]);
    }

    /**
     * ArrayAccess - Returns the value at specified offset
     *
     * @param int $offset Bookmark ID
     *
     * @return Bookmark|null The Bookmark if found, null otherwise
     */
    public function offsetGet($offset): ?Bookmark
    {
        $realOffset = $this->getBookmarkOffset($offset);
        return isset($this->bookmarks[$realOffset]) ? $this->bookmarks[$realOffset] : null;
    }

    /**
     * Iterator - Returns the current element
     *
     * @return Bookmark corresponding to the current position
     */
    public function current(): Bookmark
    {
        return $this[$this->keys[$this->position]];
    }

    /**
     * Iterator - Returns the key of the current element
     *
     * @return int Bookmark ID corresponding to the current position
     */
    public function key(): int
    {
        return $this->keys[$this->position];
    }

    /**
     * Iterator - Moves forward to next element
     */
    public function next(): void
    {
        ++$this->position;
    }

    /**
     * Iterator - Rewinds the Iterator to the first element
     *
     * Entries are sorted by date (latest first)
     */
    public function rewind(): void
    {
        $this->keys = array_keys($this->ids);
        $this->position = 0;
    }

    /**
     * Iterator - Checks if current position is valid
     *
     * @return bool true if the current Bookmark ID exists, false otherwise
     */
    public function valid(): bool
    {
        return isset($this->keys[$this->position]);
    }

    /**
     * Returns a bookmark offset in bookmarks array from its unique ID.
     *
     * @param int|null $id Persistent ID of a bookmark.
     *
     * @return int Real offset in local array, or null if doesn't exist.
     */
    protected function getBookmarkOffset(?int $id): ?int
    {
        if ($id !== null && isset($this->ids[$id])) {
            return $this->ids[$id];
        }
        return null;
    }

    /**
     * Return the next key for bookmark creation.
     * E.g. If the last ID is 597, the next will be 598.
     *
     * @return int next ID.
     */
    public function getNextId(): int
    {
        if (!empty($this->ids)) {
            return max(array_keys($this->ids)) + 1;
        }
        return 0;
    }

    /**
     * @param string $url
     *
     * @return Bookmark|null
     */
    public function getByUrl(string $url): ?Bookmark
    {
        if (
            ! empty($url)
            && isset($this->urls[$url])
            && isset($this->bookmarks[$this->urls[$url]])
        ) {
            return $this->bookmarks[$this->urls[$url]];
        }
        return null;
    }

    /**
     * Reorder links by creation date (newest first).
     *
     * Also update the urls and ids mapping arrays.
     *
     * @param string $order        ASC|DESC
     * @param bool   $ignoreSticky If set to true, sticky bookmarks won't be first
     */
    public function reorder(string $order = 'DESC', bool $ignoreSticky = false): void
    {
        $order = $order === 'ASC' ? -1 : 1;
        // Reorder array by dates.
        usort($this->bookmarks, function ($a, $b) use ($order, $ignoreSticky) {
            /** @var $a Bookmark */
            /** @var $b Bookmark */
            if (false === $ignoreSticky && $a->isSticky() !== $b->isSticky()) {
                return $a->isSticky() ? -1 : 1;
            }
            return $a->getCreated() < $b->getCreated() ? 1 * $order : -1 * $order;
        });

        $this->urls = [];
        $this->ids = [];
        foreach ($this->bookmarks as $key => $bookmark) {
            $this->urls[$bookmark->getUrl()] = $key;
            $this->ids[$bookmark->getId()] = $key;
        }
    }
}
