<?php

declare(strict_types=1);

use Shaarli\Bookmark\Bookmark;
use Shaarli\Config\ConfigManager;
use Shaarli\Plugin\PluginManager;

function readitlater_register_routes(): array
{
    return [
        [
            'method' => 'GET',
            'route' => '/toggle-filter',
            'callable' => 'Shaarli\Plugin\ReadItLater\ReadItLaterController:toggleFilterBookmarkList',
        ],
        [
            'method' => 'GET',
            'route' => '/toggle/{id:[\d]+}',
            'callable' => 'Shaarli\Plugin\ReadItLater\ReadItLaterController:toggleBookmark',
        ]
    ];
}

/**
 * Includes: add plugin CSS file
 */
function hook_readitlater_render_includes(array $data, ConfigManager $conf): array
{
    if (!($data['_LOGGEDIN_'] ?? false) || $conf->get('resource.theme') !== 'default') {
        return $data;
    }

    $data['css_files'][] = PluginManager::$PLUGINS_PATH . '/readitlater/readitlater.default.css';

    return $data;
}

/**
 * Footer: add plugin JS file
 */
function hook_readitlater_render_footer(array $data, ConfigManager $conf): array
{
    if (!($data['_LOGGEDIN_'] ?? false) || $conf->get('resource.theme') !== 'default') {
        return $data;
    }

    $data['js_files'][] = PluginManager::$PLUGINS_PATH . '/readitlater/readitlater.default.js';

    return $data;
}

/**
 * Edit link: add the 'Read it later' checkbox only for bookmark creation.
 *            It doesn't seem useful to add it on edit mode, because it can be toggled from the linklist.
 */
function hook_readitlater_render_editlink(array $data, ConfigManager $conf): array
{
    if (!$data['link_is_new']) {
        return $data;
    }

    $default = filter_var($conf->get('plugins.READITLATER_DEFAULT_CHECK', false), FILTER_VALIDATE_BOOLEAN);

    // Load HTML into a string
    $html = file_get_contents(PluginManager::$PLUGINS_PATH . '/readitlater/readitlater_editlink.html');

    // Random ID for batch shaare (multiple inputs on the same page)
    $random = uniqid();

     // Replace value in HTML if it exists in $data
    $html = sprintf($html, $random, $default ? 'checked' : '', $random);

    // field_plugin
    $data['edit_link_plugin'][] = $html;

    return $data;
}

/**
 * Save link: if the flag is already defined, do nothing, otherwise rely on the checkbox value.
 */
function hook_readitlater_save_link(array $data): array
{
    if (array_key_exists('readitlater', $data['additional_content'] ?? [])) {
        return $data;
    }

    $data['additional_content']['readitlater'] = !!($_POST['readitlater'] ?? false);

    return $data;
}

/**
 * Linklist:
 *   - no effect for logged out users
 *   - if the flag is set to true, we add the readitlater class to format the bookmark
 *   - otherwise we only add the toggle button
 *   - also include a filter to display all bookmark to read
 */
function hook_readitlater_render_linklist(array $data, ConfigManager $conf): array
{
    if (!($data['_LOGGEDIN_'] ?? false)) {
        return $data;
    }

    $basePath = $data['_BASE_PATH_'] ?? __DIR__ . '/../../';
    $buttonHtml = file_get_contents(PluginManager::$PLUGINS_PATH . '/readitlater/readitlater_button.html');
    $toggleUrl = $basePath . '/plugin/readitlater/toggle/';

    // Display a toggle icon for each link and a label for unread links
    foreach ($data['links'] as &$link) {
        $isUnread = $link['additional_content']['readitlater'] ?? false;
        $link['link_plugin'][] = sprintf(
            $buttonHtml,
            $toggleUrl,
            $link['id'],
            $isUnread ? t('Mark as Read') : t('Read it later'),
            readitlater_get_icon($conf, $isUnread)
        );

        if ($isUnread) {
            $link['class'] = ($link['class'] ?? '') . ' readitlater-unread ';
        }
    }

    $data['action_plugin'][] = [
        'attr' => [
            'href' => $basePath . '/plugin/readitlater/toggle-filter',
            'title' => t('Filter ReadItLater bookmarks'),
        ],
        'on' => $_SESSION['readitlater-only'] ?? false,
        'html' => readitlater_get_icon($conf, true),
    ];

    return $data;
}

/**
 * If search through only readitlater entries is enabled, add custom filter.
 */
function hook_readitlater_filter_search_entry(Bookmark $bookmark, array $context): bool
{
    if (($_SESSION['readitlater-only'] ?? false) !== true) {
        return true;
    }

    return $bookmark->getAdditionalContentEntry('readitlater') === true;
}

/**
 * Get ForkAwesome icon for the default theme, failback on text.
 */
function readitlater_get_icon(ConfigManager $conf, bool $isUnread): string
{
    if ($conf->get('resource.theme') === 'default') {
        return '<i class="fa fa-eye' . ($isUnread ? '-slash' : '') . '" aria-hidden="true"></i>';
    } else {
        return $isUnread ? 'Mark as Read' : 'Read it later';
    }
}
