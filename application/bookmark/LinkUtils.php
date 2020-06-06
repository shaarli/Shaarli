<?php

use Shaarli\Bookmark\Bookmark;

/**
 * Extract title from an HTML document.
 *
 * @param string $html HTML content where to look for a title.
 *
 * @return bool|string Extracted title if found, false otherwise.
 */
function html_extract_title($html)
{
    if (preg_match('!<title.*?>(.*?)</title>!is', $html, $matches)) {
        return trim(str_replace("\n", '', $matches[1]));
    }
    return false;
}

/**
 * Extract charset from HTTP header if it's defined.
 *
 * @param string $header HTTP header Content-Type line.
 *
 * @return bool|string Charset string if found (lowercase), false otherwise.
 */
function header_extract_charset($header)
{
    preg_match('/charset="?([^; ]+)/i', $header, $match);
    if (! empty($match[1])) {
        return strtolower(trim($match[1]));
    }

    return false;
}

/**
 * Extract charset HTML content (tag <meta charset>).
 *
 * @param string $html HTML content where to look for charset.
 *
 * @return bool|string Charset string if found, false otherwise.
 */
function html_extract_charset($html)
{
    // Get encoding specified in HTML header.
    preg_match('#<meta .*charset=["\']?([^";\'>/]+)["\']? */?>#Usi', $html, $enc);
    if (!empty($enc[1])) {
        return strtolower($enc[1]);
    }

    return false;
}

/**
 * Extract meta tag from HTML content in either:
 *   - OpenGraph: <meta property="og:[tag]" ...>
 *   - Meta tag: <meta name="[tag]" ...>
 *
 * @param string $tag  Name of the tag to retrieve.
 * @param string $html HTML content where to look for charset.
 *
 * @return bool|string Charset string if found, false otherwise.
 */
function html_extract_tag($tag, $html)
{
    $propertiesKey = ['property', 'name', 'itemprop'];
    $properties = implode('|', $propertiesKey);
    // Try to retrieve OpenGraph image.
    $ogRegex = '#<meta[^>]+(?:'. $properties .')=["\']?(?:og:)?'. $tag .'["\'\s][^>]*content=["\']?(.*?)["\'/>]#';
    // If the attributes are not in the order property => content (e.g. Github)
    // New regex to keep this readable... more or less.
    $ogRegexReverse = '#<meta[^>]+content=["\']([^"\']+)[^>]+(?:'. $properties .')=["\']?(?:og)?:'. $tag .'["\'\s/>]#';

    if (preg_match($ogRegex, $html, $matches) > 0
        || preg_match($ogRegexReverse, $html, $matches) > 0
    ) {
        return $matches[1];
    }

    return false;
}

/**
 * In a string, converts URLs to clickable bookmarks.
 *
 * @param string $text       input string.
 *
 * @return string returns $text with all bookmarks converted to HTML bookmarks.
 *
 * @see Function inspired from http://www.php.net/manual/en/function.preg-replace.php#85722
 */
function text2clickable($text)
{
    $regex = '!(((?:https?|ftp|file)://|apt:|magnet:)\S+[a-z0-9\(\)]/?)!si';
    return preg_replace($regex, '<a href="$1">$1</a>', $text);
}

/**
 * Auto-link hashtags.
 *
 * @param string $description Given description.
 * @param string $indexUrl    Root URL.
 *
 * @return string Description with auto-linked hashtags.
 */
function hashtag_autolink($description, $indexUrl = '')
{
    /*
     * To support unicode: http://stackoverflow.com/a/35498078/1484919
     * \p{Pc} - to match underscore
     * \p{N} - numeric character in any script
     * \p{L} - letter from any language
     * \p{Mn} - any non marking space (accents, umlauts, etc)
     */
    $regex = '/(^|\s)#([\p{Pc}\p{N}\p{L}\p{Mn}]+)/mui';
    $replacement = '$1<a href="'. $indexUrl .'./add-tag/$2" title="Hashtag $2">#$2</a>';
    return preg_replace($regex, $replacement, $description);
}

/**
 * This function inserts &nbsp; where relevant so that multiple spaces are properly displayed in HTML
 * even in the absence of <pre>  (This is used in description to keep text formatting).
 *
 * @param string $text input text.
 *
 * @return string formatted text.
 */
function space2nbsp($text)
{
    return preg_replace('/(^| ) /m', '$1&nbsp;', $text);
}

/**
 * Format Shaarli's description
 *
 * @param string $description shaare's description.
 * @param string $indexUrl    URL to Shaarli's index.

 * @return string formatted description.
 */
function format_description($description, $indexUrl = '')
{
    return nl2br(space2nbsp(hashtag_autolink(text2clickable($description), $indexUrl)));
}

/**
 * Generate a small hash for a link.
 *
 * @param DateTime $date Link creation date.
 * @param int      $id   Link ID.
 *
 * @return string the small hash generated from link data.
 */
function link_small_hash($date, $id)
{
    return smallHash($date->format(Bookmark::LINK_DATE_FORMAT) . $id);
}

/**
 * Returns whether or not the link is an internal note.
 * Its URL starts by `?` because it's actually a permalink.
 *
 * @param string $linkUrl
 *
 * @return bool true if internal note, false otherwise.
 */
function is_note($linkUrl)
{
    return isset($linkUrl[0]) && $linkUrl[0] === '?';
}
