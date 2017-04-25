<?php

/**
 * Class LinkFilter.
 *
 * Perform search and filter operation on link data list.
 */
class LinkFilter
{
    /**
     * @var string permalinks.
     */
    public static $FILTER_HASH   = 'permalink';

    /**
     * @var string text search.
     */
    public static $FILTER_TEXT   = 'fulltext';

    /**
     * @var string tag filter.
     */
    public static $FILTER_TAG    = 'tags';

    /**
     * @var string filter by day.
     */
    public static $FILTER_DAY    = 'FILTER_DAY';

    /**
     * @var string Allowed characters for hashtags (regex syntax).
     */
    public static $HASHTAG_CHARS = '\p{Pc}\p{N}\p{L}\p{Mn}';

    /**
     * @var LinkDB all available links.
     */
    private $links;

    /**
     * @param LinkDB $links initialization.
     */
    public function __construct($links)
    {
        $this->links = $links;
    }

    /**
     * Filter links according to parameters.
     *
     * @param string $type          Type of filter (eg. tags, permalink, etc.).
     * @param mixed  $request       Filter content.
     * @param bool   $casesensitive Optional: Perform case sensitive filter if true.
     * @param string $visibility    Optional: return only all/private/public links
     *
     * @return array filtered link list.
     */
    public function filter($type, $request, $casesensitive = false, $visibility = 'all')
    {
        if (! in_array($visibility, ['all', 'public', 'private'])) {
            $visibility = 'all';
        }

        switch($type) {
            case self::$FILTER_HASH:
                return $this->filterSmallHash($request);
            case self::$FILTER_TAG | self::$FILTER_TEXT:
                if (!empty($request)) {
                    $filtered = $this->links;
                    if (isset($request[0])) {
                        $filtered = $this->filterTags($request[0], $casesensitive, $visibility);
                    }
                    if (isset($request[1])) {
                        $lf = new LinkFilter($filtered);
                        $filtered = $lf->filterFulltext($request[1], $visibility);
                    }
                    return $filtered;
                }
                return $this->noFilter($visibility);
            case self::$FILTER_TEXT:
                return $this->filterFulltext($request, $visibility);
            case self::$FILTER_TAG:
                return $this->filterTags($request, $casesensitive, $visibility);
            case self::$FILTER_DAY:
                return $this->filterDay($request);
            default:
                return $this->noFilter($visibility);
        }
    }

