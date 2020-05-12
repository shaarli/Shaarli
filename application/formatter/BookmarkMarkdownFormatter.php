<?php

namespace Shaarli\Formatter;

use Shaarli\Config\ConfigManager;

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
    const NO_MD_TAG = 'nomarkdown';

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

        $this->parsedown = new \Parsedown();
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

        $processedDescription = $bookmark->getDescription();
        $processedDescription = $this->filterProtocols($processedDescription);
        $processedDescription = $this->formatHashTags($processedDescription);
        $processedDescription = $this->reverseEscapedHtml($processedDescription);
        $processedDescription = $this->parsedown
            ->setMarkupEscaped($this->escape)
            ->setBreaksEnabled(true)
            ->text($processedDescription);
        $processedDescription = $this->sanitizeHtml($processedDescription);

        if (!empty($processedDescription)) {
            $processedDescription = '<div class="markdown">'. $processedDescription . '</div>';
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
                return ']('. $link.')';
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

        /*
         * To support unicode: http://stackoverflow.com/a/35498078/1484919
         * \p{Pc} - to match underscore
         * \p{N} - numeric character in any script
         * \p{L} - letter from any language
         * \p{Mn} - any non marking space (accents, umlauts, etc)
         */
        $regex = '/(^|\s)#([\p{Pc}\p{N}\p{L}\p{Mn}]+)/mui';
        $replacement = '$1[#$2]('. $indexUrl .'./add-tag/$2)';

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
                $descriptionLine = preg_replace($regex, $replacement, $descriptionLine);
            }

            $descriptionOut .= $descriptionLine;
            if ($lineCount++ < count($descriptionLines) - 1) {
                $descriptionOut .= PHP_EOL;
            }
        }

        return $descriptionOut;
    }

    /**
     * Remove dangerous HTML tags (tags, iframe, etc.).
     * Doesn't affect <code> content (already escaped by Parsedown).
     *
     * @param string $description input description text.
     *
     * @return string given string escaped.
     */
    protected function sanitizeHtml($description)
    {
        $escapeTags = array(
            'script',
            'style',
            'link',
            'iframe',
            'frameset',
            'frame',
        );
        foreach ($escapeTags as $tag) {
            $description = preg_replace_callback(
                '#<\s*'. $tag .'[^>]*>(.*</\s*'. $tag .'[^>]*>)?#is',
                function ($match) {
                    return escape($match[0]);
                },
                $description
            );
        }
        $description = preg_replace(
            '#(<[^>]+\s)on[a-z]*="?[^ "]*"?#is',
            '$1',
            $description
        );
        return $description;
    }

    protected function reverseEscapedHtml($description)
    {
        return unescape($description);
    }
}
