<?php

declare(strict_types=1);

namespace Shaarli\Bookmark;

use DateTime;
use DateTimeInterface;
use Shaarli\Bookmark\Exception\InvalidBookmarkException;

/**
 * Class Bookmark
 *
 * This class represent a single Bookmark with all its attributes.
 * Every bookmark should manipulated using this, before being formatted.
 *
 * @package Shaarli\Bookmark
 */
class Bookmark
{
    /** @var string Date format used in string (former ID format) */
    public const LINK_DATE_FORMAT = 'Ymd_His';

    /** @var int Bookmark ID */
    protected $id;

    /** @var string Permalink identifier */
    protected $shortUrl;

    /** @var string Bookmark's URL - $shortUrl prefixed with `?` for notes */
    protected $url;

    /** @var string Bookmark's title */
    protected $title;

    /** @var string Raw bookmark's description */
    protected $description;

    /** @var array List of bookmark's tags */
    protected $tags;

    /** @var string|bool|null Thumbnail's URL - initialized at null, false if no thumbnail could be found */
    protected $thumbnail;

    /** @var bool Set to true if the bookmark is set as sticky */
    protected $sticky;

    /** @var DateTimeInterface Creation datetime */
    protected $created;

    /** @var DateTimeInterface datetime */
    protected $updated;

    /** @var bool True if the bookmark can only be seen while logged in */
    protected $private;

    /** @var mixed[] Available to store any additional content for a bookmark. Currently used for search highlight. */
    protected $additionalContent = [];

    /**
     * Initialize a link from array data. Especially useful to create a Bookmark from former link storage format.
     *
     * @param array  $data
     * @param string $tagsSeparator Tags separator loaded from the config file.
     *                              This is a context data, and it should *never* be stored in the Bookmark object.
     *
     * @return $this
     */
    public function fromArray(array $data, string $tagsSeparator = ' '): Bookmark
    {
        $this->id = $data['id'] ?? null;
        $this->shortUrl = $data['shorturl'] ?? null;
        $this->url = $data['url'] ?? null;
        $this->title = $data['title'] ?? null;
        $this->description = $data['description'] ?? null;
        $this->thumbnail = $data['thumbnail'] ?? null;
        $this->sticky = $data['sticky'] ?? false;
        $this->created = $data['created'] ?? null;
        if (is_array($data['tags'])) {
            $this->tags = $data['tags'];
        } else {
            $this->tags = tags_str2array($data['tags'] ?? '', $tagsSeparator);
        }
        if (! empty($data['updated'])) {
            $this->updated = $data['updated'];
        }
        $this->private = ($data['private'] ?? false) ? true : false;
        $this->additionalContent = $data['additional_content'] ?? [];

        return $this;
    }

    /**
     * Make sure that the current instance of Bookmark is valid and can be saved into the data store.
     * A valid link requires:
     *   - an integer ID
     *   - a short URL (for permalinks)
     *   - a creation date
     *
     * This function also initialize optional empty fields:
     *   - the URL with the permalink
     *   - the title with the URL
     *
     * Also make sure that we do not save search highlights in the datastore.
     *
     * @throws InvalidBookmarkException
     */
    public function validate(): void
    {
        if (
            $this->id === null
            || ! is_int($this->id)
            || empty($this->shortUrl)
            || empty($this->created)
        ) {
            throw new InvalidBookmarkException($this);
        }
        if (empty($this->url)) {
            $this->url = '/shaare/' . $this->shortUrl;
        }
        if (empty($this->title)) {
            $this->title = $this->url;
        }
        if (array_key_exists('search_highlight', $this->additionalContent)) {
            unset($this->additionalContent['search_highlight']);
        }
    }

