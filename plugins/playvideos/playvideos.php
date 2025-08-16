<?php

/**
 * Plugin PlayVideos
 *
 * Add a button in the toolbar allowing to watch all videos.
 * Note: this plugin adds jQuery.
 */

use Shaarli\Plugin\PluginManager;
use Shaarli\Render\TemplatePage;

/**
 * When linklist is displayed, add play videos to header's toolbar.
 *
 * @param array $data - header data.
 *
 * @return mixed - header data with playvideos toolbar item.
 */
function hook_playvideos_render_header($data)
{
    if ($data['_PAGE_'] == TemplatePage::LINKLIST) {
        $playvideo = [
            'attr' => [
                'href' => '#',
                'title' => t('Video player'),
                'id' => 'playvideos',
            ],
            'html' => '► ' . t('Play Videos')
        ];
        $data['buttons_toolbar'][] = $playvideo;
    }

    return $data;
}

/**
 * When linklist is displayed, include playvideos JS files.
 *
 * @param array $data - footer data.
 *
 * @return mixed - footer data with playvideos JS files added.
 */
function hook_playvideos_render_footer($data)
{
    if ($data['_PAGE_'] == TemplatePage::LINKLIST) {
        $data['js_files'][] = PluginManager::$PLUGINS_PATH . '/playvideos/youtube_playlist.js';
    }

    return $data;
}

/**
 * This function is never called, but contains translation calls for GNU gettext extraction.
 */
function playvideos_dummy_translation()
{
    // meta
    t('Add a button in the toolbar allowing to watch all videos.');
}
