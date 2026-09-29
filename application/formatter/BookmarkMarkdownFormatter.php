<?php

namespace Shaarli\Formatter;

use Shaarli\Config\ConfigManager;
use Shaarli\Formatter\Parsedown\ShaarliParsedown;
use HTMLPurifier;
use HTMLPurifier_Config;
use HTMLPurifier_ConfigSchema;

/**
 * Class BookmarkMarkdownFormatter
 *
 * Format bookmark description into Markdown format.
 *
 * @package Shaarli\Formatter
 */
class BookmarkMarkdownFormatter extends BookmarkDefaultFormatter
{
    /**
     * When this tag is present in a bookmark, its description should not be processed with Markdown
     */
    public const NO_MD_TAG = 'nomarkdown';

    /** @var \Parsedown instance */
    protected $parsedown;

    /** @var bool used to escape HTML in Markdown or not.
     *            It MUST be set to true for shared instance as HTML content can
     *            introduce XSS vulnerabilities.
     */
    protected $escape;

    /**
     * @var array List of allowed protocols for links inside bookmark's description.
     */
    protected $allowedProtocols;

    /**
     * LinkMarkdownFormatter constructor.
     *
     * @param ConfigManager $conf instance
     * @param bool          $isLoggedIn
     */
    public function __construct(ConfigManager $conf, bool $isLoggedIn)
    {
        parent::__construct($conf, $isLoggedIn);

        $this->parsedown = new ShaarliParsedown();
        $this->escape = $conf->get('security.markdown_escape', true);
        $this->allowedProtocols = $conf->get('security.allowed_protocols', []);
    }

    /**
     * @inheritdoc
     */
    public function formatDescription($bookmark)
    {
        if (in_array(self::NO_MD_TAG, $bookmark->getTags())) {
            return parent::formatDescription($bookmark);
        }

        $processedDescription = $this->tokenizeSearchHighlightField(
            $bookmark->getDescription() ?? '',
            $bookmark->getAdditionalContentEntry('search_highlight')['description'] ?? []
        );
        $processedDescription = $this->filterProtocols($processedDescription);
        $processedDescription = $this->formatHashTags($processedDescription);
        $processedDescription = $this->reverseEscapedHtml($processedDescription);
        $processedDescription = $this->parsedown
            ->setMarkupEscaped($this->escape)
            ->setBreaksEnabled(true)
            ->text($processedDescription);
        $processedDescription = $this->sanitizeHtml($processedDescription);
        $processedDescription = $this->replaceTokens($processedDescription);

        if (!empty($processedDescription)) {
            $processedDescription = '<div class="markdown">' . $processedDescription . '</div>';
        }

        return $processedDescription;
    }

    /**
     * Remove the NO markdown tag if it is present
     *
     * @inheritdoc
     */
    protected function formatTagList($bookmark)
    {
        $out = parent::formatTagList($bookmark);
        if ($this->isLoggedIn === false && ($pos = array_search(self::NO_MD_TAG, $out)) !== false) {
            unset($out[$pos]);
            return array_values($out);
        }
        return $out;
    }

    /**
     * Replace not whitelisted protocols with http:// in given description.
     * Also adds `index_url` to relative links if it's specified
     *
     * @param string $description      input description text.
     *
     * @return string $description without malicious link.
     */
    protected function filterProtocols($description)
    {
        $allowedProtocols = $this->allowedProtocols;
        $indexUrl = ! empty($this->contextData['index_url']) ? $this->contextData['index_url'] : '';

        return preg_replace_callback(
            '#]\((.*?)\)#is',
            function ($match) use ($allowedProtocols, $indexUrl) {
                $link = startsWith($match[1], '?') || startsWith($match[1], '/') ? $indexUrl : '';
                $link .= whitelist_protocols($match[1], $allowedProtocols);
                return '](' . $link . ')';
            },
            $description
        );
    }

