<?php

namespace Shaarli\Tests\Utils;

use DateTime;
use Shaarli\Bookmark\Bookmark;
use Shaarli\Bookmark\BookmarkArray;
use Shaarli\Helper\FileUtils;

/**
 * Populates a reference datastore to test Bookmark
 */
class ReferenceLinkDB
{
    public static $NB_LINKS_TOTAL = 11;

    protected $bookmarks = [];
    protected $publicCount = 0;
    protected $privateCount = 0;

    protected $isLegacy;

    /**
     * Populates the test DB with reference data
     *
     * @param bool $isLegacy Use links as array instead of Bookmark object
     */
    public function __construct($isLegacy = false)
    {
        $this->isLegacy = $isLegacy;
        if (! $this->isLegacy) {
            $this->bookmarks = new BookmarkArray();
        }
        $this->addLink(
            11,
            'Pined older',
            '/shaare/PCRizQ',
            'This is an older pinned link',
            0,
            DateTime::createFromFormat(Bookmark::LINK_DATE_FORMAT, '20100309_101010'),
            '',
            null,
            'PCRizQ',
            true
        );

        $this->addLink(
            10,
            'Pined',
            '/shaare/0gCTjQ',
            'This is a pinned link',
            0,
            DateTime::createFromFormat(Bookmark::LINK_DATE_FORMAT, '20121207_152312'),
            '',
            null,
            '0gCTjQ',
            true
        );

        $this->addLink(
            41,
            'Link title: @website',
            '/shaare/WDWyig',
            'Stallman has a beard and is part of the Free Software Foundation (or not). Seriously, read this. #hashtag',
            0,
            DateTime::createFromFormat(Bookmark::LINK_DATE_FORMAT, '20150310_114651'),
            'sTuff',
            null,
            'WDWyig'
        );

        $this->addLink(
            42,
            'Note: I have a big ID but an old date',
            '/shaare/WDWyig',
            'Used to test bookmarks reordering.',
            0,
            DateTime::createFromFormat(Bookmark::LINK_DATE_FORMAT, '20100310_101010'),
            'ut'
        );

        $this->addLink(
            9,
            'PSR-2: Coding Style Guide',
            'http://www.php-fig.org/psr/psr-2/',
            'This guide extends and expands on PSR-1, the basic coding standard.',
            0,
            DateTime::createFromFormat(Bookmark::LINK_DATE_FORMAT, '20121206_152312'),
            'coding-style standards quality assurance'
        );

        $this->addLink(
            8,
            'Free as in Freedom 2.0 @website',
            'https://static.fsf.org/nosvn/faif-2.0.pdf',
            'Richard Stallman and the Free Software Revolution. Read this. #hashtag',
            0,
            DateTime::createFromFormat(Bookmark::LINK_DATE_FORMAT, '20150310_114633'),
            'free gnu software stallman -exclude stuff hashtag',
            DateTime::createFromFormat(Bookmark::LINK_DATE_FORMAT, '20160803_093033')
        );

        $this->addLink(
            7,
            'MediaGoblin',
            'http://mediagoblin.org/',
            'A free software media publishing platform #hashtagOther',
            0,
            DateTime::createFromFormat(Bookmark::LINK_DATE_FORMAT, '20130614_184135'),
            'gnu media web .hidden hashtag',
            DateTime::createFromFormat(Bookmark::LINK_DATE_FORMAT, '20130615_184230'),
            'IuWvgA'
        );

        $this->addLink(
            6,
            'w3c-markup-validator',
            'https://dvcs.w3.org/hg/markup-validator/summary',
            'Mercurial repository for the W3C Validator #private',
            1,
            DateTime::createFromFormat(Bookmark::LINK_DATE_FORMAT, '20141125_084734'),
            'css html w3c web Mercurial'
        );

        $this->addLink(
            4,
            'UserFriendly - Web Designer',
            'http://ars.userfriendly.org/cartoons/?id=20121206',
            'Naming conventions... #private',
            0,
            DateTime::createFromFormat(Bookmark::LINK_DATE_FORMAT, '20121206_142300'),
            'dev cartoon web'
        );

        $this->addLink(
            1,
            'UserFriendly - Samba',
            'http://ars.userfriendly.org/cartoons/?id=20010306',
            'Tropical printing',
            0,
            DateTime::createFromFormat(Bookmark::LINK_DATE_FORMAT, '20121206_172539'),
            'samba cartoon web'
        );

        $this->addLink(
            0,
            'Geek and Poke',
            'http://geek-and-poke.com/',
            '',
            1,
            DateTime::createFromFormat(Bookmark::LINK_DATE_FORMAT, '20121206_182539'),
            'dev cartoon tag1  tag2   tag3  tag4   '
        );
    }