    /**
     * Set the Id.
     * If they're not already initialized, this function also set:
     *   - created: with the current datetime
     *   - shortUrl: with a generated small hash from the date and the given ID
     *
     * @param int|null $id
     *
     * @return Bookmark
     */
    public function setId(?int $id): Bookmark
    {
        $this->id = $id;
        if (empty($this->created)) {
            $this->created = new DateTime();
        }
        if (empty($this->shortUrl)) {
            $this->shortUrl = link_small_hash($this->created, $this->id);
        }

        return $this;
    }

    /**
     * Get the Id.
     *
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Get the ShortUrl.
     *
     * @return string|null
     */
    public function getShortUrl(): ?string
    {
        return $this->shortUrl;
    }

    /**
     * Get the Url.
     *
     * @return string|null
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }

    /**
     * Get the Title.
     *
     * @return string
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * Get the Description.
     *
     * @return string
     */
    public function getDescription(): string
    {
        return ! empty($this->description) ? $this->description : '';
    }

    /**
     * Get the Created.
     *
     * @return DateTimeInterface
     */
    public function getCreated(): ?DateTimeInterface
    {
        return $this->created;
    }

    /**
     * Get the Updated.
     *
     * @return DateTimeInterface
     */
    public function getUpdated(): ?DateTimeInterface
    {
        return $this->updated;
    }

    /**
     * Set the ShortUrl.
     *
     * @param string|null $shortUrl
     *
     * @return Bookmark
     */
    public function setShortUrl(?string $shortUrl): Bookmark
    {
        $this->shortUrl = $shortUrl;

        return $this;
    }

    /**
     * Set the Url.
     *
     * @param string|null $url
     * @param string[]    $allowedProtocols
     *
     * @return Bookmark
     */
    public function setUrl(?string $url, array $allowedProtocols = []): Bookmark
    {
        $url = $url !== null ? trim($url) : '';
        if (! empty($url)) {
            $url = whitelist_protocols($url, $allowedProtocols);
        }
        $this->url = $url;

        return $this;
    }

    /**
     * Set the Title.
     *
     * @param string|null $title
     *
     * @return Bookmark
     */
    public function setTitle(?string $title): Bookmark
    {
        $this->title = $title !== null ? trim($title) : '';

        return $this;
    }

    /**
     * Set the Description.
     *
     * @param string|null $description
     *
     * @return Bookmark
     */
    public function setDescription(?string $description): Bookmark
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Set the Created.
     * Note: you shouldn't set this manually except for special cases (like bookmark import)
     *
     * @param DateTimeInterface|null $created
     *
     * @return Bookmark
     */
    public function setCreated(?DateTimeInterface $created): Bookmark
    {
        $this->created = $created;

        return $this;
    }

    /**
     * Set the Updated.
     *
     * @param DateTimeInterface|null $updated
     *
     * @return Bookmark
     */
    public function setUpdated(?DateTimeInterface $updated): Bookmark
    {
        $this->updated = $updated;

        return $this;
    }

    /**
     * Get the Private.
     *
     * @return bool
     */
    public function isPrivate(): bool
    {
        return $this->private ? true : false;
    }

    /**
     * Set the Private.
     *
     * @param bool|null $private
     *
     * @return Bookmark
     */
    public function setPrivate(?bool $private): Bookmark
    {
        $this->private = $private ? true : false;

        return $this;
    }

    /**
     * Get the Tags.
     *
     * @return string[]
     */
    public function getTags(): array
    {
        return is_array($this->tags) ? $this->tags : [];
    }

    /**
     * Set the Tags.
     *
     * @param string[]|null $tags
     *
     * @return Bookmark
     */
    public function setTags(?array $tags): Bookmark
    {
        $this->tags = array_map(
            function (string $tag): string {
                return $tag[0] === '-' ? substr($tag, 1) : $tag;
            },
            tags_filter($tags, ' ')
        );

        return $this;
    }

    /**
     * Get the Thumbnail.
     *
     * @return string|bool|null Thumbnail's URL - initialized at null, false if no thumbnail could be found
     */
    public function getThumbnail()
    {
        return !$this->isNote() ? $this->thumbnail : false;
    }