    /**
     * Unknown filter, but handle private only.
     *
     * @param string $visibility Optional: return only all/private/public links
     *
     * @return array filtered links.
     */
    private function noFilter($visibility = 'all')
    {
        if ($visibility === 'all') {
            return $this->links;
        }

        $out = array();
        foreach ($this->links as $key => $value) {
            if ($value['private'] && $visibility === 'private') {
                $out[$key] = $value;
            } else if (! $value['private'] && $visibility === 'public') {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    /**
     * Returns the shaare corresponding to a smallHash.
     *
     * @param string $smallHash permalink hash.
     *
     * @return array $filtered array containing permalink data.
     *
     * @throws LinkNotFoundException if the smallhash doesn't match any link.
     */
    private function filterSmallHash($smallHash)
    {
        $filtered = array();
        foreach ($this->links as $key => $l) {
            if ($smallHash == $l['shorturl']) {
                // Yes, this is ugly and slow
                $filtered[$key] = $l;
                return $filtered;
            }
        }

        if (empty($filtered)) {
            throw new LinkNotFoundException();
        }

        return $filtered;
    }

    /**
     * Returns the list of links corresponding to a full-text search
     *
     * Searches:
     *  - in the URLs, title and description;
     *  - are case-insensitive;
     *  - terms surrounded by quotes " are exact terms search.
     *  - terms starting with a dash - are excluded (except exact terms).
     *
     * Example:
     *    print_r($mydb->filterFulltext('hollandais'));
     *
     * mb_convert_case($val, MB_CASE_LOWER, 'UTF-8')
     *  - allows to perform searches on Unicode text
     *  - see https://github.com/shaarli/Shaarli/issues/75 for examples
     *
     * @param string $searchterms search query.
     * @param string $visibility Optional: return only all/private/public links.
     *
     * @return array search results.
     */
    private function filterFulltext($searchterms, $visibility = 'all')
    {
        if (empty($searchterms)) {
            return $this->noFilter($visibility);
        }

        $filtered = array();
        $search = mb_convert_case(html_entity_decode($searchterms), MB_CASE_LOWER, 'UTF-8');
        $exactRegex = '/"([^"]+)"/';
        // Retrieve exact search terms.
        preg_match_all($exactRegex, $search, $exactSearch);
        $exactSearch = array_values(array_filter($exactSearch[1]));

        // Remove exact search terms to get AND terms search.
        $explodedSearchAnd = explode(' ', trim(preg_replace($exactRegex, '', $search)));
        $explodedSearchAnd = array_values(array_filter($explodedSearchAnd));

        // Filter excluding terms and update andSearch.
        $excludeSearch = array();
        $andSearch = array();
        foreach ($explodedSearchAnd as $needle) {
            if ($needle[0] == '-' && strlen($needle) > 1) {
                $excludeSearch[] = substr($needle, 1);
            } else {
                $andSearch[] = $needle;
            }
        }

        $keys = array('title', 'description', 'url', 'tags');

        // Iterate over every stored link.
        foreach ($this->links as $id => $link) {

            // ignore non private links when 'privatonly' is on.
            if ($visibility !== 'all') {
                if (! $link['private'] && $visibility === 'private') {
                    continue;
                } else if ($link['private'] && $visibility === 'public') {
                    continue;
                }
            }

            // Concatenate link fields to search across fields.
            // Adds a '\' separator for exact search terms.
            $content = '';
            foreach ($keys as $key) {
                $content .= mb_convert_case($link[$key], MB_CASE_LOWER, 'UTF-8') . '\\';
            }

            // Be optimistic
            $found = true;

            // First, we look for exact term search
            for ($i = 0; $i < count($exactSearch) && $found; $i++) {
                $found = strpos($content, $exactSearch[$i]) !== false;
            }

            // Iterate over keywords, if keyword is not found,
            // no need to check for the others. We want all or nothing.
            for ($i = 0; $i < count($andSearch) && $found; $i++) {
                $found = strpos($content, $andSearch[$i]) !== false;
            }

            // Exclude terms.
            for ($i = 0; $i < count($excludeSearch) && $found; $i++) {
                $found = strpos($content, $excludeSearch[$i]) === false;
            }

            if ($found) {
                $filtered[$id] = $link;
            }
        }

        return $filtered;
    }

    /**
     * Returns the list of links associated with a given list of tags
     *
     * You can specify one or more tags, in an array
     *
     * @param array  $tags          list of tags
     * @param bool   $casesensitive ignore case if false.
     * @param string $visibility    Optional: return only all/private/public links.
     *
     * @return array filtered links.
     */
    public function filterTags($tags, $casesensitive = false, $visibility = 'all')
    {
        if (empty($tags)) {
            return $this->noFilter($visibility);
        }

        $searchtags = $casesensitive ? $tags : self::lowerCase($tags);

        $filtered = array();
        if (empty($searchtags)) {
            return $filtered;
        }

        foreach ($this->links as $key => $link) {
            // ignore non private links when 'privatonly' is on.
            if ($visibility !== 'all') {
                if (! $link['private'] && $visibility === 'private') {
                    continue;
                } else if ($link['private'] && $visibility === 'public') {
                    continue;
                }
            }

            $linktags = $casesensitive ? $link['tags'] : self::lowerCase($link['tags']);

            $found = true;
            for ($i = 0 ; $i < count($searchtags) && $found; $i++) {
                // Exclusive search, quit if tag found.
                // Or, tag not found in the link, quit.
                if (($searchtags[$i][0] == '-'
                        && $this->searchTagAndHashTag(substr($searchtags[$i], 1), $linktags, $link['description']))
                    || ($searchtags[$i][0] != '-')
                        && ! $this->searchTagAndHashTag($searchtags[$i], $linktags, $link['description'])
                ) {
                    $found = false;
                }
            }

            if ($found) {
                $filtered[$key] = $link;
            }
        }
        return $filtered;
    }

    /**
     * Returns the list of articles for a given day, chronologically sorted
     *
     * Day must be in the form 'YYYYMMDD' (e.g. '20120125'), e.g.
     *  print_r($mydb->filterDay('20120125'));
     *
     * @param string $day day to filter.
     *
     * @return array all link matching given day.
     *
     * @throws Exception if date format is invalid.
     */
    public function filterDay($day)
    {
        if (! checkDateFormat('Ymd', $day)) {
            throw new Exception('Invalid date format');
        }

        $filtered = array();
        foreach ($this->links as $key => $l) {
            if ($l['created']->format('Ymd') == $day) {
                $filtered[$key] = $l;
            }
        }

        // sort by date ASC
        return array_reverse($filtered, true);
    }

    /**
     * Check if a tag is found in the taglist, or as an hashtag in the link description.
     *
     * @param string $tag         Tag to search.
     * @param array  $taglist     List of tags for the current link.
     * @param string $description Link description.
     *
     * @return bool True if found, false otherwise.
     */
    protected function searchTagAndHashTag($tag, $taglist, $description)
    {
        if (in_array($tag, $taglist)) {
            return true;
        }

        if (preg_match('/(^| )#'. $tag .'([^'. self::$HASHTAG_CHARS .']|$)/mui', $description) > 0) {
            return true;
        }

        return false;
    }

    /**
     * Move tags to lower case.
     *
     * @param string $tags          string containing a list of tags.
     *
     * @return array of lower case tags.
     */
    public static function lowerCase($tags)
    {
        $lowerCase = function($string) {
            // We use UTF-8 conversion to handle various graphemes (i.e. cyrillic, or greek)
            return mb_convert_case($string, MB_CASE_LOWER, 'UTF-8');
        };
        
        $tagsOut = array_map($lowerCase, $tags);
        
        return array_values(array_filter($tagsOut, 'strlen'));
    }
}

class LinkNotFoundException extends Exception
{
    protected $message = 'The link you are trying to reach does not exist or has been deleted.';
}
