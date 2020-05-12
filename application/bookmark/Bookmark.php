<?php

namespace Shaarli\Bookmark;

use DateTime;
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
    const LINK_DATE_FORMAT = 'Ymd_His';

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

    /** @var string Thumbnail's URL - false if no thumbnail could be found */
    protected $thumbnail;

    /** @var bool Set to true if the bookmark is set as sticky */
    protected $sticky;

    /** @var DateTime Creation datetime */
    protected $created;

    /** @var DateTime Update datetime */
    protected $updated;

    /** @var bool True if the bookmark can only be seen while logged in */
    protected $private;

    /**
     * Initialize a link from array data. Especially useful to create a Bookmark from former link storage format.
     *
     * @param array $data
     *
     * @return $this
     */
    public function fromArray($data)
    {
        $this->id = $data['id'];
        $this->shortUrl = $data['shorturl'];
        $this->url = $data['url'];
        $this->title = $data['title'];
        $this->description = $data['description'];
        $this->thumbnail = isset($data['thumbnail']) ? $data['thumbnail'] : null;
        $this->sticky = isset($data['sticky']) ? $data['sticky'] : false;
        $this->created = $data['created'];
        if (is_array($data['tags'])) {
            $this->tags = $data['tags'];
        } else {
            $this->tags = preg_split('/\s+/', $data['tags'], -1, PREG_SPLIT_NO_EMPTY);
        }
        if (! empty($data['updated'])) {
            $this->updated = $data['updated'];
        }
        $this->private = $data['private'] ? true : false;

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
     * @throws InvalidBookmarkException
     */
    public function validate()
    {
        if ($this->id === null
            || ! is_int($this->id)
            || empty($this->shortUrl)
            || empty($this->created)
            || ! $this->created instanceof DateTime
        ) {
            throw new InvalidBookmarkException($this);
        }
        if (empty($this->url)) {
            $this->url = '?'. $this->shortUrl;
        }
        if (empty($this->title)) {
            $this->title = $this->url;
        }
    }

    /**
     * Set the Id.
     * If they're not already initialized, this function also set:
     *   - created: with the current datetime
     *   - shortUrl: with a generated small hash from the date and the given ID
     *
     * @param int $id
     *
     * @return Bookmark
     */
    public function setId($id)
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
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get the ShortUrl.
     *
     * @return string
     */
    public function getShortUrl()
    {
        return $this->shortUrl;
    }

    /**
     * Get the Url.
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Get the Title.
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Get the Description.
     *
     * @return string
     */
    public function getDescription()
    {
        return ! empty($this->description) ? $this->description : '';
    }

    /**
     * Get the Created.
     *
     * @return DateTime
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * Get the Updated.
     *
     * @return DateTime
     */
    public function getUpdated()
    {
        return $this->updated;
    }

    /**
     * Set the ShortUrl.
     *
     * @param string $shortUrl
     *
     * @return Bookmark
     */
    public function setShortUrl($shortUrl)
    {
        $this->shortUrl = $shortUrl;

        return $this;
    }

    /**
     * Set the Url.
     *
     * @param string $url
     * @param array  $allowedProtocols
     *
     * @return Bookmark
     */
    public function setUrl($url, $allowedProtocols = [])
    {
        $url = trim($url);
        if (! empty($url)) {
            $url = whitelist_protocols($url, $allowedProtocols);
        }
        $this->url = $url;

        return $this;
    }

    /**
     * Set the Title.
     *
     * @param string $title
     *
     * @return Bookmark
     */
    public function setTitle($title)
    {
        $this->title = trim($title);

        return $this;
    }

    /**
     * Set the Description.
     *
     * @param string $description
     *
     * @return Bookmark
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Set the Created.
     * Note: you shouldn't set this manually except for special cases (like bookmark import)
     *
     * @param DateTime $created
     *
     * @return Bookmark
     */
    public function setCreated($created)
    {
        $this->created = $created;

        return $this;
    }

    /**
     * Set the Updated.
     *
     * @param DateTime $updated
     *
     * @return Bookmark
     */
    public function setUpdated($updated)
    {
        $this->updated = $updated;

        return $this;
    }

    /**
     * Get the Private.
     *
     * @return bool
     */
    public function isPrivate()
    {
        return $this->private ? true : false;
    }

    /**
     * Set the Private.
     *
     * @param bool $private
     *
     * @return Bookmark
     */
    public function setPrivate($private)
    {
        $this->private = $private ? true : false;

        return $this;
    }

    /**
     * Get the Tags.
     *
     * @return array
     */
    public function getTags()
    {
        return is_array($this->tags) ? $this->tags : [];
    }

    /**
     * Set the Tags.
     *
     * @param array $tags
     *
     * @return Bookmark
     */
    public function setTags($tags)
    {
        $this->setTagsString(implode(' ', $tags));

        return $this;
    }

    /**
     * Get the Thumbnail.
     *
     * @return string|bool|null
     */
    public function getThumbnail()
    {
        return !$this->isNote() ? $this->thumbnail : false;
    }

    /**
     * Set the Thumbnail.
     *
     * @param string|bool $thumbnail
     *
     * @return Bookmark
     */
    public function setThumbnail($thumbnail)
    {
        $this->thumbnail = $thumbnail;

        return $this;
    }

    /**
     * Get the Sticky.
     *
     * @return bool
     */
    public function isSticky()
    {
        return $this->sticky ? true : false;
    }

    /**
     * Set the Sticky.
     *
     * @param bool $sticky
     *
     * @return Bookmark
     */
    public function setSticky($sticky)
    {
        $this->sticky = $sticky ? true : false;

        return $this;
    }

    /**
     * @return string Bookmark's tags as a string, separated by a space
     */
    public function getTagsString()
    {
        return implode(' ', $this->getTags());
    }

    /**
     * @return bool
     */
    public function isNote()
    {
        // We check empty value to get a valid result if the link has not been saved yet
        return empty($this->url) || $this->url[0] === '?';
    }

    /**
     * Set tags from a string.
     * Note:
     *   - tags must be separated whether by a space or a comma
     *   - multiple spaces will be removed
     *   - trailing dash in tags will be removed
     *
     * @param string $tags
     *
     * @return $this
     */
    public function setTagsString($tags)
    {
        // Remove first '-' char in tags.
        $tags = preg_replace('/(^| )\-/', '$1', $tags);
        // Explode all tags separted by spaces or commas
        $tags = preg_split('/[\s,]+/', $tags);
        // Remove eventual empty values
        $tags = array_values(array_filter($tags));

        $this->tags = $tags;

        return $this;
    }

    /**
     * Rename a tag in tags list.
     *
     * @param string $fromTag
     * @param string $toTag
     */
    public function renameTag($fromTag, $toTag)
    {
        if (($pos = array_search($fromTag, $this->tags)) !== false) {
            $this->tags[$pos] = trim($toTag);
        }
    }

    /**
     * Delete a tag from tags list.
     *
     * @param string $tag
     */
    public function deleteTag($tag)
    {
        if (($pos = array_search($tag, $this->tags)) !== false) {
            unset($this->tags[$pos]);
            $this->tags = array_values($this->tags);
        }
    }
}