    /**
     * Set the Thumbnail.
     *
     * @param string|bool|null $thumbnail Thumbnail's URL - false if no thumbnail could be found
     *
     * @return Bookmark
     */
    public function setThumbnail($thumbnail): Bookmark
    {
        $this->thumbnail = $thumbnail;

        return $this;
    }

    /**
     * Return true if:
     *   - the bookmark's thumbnail is not already set to false (= not found)
     *   - it's not a note
     *   - it's an HTTP(S) link
     *   - the thumbnail has not yet be retrieved (null) or its associated cache file doesn't exist anymore
     *
     * @return bool True if the bookmark's thumbnail needs to be retrieved.
     */
    public function shouldUpdateThumbnail(): bool
    {
        return $this->thumbnail !== false
            && !$this->isNote()
            && startsWith(strtolower($this->url), 'http')
            && (null === $this->thumbnail || !is_file($this->thumbnail))
        ;
    }

    /**
     * Get the Sticky.
     *
     * @return bool
     */
    public function isSticky(): bool
    {
        return $this->sticky ? true : false;
    }

    /**
     * Set the Sticky.
     *
     * @param bool|null $sticky
     *
     * @return Bookmark
     */
    public function setSticky(?bool $sticky): Bookmark
    {
        $this->sticky = $sticky ? true : false;

        return $this;
    }

    /**
     * @param string $separator Tags separator loaded from the config file.
     *
     * @return string Bookmark's tags as a string, separated by a separator
     */
    public function getTagsString(string $separator = ' '): string
    {
        return tags_array2str($this->getTags(), $separator);
    }

    /**
     * @return bool
     */
    public function isNote(): bool
    {
        // We check empty value to get a valid result if the link has not been saved yet
        return empty($this->url) || startsWith($this->url, '/shaare/') || $this->url[0] === '?';
    }

    /**
     * Set tags from a string.
     * Note:
     *   - tags must be separated whether by a space or a comma
     *   - multiple spaces will be removed
     *   - trailing dash in tags will be removed
     *
     * @param string|null $tags
     * @param string      $separator Tags separator loaded from the config file.
     *
     * @return $this
     */
    public function setTagsString(?string $tags, string $separator = ' '): Bookmark
    {
        $this->setTags(tags_str2array($tags, $separator));

        return $this;
    }

    /**
     * Get entire additionalContent array.
     *
     * @return mixed[]
     */
    public function getAdditionalContent(): array
    {
        return $this->additionalContent;
    }

    /**
     * Set a single entry in additionalContent, by key.
     *
     * @param string     $key
     * @param mixed|null $value Any type of value can be set.
     *
     * @return $this
     */
    public function setAdditionalContentEntry(string $key, $value): self
    {
        $this->additionalContent[$key] = $value;

        return $this;
    }

    /**
     * Get a single entry in additionalContent, by key.
     *
     * @param string $key
     * @param mixed|null $default
     *
     * @return mixed|null can be any type or even null.
     */
    public function getAdditionalContentEntry(string $key, $default = null)
    {
        return array_key_exists($key, $this->additionalContent) ? $this->additionalContent[$key] : $default;
    }

    /**
     * Rename a tag in tags list.
     *
     * @param string $fromTag
     * @param string $toTag
     */
    public function renameTag(string $fromTag, string $toTag): void
    {
        if (($pos = array_search($fromTag, $this->tags ?? [])) !== false) {
            $this->tags[$pos] = trim($toTag);
        }
    }

    /**
     * Add a tag in tags list.
     *
     * @param string $tag
     */
    public function addTag(string $tag): self
    {
        return $this->setTags(array_unique(array_merge($this->getTags(), [$tag])));
    }

    /**
     * Delete a tag from tags list.
     *
     * @param string $tag
     */
    public function deleteTag(string $tag): void
    {
        while (($pos = array_search($tag, $this->tags ?? [])) !== false) {
            unset($this->tags[$pos]);
            $this->tags = array_values($this->tags);
        }
    }
}
