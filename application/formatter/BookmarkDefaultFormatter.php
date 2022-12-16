<?php

namespace Shaarli\Formatter;

use Shaarli\Bookmark\Bookmark;

/**
 * Class BookmarkDefaultFormatter
 *
 * Default bookmark formatter.
 * Escape values for HTML display and automatically add link to URL and hashtags.
 *
 * @package Shaarli\Formatter
 */
class BookmarkDefaultFormatter extends BookmarkFormatter
{
    public const SEARCH_HIGHLIGHT_OPEN = 'SHAARLI_O_HIGHLIGHT';
    public const SEARCH_HIGHLIGHT_CLOSE = 'SHAARLI_C_HIGHLIGHT';

    /**
     * @inheritdoc
     */
    protected function formatTitle($bookmark)
    {
        return escape($bookmark->getTitle());
    }

    /**
     * @inheritdoc
     */
    protected function formatTitleHtml($bookmark)
    {
        $title = $this->tokenizeSearchHighlightField(
            $bookmark->getTitle() ?? '',
            $bookmark->getAdditionalContentEntry('search_highlight')['title'] ?? []
        );

        return $this->replaceTokens(escape($title));
    }

    /**
     * @inheritdoc
     */
    protected function formatDescription($bookmark)
    {
        $indexUrl = ! empty($this->contextData['index_url']) ? $this->contextData['index_url'] : '';
        $description = $this->tokenizeSearchHighlightField(
            $bookmark->getDescription() ?? '',
            $bookmark->getAdditionalContentEntry('search_highlight')['description'] ?? []
        );
        $description = format_description(
            escape($description),
            $indexUrl,
            $this->conf->get('formatter_settings.autolink', true)
        );

        return $this->replaceTokens($description);
    }

    /**
     * @inheritdoc
     */
    protected function formatTagList($bookmark)
    {
        return escape(parent::formatTagList($bookmark));
    }

    /**
     * @inheritdoc
     */
    protected function formatTagListHtml($bookmark)
    {
        $tagsSeparator = $this->conf->get('general.tags_separator', ' ');
        if (empty($bookmark->getAdditionalContentEntry('search_highlight')['tags'])) {
            return $this->formatTagList($bookmark);
        }

        $tags = $this->tokenizeSearchHighlightField(
            $bookmark->getTagsString($tagsSeparator),
            $bookmark->getAdditionalContentEntry('search_highlight')['tags']
        );
        $tags = $this->filterTagList(tags_str2array($tags, $tagsSeparator));
        $tags = escape($tags);
        $tags = $this->replaceTokensArray($tags);

        return $tags;
    }

    /**
     * @inheritdoc
     */
    protected function formatTagString($bookmark)
    {
        return implode($this->conf->get('general.tags_separator'), $this->formatTagList($bookmark));
    }

    /**
     * @inheritdoc
     */
    protected function formatUrl($bookmark)
    {
        if ($bookmark->isNote() && isset($this->contextData['index_url'])) {
            return rtrim($this->contextData['index_url'], '/') . '/' . escape(ltrim($bookmark->getUrl(), '/'));
        }

        return escape($bookmark->getUrl());
    }

    /**
     * @inheritdoc
     */
    protected function formatRealUrl($bookmark)
    {
        if ($bookmark->isNote()) {
            if (isset($this->contextData['index_url'])) {
                $prefix = rtrim($this->contextData['index_url'], '/') . '/';
            }

            if (isset($this->contextData['base_path'])) {
                $prefix = rtrim($this->contextData['base_path'], '/') . '/';
            }

            return escape($prefix ?? '') . escape(ltrim($bookmark->getUrl() ?? '', '/'));
        }

        return escape($bookmark->getUrl());
    }

    /**
     * @inheritdoc
     */
    protected function formatUrlHtml($bookmark)
    {
        $url = $this->tokenizeSearchHighlightField(
            $bookmark->getUrl() ?? '',
            $bookmark->getAdditionalContentEntry('search_highlight')['url'] ?? []
        );

        return $this->replaceTokens(escape($url));
    }

    /**
     * @inheritdoc
     */
    protected function formatThumbnail($bookmark)
    {
        return escape($bookmark->getThumbnail());
    }

    /**
     * @inheritDoc
     */
    protected function formatAdditionalContent(Bookmark $bookmark): array
    {
        $additionalContent = parent::formatAdditionalContent($bookmark);

        unset($additionalContent['search_highlight']);

        return $additionalContent;
    }

    /**
     * Insert search highlight token in provided field content based on a list of search result positions
     *
     * @param string     $fieldContent
     * @param array|null $positions    List of of search results with 'start' and 'end' positions.
     *
     * @return string Updated $fieldContent.
     */
    protected function tokenizeSearchHighlightField(string $fieldContent, ?array $positions): string
    {
        if (empty($positions)) {
            return $fieldContent;
        }

        $insertedTokens = 0;
        $tokenLength = strlen(static::SEARCH_HIGHLIGHT_OPEN);
        foreach ($positions as $position) {
            $position = [
                'start' => $position['start'] + ($insertedTokens * $tokenLength),
                'end' => $position['end'] + ($insertedTokens * $tokenLength),
            ];

            $content = mb_substr($fieldContent, 0, $position['start']);
            $content .= static::SEARCH_HIGHLIGHT_OPEN;
            $content .= mb_substr($fieldContent, $position['start'], $position['end'] - $position['start']);
            $content .= static::SEARCH_HIGHLIGHT_CLOSE;
            $content .= mb_substr($fieldContent, $position['end']);

            $fieldContent = $content;

            $insertedTokens += 2;
        }

        return $fieldContent;
    }

    /**
     * Replace search highlight tokens with HTML highlighted span.
     *
     * @param string $fieldContent
     *
     * @return string updated content.
     */
    protected function replaceTokens(string $fieldContent): string
    {
        return str_replace(
            [static::SEARCH_HIGHLIGHT_OPEN, static::SEARCH_HIGHLIGHT_CLOSE],
            ['<span class="search-highlight">', '</span>'],
            $fieldContent
        );
    }

    /**
     * Apply replaceTokens to an array of content strings.
     *
     * @param string[] $fieldContents
     *
     * @return array
     */
    protected function replaceTokensArray(array $fieldContents): array
    {
        foreach ($fieldContents as &$entry) {
            $entry = $this->replaceTokens($entry);
        }

        return $fieldContents;
    }
}
