<?php

/**
 * When linklist is displayed, add play videos to header's toolbar.
 *
 * @param array $data - header data.
 * @return mixed - header data with addlink toolbar item.
 */
function hook_addlink_toolbar_render_header($data) {
    if ($data['_PAGE_'] == Router::$PAGE_LINKLIST && $data['_LOGGEDIN_'] === true) {
        $data['fields_toolbar'][] = file_get_contents(PluginManager::$PLUGINS_PATH . '/addlink_toolbar/addlink_toolbar.html');
    }

    return $data;
}

/**
 * When link list is displayed, include markdown CSS.
 *
 * @param array $data - includes data.
 * @return mixed - includes data with markdown CSS file added.
 */
function hook_addlink_toolbar_render_includes($data) {
    if ($data['_PAGE_'] == Router::$PAGE_LINKLIST && $data['_LOGGEDIN_'] === true) {
        $data['css_files'][] = PluginManager::$PLUGINS_PATH . '/addlink_toolbar/addlink_toolbar.css';
    }

    return $data;
}