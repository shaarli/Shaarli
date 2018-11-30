<?php
/**
 * Populates a reference datastore to test LinkDB
 */
class ReferenceLinkDB
{
    public static $NB_LINKS_TOTAL = 11;

    private $_links = array();
    private $_publicCount = 0;
    private $_privateCount = 0;

    /**
     * Populates the test DB with reference data
     */
    public function __construct()
    {
        $this->addLink(
            11,
            'Pined older',
            '?PCRizQ',
            'This is an older pinned link',
            0,
            DateTime::createFromFormat(LinkDB::LINK_DATE_FORMAT, '20100309_101010'),
            '',
            null,
            'PCRizQ',
            true
        );

        $this->addLink(
            10,
            'Pined',
            '?0gCTjQ',
            'This is a pinned link',
            0,
            DateTime::createFromFormat(LinkDB::LINK_DATE_FORMAT, '20121207_152312'),
            '',
            null,
            '0gCTjQ',
            true
        );

        $this->addLink(
            41,
            'Link title: @website',
            '?WDWyig',
            'Stallman has a beard and is part of the Free Software Foundation (or not). Seriously, read this. #hashtag',
            0,
            DateTime::createFromFormat(LinkDB::LINK_DATE_FORMAT, '20150310_114651'),
            'sTuff',
            null,
            'WDWyig'
        );

        $this->addLink(
            42,
            'Note: I have a big ID but an old date',
            '?WDWyig',
            'Used to test links reordering.',
            0,
            DateTime::createFromFormat(LinkDB::LINK_DATE_FORMAT, '20100310_101010'),
            'ut'
        );

        $this->addLink(
            9,
            'PSR-2: Coding Style Guide',
            'http://www.php-fig.org/psr/psr-2/',
            'This guide extends and expands on PSR-1, the basic coding standard.',
            0,
            DateTime::createFromFormat(LinkDB::LINK_DATE_FORMAT, '20121206_152312'),
            ''
        );

        $this->addLink(
            8,
            'Free as in Freedom 2.0 @website',
            'https://static.fsf.org/nosvn/faif-2.0.pdf',
            'Richard Stallman and the Free Software Revolution. Read this. #hashtag',
            0,
            DateTime::createFromFormat(LinkDB::LINK_DATE_FORMAT, '20150310_114633'),
            'free gnu software stallman -exclude stuff hashtag',
            DateTime::createFromFormat(LinkDB::LINK_DATE_FORMAT, '20160803_093033')
        );

        $this->addLink(
            7,
            'MediaGoblin',
            'http://mediagoblin.org/',
            'A free software media publishing platform #hashtagOther',
            0,
            DateTime::createFromFormat(LinkDB::LINK_DATE_FORMAT, '20130614_184135'),
            'gnu media web .hidden hashtag',
            DateTime::createFromFormat(LinkDB::LINK_DATE_FORMAT, '20130615_184230'),
            'IuWvgA'
        );

        $this->addLink(
            6,
            'w3c-markup-validator',
            'https://dvcs.w3.org/hg/markup-validator/summary',
            'Mercurial repository for the W3C Validator #private',
            1,
            DateTime::createFromFormat(LinkDB::LINK_DATE_FORMAT, '20141125_084734'),
            'css html w3c web Mercurial'
        );

        $this->addLink(
            4,
            'UserFriendly - Web Designer',
            'http://ars.userfriendly.org/cartoons/?id=20121206',
            'Naming conventions... #private',
            0,
            DateTime::createFromFormat(LinkDB::LINK_DATE_FORMAT, '20121206_142300'),
            'dev cartoon web'
        );

        $this->addLink(
            1,
            'UserFriendly - Samba',
            'http://ars.userfriendly.org/cartoons/?id=20010306',
            'Tropical printing',
            0,
            DateTime::createFromFormat(LinkDB::LINK_DATE_FORMAT, '20121206_172539'),
            'samba cartoon web'
        );

        $this->addLink(
            0,
            'Geek and Poke',
            'http://geek-and-poke.com/',
            '',
            1,
            DateTime::createFromFormat(LinkDB::LINK_DATE_FORMAT, '20121206_182539'),
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
        $pinned = false)
    {
        $link = array(
            'id' => $id,
            'title' => $title,
            'url' => $url,
            'description' => $description,
            'private' => $private,
            'tags' => $tags,
            'created' => $date,
            'updated' => $updated,
            'shorturl' => $shorturl ? $shorturl : smallHash($date->format(LinkDB::LINK_DATE_FORMAT) . $id),
            'sticky' => $pinned
        );
        $this->_links[$id] = $link;

        if ($private) {
            $this->_privateCount++;
            return;
        }
        $this->_publicCount++;
    }

    /**
     * Writes data to the datastore
     */
    public function write($filename)
    {
        $this->reorder();
        file_put_contents(
            $filename,
            '<?php /* '.base64_encode(gzdeflate(serialize($this->_links))).' */ ?>'
        );
    }

    /**
     * Reorder links by creation date (newest first).
     *
     * Also update the urls and ids mapping arrays.
     *
     * @param string $order ASC|DESC
     */
    public function reorder($order = 'DESC')
    {
        // backward compatibility: ignore reorder if the the `created` field doesn't exist
        if (! isset(array_values($this->_links)[0]['created'])) {
            return;
        }

        $order = $order === 'ASC' ? -1 : 1;
        // Reorder array by dates.
        usort($this->_links, function($a, $b) use ($order) {
            if (isset($a['sticky']) && isset($b['sticky']) && $a['sticky'] !== $b['sticky']) {
                return $a['sticky'] ? -1 : 1;
            }

            return $a['created'] < $b['created'] ? 1 * $order : -1 * $order;
        });
    }

    /**
     * Returns the number of links in the reference data
     */
    public function countLinks()
    {
        return $this->_publicCount + $this->_privateCount;
    }

    /**
     * Returns the number of public links in the reference data
     */
    public function countPublicLinks()
    {
        return $this->_publicCount;
    }

    /**
     * Returns the number of private links in the reference data
     */
    public function countPrivateLinks()
    {
        return $this->_privateCount;
    }

    /**
     * Returns the number of links without tag
     */
    public function countUntaggedLinks()
    {
        $cpt = 0;
        foreach ($this->_links as $link) {
            if (empty($link['tags'])) {
                ++$cpt;
            }
        }
        return $cpt;
    }

    public function getLinks()
    {
        $this->reorder();
        return $this->_links;
    }

    /**
     * Setter to override link creation.
     *
     * @param array $links List of links.
     */
    public function setLinks($links)
    {
        $this->_links = $links;
    }
}
