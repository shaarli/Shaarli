<?php

declare(strict_types=1);

namespace Shaarli\Bookmark;

use Shaarli\Bookmark\Exception\BookmarkNotFoundException;
use Shaarli\Config\ConfigManager;
use Shaarli\Plugin\PluginManager;

/**
 * Class LinkFilter.
 *
 * Perform search and filter operation on link data list.
 */
class BookmarkFilter
{
    /**
     * @var string permalinks.
     */
    public static $FILTER_HASH = 'permalink';

    /**
     * @var string text search.
     */
    public static $FILTER_TEXT = 'fulltext';

    /**
     * @var string tag filter.
     */
    public static $FILTER_TAG = 'tags';

    /**
     * @var string filter by day.
     */
    public static $DEFAULT = 'NO_FILTER';

    /** @var string Visibility: all */
    public static $ALL = 'all';

    /** @var string Visibility: public */
    public static $PUBLIC = 'public';

    /** @var string Visibility: private */
    public static $PRIVATE = 'private';

    /**
     * @var string Allowed characters for hashtags (regex syntax).
     */
    public static $HASHTAG_CHARS = '\p{Pc}\p{N}\p{L}\p{Mn}';

    /**
     * @var Bookmark[] all available bookmarks.
     */
    private $bookmarks;

    /** @var ConfigManager */
    protected $conf;

    /** @var PluginManager */
    protected $pluginManager;

    /**
     * @param Bookmark[] $bookmarks initialization.
     */
    public function __construct($bookmarks, ConfigManager $conf, PluginManager $pluginManager)
    {
        $this->bookmarks = $bookmarks;
        $this->conf = $conf;
        $this->pluginManager = $pluginManager;
    }

    /**
     * Filter bookmarks according to parameters.
     *
     * @param string $type          Type of filter (eg. tags, permalink, etc.).
     * @param mixed  $request       Filter content.
     * @param bool   $casesensitive Optional: Perform case sensitive filter if true.
     * @param string $visibility    Optional: return only all/private/public bookmarks
     * @param bool   $untaggedonly  Optional: return only untagged bookmarks. Applies only if $type includes FILTER_TAG
     *
     * @return Bookmark[] filtered bookmark list.
     *
     * @throws BookmarkNotFoundException
     */
    public function filter(
        string $type,
        $request,
        bool $casesensitive = false,
        string $visibility = 'all',
        bool $untaggedonly = false
    ) {
        if (!in_array($visibility, ['all', 'public', 'private'])) {
            $visibility = 'all';
        }

        switch ($type) {
            case self::$FILTER_HASH:
                return $this->filterSmallHash($request);
            case self::$FILTER_TAG | self::$FILTER_TEXT: // == "vuotext"
                $noRequest = empty($request) || (empty($request[0]) && empty($request[1]));
                if ($noRequest) {
                    if ($untaggedonly) {
                        return $this->filterUntagged($visibility);
                    }
                    return $this->noFilter($visibility);
                }
                if ($untaggedonly) {
                    $filtered = $this->filterUntagged($visibility);
                } else {
                    $filtered = $this->bookmarks;
                }
                if (!empty($request[0])) {
                    $filtered = (new BookmarkFilter($filtered, $this->conf, $this->pluginManager))
                        ->filterTags($request[0], $casesensitive, $visibility)
                    ;
                }
                if (!empty($request[1])) {
                    $filtered = (new BookmarkFilter($filtered, $this->conf, $this->pluginManager))
                        ->filterFulltext($request[1], $visibility)
                    ;
                }
                return $filtered;
            case self::$FILTER_TEXT:
                return $this->filterFulltext($request, $visibility);
            case self::$FILTER_TAG:
                if ($untaggedonly) {
                    return $this->filterUntagged($visibility);
                } else {
                    return $this->filterTags($request, $casesensitive, $visibility);
                }
            default:
                return $this->noFilter($visibility);
        }
    }

