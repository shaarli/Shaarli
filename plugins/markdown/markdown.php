<?php

// Note: this is only a POC for now.

require_once 'Parsedown.php';

/**
 * Render shaare contents through Markdown parser.
 *
 * @param array $data - linklist data.
 * @return mixed - linklist data parsed in markdown (and converted to HTML).
 */
function hook_markdown_render_linklist($data) {
    $Parsedown = new Parsedown();

    foreach ($data['links'] as &$value) {
        $value['description'] = $Parsedown->setMarkupEscaped(false)->text($value['description']);
    }

    return $data;
}

/**
 * When link list is displayed, include markdown CSS.
 *
 * @param array $data - includes data.
 * @return mixed - includes data with markdown CSS file added.
 */
function hook_markdown_render_includes($data) {
    if ($data['_PAGE_'] == Router::$PAGE_LINKLIST) {
        $data['css_files'][] = PluginManager::$PLUGINS_PATH . '/markdown/markdown.css';
    }

    return $data;
}