    /**
     * Replace hashtag in Markdown links format
     * E.g. `#hashtag` becomes `[#hashtag](./add-tag/hashtag)`
     * It includes the index URL if specified.
     *
     * @param string $description
     *
     * @return string
     */
    protected function formatHashTags($description)
    {
        $indexUrl = ! empty($this->contextData['index_url']) ? $this->contextData['index_url'] : '';
        $tokens = '(?:' . BookmarkDefaultFormatter::SEARCH_HIGHLIGHT_OPEN . ')' .
            '(?:' . BookmarkDefaultFormatter::SEARCH_HIGHLIGHT_CLOSE . ')'
        ;

        /*
         * To support unicode: http://stackoverflow.com/a/35498078/1484919
         * \p{Pc} - to match underscore
         * \p{N} - numeric character in any script
         * \p{L} - letter from any language
         * \p{Mn} - any non marking space (accents, umlauts, etc)
         */
        $regex = '/(^|\s)#([\p{Pc}\p{N}\p{L}\p{Mn}' . $tokens . ']+)/mui';
        $replacement = function (array $match) use ($indexUrl): string {
            $cleanMatch = str_replace(
                BookmarkDefaultFormatter::SEARCH_HIGHLIGHT_OPEN,
                '',
                str_replace(BookmarkDefaultFormatter::SEARCH_HIGHLIGHT_CLOSE, '', $match[2])
            );
            return $match[1] . '[#' . $match[2] . '](' . $indexUrl . './add-tag/' . $cleanMatch . ')';
        };

        $descriptionLines = explode(PHP_EOL, $description);
        $descriptionOut = '';
        $codeBlockOn = false;
        $lineCount = 0;

        foreach ($descriptionLines as $descriptionLine) {
            // Detect line of code: starting with 4 spaces,
            // except lists which can start with +/*/- or `2.` after spaces.
            $codeLineOn = preg_match('/^    +(?=[^\+\*\-])(?=(?!\d\.).)/', $descriptionLine) > 0;
            // Detect and toggle block of code
            if (!$codeBlockOn) {
                $codeBlockOn = preg_match('/^```/', $descriptionLine) > 0;
            } elseif (preg_match('/^```/', $descriptionLine) > 0) {
                $codeBlockOn = false;
            }

            if (!$codeBlockOn && !$codeLineOn) {
                $descriptionLine = preg_replace_callback($regex, $replacement, $descriptionLine);
            }

            $descriptionOut .= $descriptionLine;
            if ($lineCount++ < count($descriptionLines) - 1) {
                $descriptionOut .= PHP_EOL;
            }
        }

        return $descriptionOut;
    }

    /**
     * Sanitize bookmark description HTML using HTMLPurifier.
     * Strips disallowed tags, event handlers, and non-whitelisted protocols.
     *
     * @param string $description input description text.
     *
     * @return string sanitized HTML.
     */
    protected function sanitizeHtml($description)
    {
        // Build a fresh config schema (not cached) so we can mutate it without
        // triggering HTMLPurifier's "config is finalised" error.
        $schema = HTMLPurifier_ConfigSchema::makeFromSerial();
        $config = new HTMLPurifier_Config($schema);

        // Disable the serializer cache so we don't need a writable temp dir.
        $config->set('Cache.DefinitionImpl', null);

        // Whitelist the exact set of elements and attributes that Markdown/
        // MarkdownExtra can emit. Anything not listed is stripped.
        //
        // Syntax: "element[allowed_attrs]" or just "element".
        // - Elements like p, br, ul, li, table cells have no attributes.
        // - a allows href, title, target, rel (standard link attributes).
        // - abbr allows title (the abbreviation definition).
        // - h1-h6, div, span allow class (for ParsedownExtra's {.class} syntax).
        $config->set('HTML.Allowed', implode(',', [
            'p[class],br,strong,em,code,span[class],hr',
            'h1[class],h2[class],h3[class],h4[class],h5[class],h6[class]',
            'a[href|title|target|rel]',
            'ul,ol,li,dl,dt,dd,blockquote,pre',
            'table,thead,tbody,tfoot,tr,th,td',
            'abbr[title],sub,sup,q,kbd,samp,var,tt,address',
            'div[class]',
        ]));

        // Build the allowed URI scheme list. Always include http/https/mailto/ftp,
        // then add any extras the admin configured (e.g. ssh, git).
        $schemes = ['http' => true, 'https' => true, 'mailto' => true, 'ftp' => true];
        foreach ($this->allowedProtocols as $protocol) {
            $schemes[$protocol] = true;
        }
        $config->set('URI.AllowedSchemes', $schemes);

        // Parse the HTML, strip everything not in the whitelist, and return the
        // cleaned string.
        return (new HTMLPurifier($config))->purify($description);
    }

    protected function reverseEscapedHtml($description)
    {
        return unescape($description);
    }
}