    /**
     * Unknown filter, but handle private only.
     *
     * @param string $visibility Optional: return only all/private/public bookmarks
     *
     * @return Bookmark[] filtered bookmarks.
     */
    private function noFilter(string $visibility = 'all')
    {
        $out = [];
        foreach ($this->bookmarks as $key => $value) {
            if (
                !$this->pluginManager->filterSearchEntry(
                    $value,
                    ['source' => 'no_filter', 'visibility' => $visibility]
                )
            ) {
                continue;
            }

            if ($visibility === 'all') {
                $out[$key] = $value;
            } elseif ($value->isPrivate() && $visibility === 'private') {
                $out[$key] = $value;
            } elseif (!$value->isPrivate() && $visibility === 'public') {
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
     * @return Bookmark[] $filtered array containing permalink data.
     *
     * @throws BookmarkNotFoundException if the smallhash doesn't match any link.
     */
    private function filterSmallHash(string $smallHash)
    {
        foreach ($this->bookmarks as $key => $l) {
            if ($smallHash == $l->getShortUrl()) {
                // Yes, this is ugly and slow
                return [$key => $l];
            }
        }

        throw new BookmarkNotFoundException();
    }

    /**
     * Returns the list of bookmarks corresponding to a full-text search
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
     * @param string $visibility  Optional: return only all/private/public bookmarks.
     *
     * @return Bookmark[] search results.
     */
    private function filterFulltext(string $searchterms, string $visibility = 'all')
    {
        if (empty($searchterms)) {
            return $this->noFilter($visibility);
        }

        $filtered = [];
        $search = mb_convert_case(html_entity_decode($searchterms), MB_CASE_LOWER, 'UTF-8');
        $exactRegex = '/"([^"]+)"/';
        // Retrieve exact search terms.
        preg_match_all($exactRegex, $search, $exactSearch);
        $exactSearch = array_values(array_filter($exactSearch[1]));

        // Remove exact search terms to get AND terms search.
        $explodedSearchAnd = explode(' ', trim(preg_replace($exactRegex, '', $search)));
        $explodedSearchAnd = array_values(array_filter($explodedSearchAnd));

        // Filter excluding terms and update andSearch.
        $excludeSearch = [];
        $andSearch = [];
        foreach ($explodedSearchAnd as $needle) {
            if ($needle[0] == '-' && strlen($needle) > 1) {
                $excludeSearch[] = substr($needle, 1);
            } else {
                $andSearch[] = $needle;
            }
        }

        // Iterate over every stored link.
        foreach ($this->bookmarks as $id => $bookmark) {
            if (
                !$this->pluginManager->filterSearchEntry(
                    $bookmark,
                    [
                    'source' => 'fulltext',
                    'searchterms' => $searchterms,
                    'andSearch' => $andSearch,
                    'exactSearch' => $exactSearch,
                    'excludeSearch' => $excludeSearch,
                    'visibility' => $visibility
                    ]
                )
            ) {
                continue;
            }

            // ignore non private bookmarks when 'privatonly' is on.
            if ($visibility !== 'all') {
                if (!$bookmark->isPrivate() && $visibility === 'private') {
                    continue;
                } elseif ($bookmark->isPrivate() && $visibility === 'public') {
                    continue;
                }
            }

            $lengths = [];
            $content = $this->buildFullTextSearchableLink($bookmark, $lengths);

            // Be optimistic
            $found = true;
            $foundPositions = [];

            // First, we look for exact term search
            // Then iterate over keywords, if keyword is not found,
            // no need to check for the others. We want all or nothing.
            foreach ([$exactSearch, $andSearch] as $search) {
                for ($i = 0; $i < count($search) && $found !== false; $i++) {
                    $found = mb_strpos($content, $search[$i]);
                    if ($found === false) {
                        break;
                    }

                    $foundPositions[] = ['start' => $found, 'end' => $found + mb_strlen($search[$i])];
                }
            }

            // Exclude terms.
            for ($i = 0; $i < count($excludeSearch) && $found !== false; $i++) {
                $found = strpos($content, $excludeSearch[$i]) === false;
            }

            if ($found !== false) {
                $bookmark->setAdditionalContentEntry(
                    'search_highlight',
                    $this->postProcessFoundPositions($lengths, $foundPositions)
                );

                $filtered[$id] = $bookmark;
            }
        }

        return $filtered;
    }

    /**
     * Returns the list of bookmarks associated with a given list of tags
     *
     * You can specify one or more tags, separated by space or a comma, e.g.
     *  print_r($mydb->filterTags('linux programming'));
     *
     * @param string|array $tags          list of tags, separated by commas or blank spaces if passed as string.
     * @param bool         $casesensitive ignore case if false.
     * @param string       $visibility    Optional: return only all/private/public bookmarks.
     *
     * @return Bookmark[] filtered bookmarks.
     */
    public function filterTags($tags, bool $casesensitive = false, string $visibility = 'all')
    {
        $tagsSeparator = $this->conf->get('general.tags_separator', ' ');
        // get single tags (we may get passed an array, even though the docs say different)
        $inputTags = $tags;
        if (!is_array($tags)) {
            // we got an input string, split tags
            $inputTags = tags_str2array($inputTags, $tagsSeparator);
        }

        if (count($inputTags) === 0) {
            // no input tags
            return $this->noFilter($visibility);
        }

        // If we only have public visibility, we can't look for hidden tags
        if ($visibility === self::$PUBLIC) {
            $inputTags = array_values(array_filter($inputTags, function ($tag) {
                return ! startsWith($tag, '.');
            }));

            if (empty($inputTags)) {
                return [];
            }
        }

        // build regex from all tags
        $re = '/^' . implode(array_map([$this, 'tag2regex'], $inputTags)) . '.*$/';
        if (!$casesensitive) {
            // make regex case insensitive
            $re .= 'i';
        }

        // create resulting array
        $filtered = [];

        // iterate over each link
        foreach ($this->bookmarks as $key => $bookmark) {
            if (
                !$this->pluginManager->filterSearchEntry(
                    $bookmark,
                    [
                    'source' => 'tags',
                    'tags' => $tags,
                    'casesensitive' => $casesensitive,
                    'visibility' => $visibility
                    ]
                )
            ) {
                continue;
            }

            // check level of visibility
            // ignore non private bookmarks when 'privateonly' is on.
            if ($visibility !== 'all') {
                if (!$bookmark->isPrivate() && $visibility === 'private') {
                    continue;
                } elseif ($bookmark->isPrivate() && $visibility === 'public') {
                    continue;
                }
            }
            // build search string, start with tags of current link
            $search = $bookmark->getTagsString($tagsSeparator);
            if (strlen(trim($bookmark->getDescription())) && strpos($bookmark->getDescription(), '#') !== false) {
                // description given and at least one possible tag found
                $descTags = [];
                // find all tags in the form of #tag in the description
                preg_match_all(
                    '/(?<![' . self::$HASHTAG_CHARS . '])#([' . self::$HASHTAG_CHARS . ']+?)\b/sm',
                    $bookmark->getDescription(),
                    $descTags
                );
                if (count($descTags[1])) {
                    // there were some tags in the description, add them to the search string
                    $search .= $tagsSeparator . tags_array2str($descTags[1], $tagsSeparator);
                }
            }
            // match regular expression with search string
            if (!preg_match($re, $search)) {
                // this entry does _not_ match our regex
                continue;
            }
            $filtered[$key] = $bookmark;
        }

        return $filtered;
    }

    /**
     * Return only bookmarks without any tag.
     *
     * @param string $visibility return only all/private/public bookmarks.
     *
     * @return Bookmark[] filtered bookmarks.
     */
    public function filterUntagged(string $visibility)
    {
        $filtered = [];
        foreach ($this->bookmarks as $key => $bookmark) {
            if (
                !$this->pluginManager->filterSearchEntry(
                    $bookmark,
                    ['source' => 'untagged', 'visibility' => $visibility]
                )
            ) {
                continue;
            }

            if ($visibility !== 'all') {
                if (!$bookmark->isPrivate() && $visibility === 'private') {
                    continue;
                } elseif ($bookmark->isPrivate() && $visibility === 'public') {
                    continue;
                }
            }

            if (empty($bookmark->getTags())) {
                $filtered[$key] = $bookmark;
            }
        }

        return $filtered;
    }

    /**
     * Convert a list of tags (str) to an array. Also
     * - handle case sensitivity.
     * - accepts spaces commas as separator.
     *
     * @param string $tags          string containing a list of tags.
     * @param bool   $casesensitive will convert everything to lowercase if false.
     *
     * @return string[] filtered tags string.
     */
    public static function tagsStrToArray(string $tags, bool $casesensitive): array
    {
        // We use UTF-8 conversion to handle various graphemes (i.e. cyrillic, or greek)
        $tagsOut = $casesensitive ? $tags : mb_convert_case($tags, MB_CASE_LOWER, 'UTF-8');
        $tagsOut = str_replace(',', ' ', $tagsOut);

        return preg_split('/\s+/', $tagsOut, -1, PREG_SPLIT_NO_EMPTY);
    }

    /**
     * generate a regex fragment out of a tag
     *
     * @param string $tag to to generate regexs from. may start with '-' to negate, contain '*' as wildcard
     *
     * @return string generated regex fragment
     */
    protected function tag2regex(string $tag): string
    {
        $tagsSeparator = $this->conf->get('general.tags_separator', ' ');
        $len = strlen($tag);
        if (!$len || $tag === "-" || $tag === "*") {
            // nothing to search, return empty regex
            return '';
        }
        if ($tag[0] === "-") {
            // query is negated
            $i = 1; // use offset to start after '-' character
            $regex = '(?!'; // create negative lookahead
        } else {
            $i = 0; // start at first character
            $regex = '(?='; // use positive lookahead
        }
        // before tag may only be the separator or the beginning
        $regex .= '.*(?:^|' . $tagsSeparator . ')';
        // iterate over string, separating it into placeholder and content
        for (; $i < $len; $i++) {
            if ($tag[$i] === '*') {
                // placeholder found
                $regex .= '[^' . $tagsSeparator . ']*?';
            } else {
                // regular characters
                $offset = strpos($tag, '*', $i);
                if ($offset === false) {
                    // no placeholder found, set offset to end of string
                    $offset = $len;
                }
                // subtract one, as we want to get before the placeholder or end of string
                $offset -= 1;
                // we got a tag name that we want to search for. escape any regex characters to prevent conflicts.
                $regex .= preg_quote(substr($tag, $i, $offset - $i + 1), '/');
                // move $i on
                $i = $offset;
            }
        }
        // after the tag may only be the separator or the end
        $regex .= '(?:$|' . $tagsSeparator . '))';
        return $regex;
    }

    /**
     * This method finalize the content of the foundPositions array,
     * by associated all search results to their associated bookmark field,
     * making sure that there is no overlapping results, etc.
     *
     * @param array $fieldLengths   Start and end positions of every bookmark fields in the aggregated bookmark content.
     * @param array $foundPositions Positions where the search results were found in the aggregated content.
     *
     * @return array Updated $foundPositions, by bookmark field.
     */
    protected function postProcessFoundPositions(array $fieldLengths, array $foundPositions): array
    {
        // Sort results by starting position ASC.
        usort($foundPositions, function (array $entryA, array $entryB): int {
            return $entryA['start'] > $entryB['start'] ? 1 : -1;
        });

        $out = [];
        $currentMax = -1;
        foreach ($foundPositions as $foundPosition) {
            // we do not allow overlapping highlights
            if ($foundPosition['start'] < $currentMax) {
                continue;
            }

            $currentMax = $foundPosition['end'];
            foreach ($fieldLengths as $part => $length) {
                if ($foundPosition['start'] < $length['start'] || $foundPosition['start'] > $length['end']) {
                    continue;
                }

                $out[$part][] = [
                    'start' => $foundPosition['start'] - $length['start'],
                    'end' => $foundPosition['end'] - $length['start'],
                ];
                break;
            }
        }

        return $out;
    }

    /**
     * Concatenate link fields to search across fields. Adds a '\' separator for exact search terms.
     * Also populate $length array with starting and ending positions of every bookmark field
     * inside concatenated content.
     *
     * @param Bookmark $link
     * @param array    $lengths (by reference)
     *
     * @return string Lowercase concatenated fields content.
     */
    protected function buildFullTextSearchableLink(Bookmark $link, array &$lengths): string
    {
        $tagString = $link->getTagsString($this->conf->get('general.tags_separator', ' '));
        $content  = mb_convert_case($link->getTitle(), MB_CASE_LOWER, 'UTF-8') . '\\';
        $content .= mb_convert_case($link->getDescription(), MB_CASE_LOWER, 'UTF-8') . '\\';
        $content .= mb_convert_case($link->getUrl(), MB_CASE_LOWER, 'UTF-8') . '\\';
        $content .= mb_convert_case($tagString, MB_CASE_LOWER, 'UTF-8') . '\\';

        $lengths['title'] = ['start' => 0, 'end' => mb_strlen($link->getTitle())];
        $nextField = $lengths['title']['end'] + 1;
        $lengths['description'] = ['start' => $nextField, 'end' => $nextField + mb_strlen($link->getDescription())];
        $nextField = $lengths['description']['end'] + 1;
        $lengths['url'] = ['start' => $nextField, 'end' => $nextField + mb_strlen($link->getUrl())];
        $nextField = $lengths['url']['end'] + 1;
        $lengths['tags'] = ['start' => $nextField, 'end' => $nextField + mb_strlen($tagString)];

        return $content;
    }
}
