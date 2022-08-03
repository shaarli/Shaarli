<?php

namespace Shaarli\Http;

/**
 * URL representation and cleanup utilities
 *
 * Form
 *   scheme://[username:password@]host[:port][/path][?query][#fragment]
 *
 * Examples
 *   http://username:password@hostname:9090/path?arg1=value1&arg2=value2#anchor
 *   https://host.name.tld
 *   https://h2.g2/faq/?vendor=hitchhiker&item=guide&dest=galaxy#answer
 *
 * @see http://www.faqs.org/rfcs/rfc3986.html
 */
class Url
{
    private static $annoyingQueryParams = [
        // Facebook
        'action_object_map=',
        'action_ref_map=',
        'action_type_map=',
        'fb_',
        'fb=',
        'PHPSESSID=',

        // Scoop.it
        '__scoop',

        // Google Analytics & FeedProxy
        'utm_',

        // ATInternet
        'xtor=',

        // Other
        'campaign_'
    ];

    private static $annoyingFragments = [
        // ATInternet
        'xtor=RSS-',

        // Misc.
        'tk.rss_all'
    ];

    /*
     * URL parts represented as an array
     *
     * @see http://php.net/parse_url
     */
    protected $parts;

    /**
     * Parses a string containing a URL
     *
     * @param string $url a string containing a URL
     */
    public function __construct($url)
    {
        $url = $url ?? '';
        $url = self::cleanupUnparsedUrl(trim($url));
        $this->parts = parse_url($url);

        if (!empty($url) && empty($this->parts['scheme'])) {
            $this->parts['scheme'] = 'http';
        }
    }

    /**
     * Clean up URL before it's parsed.
     * ie. handle urlencode, url prefixes, etc.
     *
     * @param string $url URL to clean.
     *
     * @return string cleaned URL.
     */
    protected static function cleanupUnparsedUrl($url)
    {
        return self::removeFirefoxAboutReader($url);
    }

    /**
     * Remove Firefox Reader prefix if it's present.
     *
     * @param string $input url
     *
     * @return string cleaned url
     */
    protected static function removeFirefoxAboutReader($input)
    {
        $firefoxPrefix = 'about://reader?url=';
        if (startsWith($input, $firefoxPrefix)) {
            return urldecode(ltrim($input, $firefoxPrefix));
        }
        return $input;
    }

    /**
     * Returns a string representation of this URL
     */
    public function toString()
    {
        return unparse_url($this->parts);
    }

    /**
     * Removes undesired query parameters
     */
    protected function cleanupQuery()
    {
        if (!isset($this->parts['query'])) {
            return;
        }

        $queryParams = explode('&', $this->parts['query']);

        foreach (self::$annoyingQueryParams as $annoying) {
            foreach ($queryParams as $param) {
                if (startsWith($param, $annoying)) {
                    $queryParams = array_diff($queryParams, [$param]);
                    continue;
                }
            }
        }

        if (count($queryParams) == 0) {
            unset($this->parts['query']);
            return;
        }

        $this->parts['query'] = implode('&', $queryParams);
    }

    /**
     * Removes undesired fragments
     */
    protected function cleanupFragment()
    {
        if (!isset($this->parts['fragment'])) {
            return;
        }

        foreach (self::$annoyingFragments as $annoying) {
            if (startsWith($this->parts['fragment'], $annoying)) {
                unset($this->parts['fragment']);
                break;
            }
        }
    }

    /**
     * Removes undesired query parameters and fragments
     *
     * @return string the string representation of this URL after cleanup
     */
    public function cleanup()
    {
        $this->cleanupQuery();
        $this->cleanupFragment();
        return $this->toString();
    }

    /**
     * Converts an URL with an International Domain Name host to a ASCII one.
     * This requires PHP-intl. If it's not available, just returns this->cleanup().
     *
     * @return string converted cleaned up URL.
     */
    public function idnToAscii()
    {
        $out = $this->cleanup();
        if (!function_exists('idn_to_ascii') || !isset($this->parts['host'])) {
            return $out;
        }
        $asciiHost = idn_to_ascii($this->parts['host'], 0, INTL_IDNA_VARIANT_UTS46);
        return str_replace($this->parts['host'], $asciiHost, $out);
    }

    /**
     * Get URL scheme.
     *
     * @return string the URL scheme or false if none is provided.
     */
    public function getScheme()
    {
        if (!isset($this->parts['scheme'])) {
            return false;
        }
        return $this->parts['scheme'];
    }

    /**
     * Get URL host.
     *
     * @return string the URL host or false if none is provided.
     */
    public function getHost()
    {
        if (empty($this->parts['host'])) {
            return false;
        }
        return $this->parts['host'];
    }

    /**
     * Test if the UrlUtils is an HTTP one.
     *
     * @return true is HTTP, false otherwise.
     */
    public function isHttp()
    {
        return strpos(strtolower($this->parts['scheme']), 'http') !== false;
    }
}
