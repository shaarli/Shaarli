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
     * @var array all available links.
     */
    private $links;

    /**
     * @param array $links initialization.
     */
    public function __construct($links)
    {
        $this->links = $links;
    }

    /**
     * Filter links according to parameters.
     *
     * @param string $type          Type of filter (eg. tags, permalink, etc.).
     * @param string $request       Filter content.
     * @param bool   $casesensitive Optional: Perform case sensitive filter if true.
     * @param bool   $privateonly   Optional: Only returns private links if true.
     *
     * @return array filtered link list.
     */
    public function filter($type, $request, $casesensitive = false, $privateonly = false)
    {
        switch($type) {
            case self::$FILTER_HASH:
                return $this->filterSmallHash($request);
                break;
            case self::$FILTER_TEXT:
                return $this->filterFulltext($request, $privateonly);
                break;
            case self::$FILTER_TAG:
                return $this->filterTags($request, $casesensitive, $privateonly);
                break;
            case self::$FILTER_DAY:
                return $this->filterDay($request);
                break;
            default:
                return $this->noFilter($privateonly);
        }
    }

    /**
     * Unknown filter, but handle private only.
     *
     * @param bool $privateonly returns private link only if true.
     *
     * @return array filtered links.
     */
    private function noFilter($privateonly = false)
    {
        if (! $privateonly) {
            krsort($this->links);
            return $this->links;
        }

        $out = array();
        foreach ($this->links as $value) {
            if ($value['private']) {
                $out[$value['linkdate']] = $value;
            }
        }

        krsort($out);
        return $out;
    }

    /**
     * Returns the shaare corresponding to a smallHash.
     *
     * @param string $smallHash permalink hash.
     *
     * @return array $filtered array containing permalink data.
     */
    private function filterSmallHash($smallHash)
    {
        $filtered = array();
        foreach ($this->links as $l) {
            if ($smallHash == smallHash($l['linkdate'])) {
                // Yes, this is ugly and slow
                $filtered[$l['linkdate']] = $l;
                return $filtered;
            }
        }
        return $filtered;
    }

    /**
     * Returns the list of links corresponding to a full-text search
     *
     * Searches:
     *  - in the URLs, title and description;
     *  - are case-insensitive.
     *
     * Example:
     *    print_r($mydb->filterFulltext('hollandais'));
     *
     * mb_convert_case($val, MB_CASE_LOWER, 'UTF-8')
     *  - allows to perform searches on Unicode text
     *  - see https://github.com/shaarli/Shaarli/issues/75 for examples
     *
     * @param string $searchterms search query.
     * @param bool   $privateonly return only private links if true.
     *
     * @return array search results.
     */
    private function filterFulltext($searchterms, $privateonly = false)
    {
        $search = mb_convert_case(html_entity_decode($searchterms), MB_CASE_LOWER, 'UTF-8');
        $explodedSearch = explode(' ', trim($search));
        $keys = array('title', 'description', 'url', 'tags');
        $found = true;
        $searchExactPhrase = false;

        // Check if we're using double-quotes to search for the exact string
        if ($search[0] == '"' && $search[strlen($search) - 1] == '"') {
            $searchExactPhrase = true;
            
            // Remove the double-quotes as they are not what we search for
            $search = substr($search, 1, -1);
        }
        // Iterate over every stored link.
        foreach ($this->links as $link) {

            // ignore non private links when 'privatonly' is on.
            if (! $link['private'] && $privateonly === true) {
                continue;
            }

            // Iterate over searchable link fields.
            foreach ($keys as $key) {
                // Be optimistic
                $found = true;
                
                // FIXME: Find a better word for where you're searching in 
                $haystack = mb_convert_case($link[$key], MB_CASE_LOWER, 'UTF-8');

                // When searching for the phrase, check if it's in the haystack...
                if ( $searchExactPhrase && strpos($haystack, $search) !== false) {
                    break;
                }
                else {
                    // Iterate over keywords, if keyword is not found,
                    // no need to check for the others. We want all or nothing.
                    foreach($explodedSearch as $keyword) {
                         if(strpos($haystack, $keyword) === false) {
                           $found = false;
                           break;
                      }
                    }
                }
                
                // One of the fields of the link matches, no need to check the other.
                if ($found) {
                    break;
                }
            }
            
            if ($found) {
                $filtered[$link['linkdate']] = $link;
            }
        }

        krsort($filtered);
        return $filtered;
    }

    /**
     * Returns the list of links associated with a given list of tags
     *
     * You can specify one or more tags, separated by space or a comma, e.g.
     *  print_r($mydb->filterTags('linux programming'));
     *
     * @param string $tags          list of tags separated by commas or blank spaces.
     * @param bool   $casesensitive ignore case if false.
     * @param bool   $privateonly   returns private links only.
     *
     * @return array filtered links.
     */
    public function filterTags($tags, $casesensitive = false, $privateonly = false)
    {
        $searchtags = $this->tagsStrToArray($tags, $casesensitive);
        $filtered = array();

        foreach ($this->links as $l) {
            // ignore non private links when 'privatonly' is on.
            if (! $l['private'] && $privateonly === true) {
                continue;
            }

            $linktags = $this->tagsStrToArray($l['tags'], $casesensitive);

            if (count(array_intersect($linktags, $searchtags)) == count($searchtags)) {
                $filtered[$l['linkdate']] = $l;
            }
        }
        krsort($filtered);
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
        foreach ($this->links as $l) {
            if (startsWith($l['linkdate'], $day)) {
                $filtered[$l['linkdate']] = $l;
            }
        }
        ksort($filtered);
        return $filtered;
    }

    /**
     * Convert a list of tags (str) to an array. Also
     * - handle case sensitivity.
     * - accepts spaces commas as separator.
     * - remove private tags for loggedout users.
     *
     * @param string $tags          string containing a list of tags.
     * @param bool   $casesensitive will convert everything to lowercase if false.
     *
     * @return array filtered tags string.
    */
    public function tagsStrToArray($tags, $casesensitive)
    {
        // We use UTF-8 conversion to handle various graphemes (i.e. cyrillic, or greek)
        $tagsOut = $casesensitive ? $tags : mb_convert_case($tags, MB_CASE_LOWER, 'UTF-8');
        $tagsOut = str_replace(',', ' ', $tagsOut);

        return explode(' ', trim($tagsOut));
    }
}