    /**
     * Adds a new link
     */
    protected function addLink(
        $id,
        $title,
        $url,
        $description,
        $private,
        $date,
        $tags,
        $updated = '',
        $shorturl = '',
        $pinned = false
    ) {
        $link = [
            'id' => $id,
            'title' => $title,
            'url' => $url,
            'description' => $description,
            'private' => $private,
            'tags' => $tags,
            'created' => $date,
            'updated' => $updated,
            'shorturl' => $shorturl ? $shorturl : smallHash($date->format(Bookmark::LINK_DATE_FORMAT) . $id),
            'sticky' => $pinned
        ];
        if (! $this->isLegacy) {
            $bookmark = new Bookmark();
            $this->bookmarks[$id] = $bookmark->fromArray($link);
        } else {
            $this->bookmarks[$id] = $link;
        }

        if ($private) {
            $this->privateCount++;
            return;
        }
        $this->publicCount++;
    }

    /**
     * Writes data to the datastore
     */
    public function write($filename)
    {
        $this->reorder();
        if (!file_exists(dirname($filename))) {
            mkdir(dirname($filename), 0755, true);
        }
        FileUtils::writeFlatDB($filename, $this->bookmarks);
    }

    /**
     * Reorder links by creation date (newest first).
     *
     * @param string $order ASC|DESC
     */
    public function reorder($order = 'DESC')
    {
        if (! $this->isLegacy) {
            $this->bookmarks->reorder($order);
        } else {
            $order = $order === 'ASC' ? -1 : 1;
            // backward compatibility: ignore reorder if the the `created` field doesn't exist
            if (! isset(array_values($this->bookmarks)[0]['created'])) {
                return;
            }

            usort($this->bookmarks, function ($a, $b) use ($order) {
                if (isset($a['sticky']) && isset($b['sticky']) && $a['sticky'] !== $b['sticky']) {
                    return $a['sticky'] ? -1 : 1;
                }

                return $a['created'] < $b['created'] ? 1 * $order : -1 * $order;
            });
        }
    }

    /**
     * Returns the number of bookmarks in the reference data
     */
    public function countLinks()
    {
        return $this->publicCount + $this->privateCount;
    }

    /**
     * Returns the number of public bookmarks in the reference data
     */
    public function countPublicLinks()
    {
        return $this->publicCount;
    }

    /**
     * Returns the number of private bookmarks in the reference data
     */
    public function countPrivateLinks()
    {
        return $this->privateCount;
    }

    /**
     * Returns the number of bookmarks without tag
     */
    public function countUntaggedLinks()
    {
        $cpt = 0;
        foreach ($this->bookmarks as $link) {
            if (! $this->isLegacy) {
                if (empty($link->getTags())) {
                    ++$cpt;
                }
            } else {
                if (empty($link['tags'])) {
                    ++$cpt;
                }
            }
        }
        return $cpt;
    }

    public function getLinks()
    {
        $this->reorder();
        return $this->bookmarks;
    }

    /**
     * Setter to override link creation.
     *
     * @param array $links List of bookmarks.
     */
    public function setLinks($links)
    {
        $this->bookmarks = $links;
    }
}
