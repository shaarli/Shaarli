<?php

/**
 * When linklist is displayed, add play videos to header's toolbar.
 *
 * @param array $data - header data.
 * @return mixed - header data with playvideos toolbar item.
 */
function hook_playvideos_render_header($data) {
    if ($data['_PAGE_'] == Router::$PAGE_LINKLIST) {
        $data['buttons_toolbar'][] = file_get_contents(PluginManager::$PLUGINS_PATH . '/playvideos/playvideos.html');
    }

    return $data;
}

/**
 * When linklist is displayed, include playvideos JS files.
 *
 * @param array $data - footer data.
 * @return mixed - footer data with playvideos JS files added.
 */
function hook_playvideos_render_footer($data) {
    if ($data['_PAGE_'] == Router::$PAGE_LINKLIST) {
        $data['js_files'][] = PluginManager::$PLUGINS_PATH . '/playvideos/jquery-1.11.2.min.js';
        $data['js_files'][] = PluginManager::$PLUGINS_PATH . '/playvideos/youtube_playlist.js';
    }

    return $data;
}