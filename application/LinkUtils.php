<?php

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
 * Determine charset from downloaded page.
 * Priority:
 *   1. HTTP headers (Content type).
 *   2. HTML content page (tag <meta charset>).
 *   3. Use a default charset (default: UTF-8).
 *
 * @param array  $headers           HTTP headers array.
 * @param string $htmlContent       HTML content where to look for charset.
 * @param string $defaultCharset    Default charset to apply if other methods failed.
 *
 * @return string Determined charset.
 */
function get_charset($headers, $htmlContent, $defaultCharset = 'utf-8')
{
    if ($charset = headers_extract_charset($headers)) {
        return $charset;
    }

    if ($charset = html_extract_charset($htmlContent)) {
        return $charset;
    }

    return $defaultCharset;
}

/**
 * Extract charset from HTTP headers if it's defined.
 *
 * @param array $headers HTTP headers array.
 *
 * @return bool|string Charset string if found (lowercase), false otherwise.
 */
function headers_extract_charset($headers)
{
    if (! empty($headers['Content-Type']) && strpos($headers['Content-Type'], 'charset=') !== false) {
        preg_match('/charset="?([^; ]+)/i', $headers['Content-Type'], $match);
        if (! empty($match[1])) {
            return strtolower(trim($match[1]));
        }
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
 * Count private links in given linklist.
 *
 * @param array|Countable $links Linklist.
 *
 * @return int Number of private links.
 */
function count_private($links)
{
    $cpt = 0;
    foreach ($links as $link) {
        if ($link['private']) {
            $cpt += 1;
        }
    }

    return $cpt;
}

/**
 * In a string, converts URLs to clickable links.
 *
 * @param string $text       input string.
 * @param string $redirector if a redirector is set, use it to gerenate links.
 *
 * @return string returns $text with all links converted to HTML links.
 *
 * @see Function inspired from http://www.php.net/manual/en/function.preg-replace.php#85722
 */
function text2clickable($text, $redirector = '')
{
    $regex = '!(((?:https?|ftp|file)://|apt:|magnet:)\S+[[:alnum:]]/?)!si';

    if (empty($redirector)) {
        return preg_replace($regex, '<a href="$1">$1</a>', $text);
    }
    // Redirector is set, urlencode the final URL.
    return preg_replace_callback(
        $regex,
        function ($matches) use ($redirector) {
            return '<a href="' . $redirector . urlencode($matches[1]) .'">'. $matches[1] .'</a>';
        },
        $text
    );
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
    $replacement = '$1<a href="'. $indexUrl .'?addtag=$2" title="Hashtag $2">#$2</a>';
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
 * @param string $redirector  if a redirector is set, use it to gerenate links.
 * @param string $indexUrl    URL to Shaarli's index.
 *
 * @return string formatted description.
 */
function format_description($description, $redirector = '', $indexUrl = '') {
    return nl2br(space2nbsp(hashtag_autolink(text2clickable($description, $redirector), $indexUrl)));
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
    return smallHash($date->format(LinkDB::LINK_DATE_FORMAT) . $id);
}
