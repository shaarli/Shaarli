<?php

/**
 * Add archive.org icon to link_plugin when rendering linklist.
 *
 * @param $data - linklist data.
 * @return mixed - linklist data with archive.org plugin.
 */
function hook_archiveorg_render_linklist($data) {
    $archive_html = file_get_contents(PluginManager::$PLUGINS_PATH . '/archiveorg/archiveorg.html');

    foreach ($data['links'] as &$value) {
        $archive = sprintf($archive_html, $value['url']);
        $value['link_plugin'][] = $archive;
    }

    return $data;
}
