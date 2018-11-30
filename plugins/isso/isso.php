<?php

/**
 * Plugin Isso.
 */

use Shaarli\Config\ConfigManager;

/**
 * Display an error everywhere if the plugin is enabled without configuration.
 *
 * @param $conf ConfigManager instance
 *
 * @return mixed - linklist data with Isso plugin.
 */
function isso_init($conf)
{
    $issoUrl = $conf->get('plugins.ISSO_SERVER');
    if (empty($issoUrl)) {
        $error = t('Isso plugin error: '.
            'Please define the "ISSO_SERVER" setting in the plugin administration page.');
        return array($error);
    }
}

/**
 * Render linklist hook.
 * Will only display Isso comments on permalinks.
 *
 * @param $data array         List of links
 * @param $conf ConfigManager instance
 *
 * @return mixed - linklist data with Isso plugin.
 */
function hook_isso_render_linklist($data, $conf)
{
    $issoUrl = $conf->get('plugins.ISSO_SERVER');
    if (empty($issoUrl)) {
        return $data;
    }

    // Only display comments for permalinks.
    if (count($data['links']) == 1 && empty($data['search_tags']) && empty($data['search_term'])) {
        $link = reset($data['links']);
        $issoHtml = file_get_contents(PluginManager::$PLUGINS_PATH . '/isso/isso.html');

        $isso = sprintf($issoHtml, $issoUrl, $issoUrl, $link['id'], $link['id']);
        $data['plugin_end_zone'][] = $isso;
    } else {
        $button = '<span><a href="?%s#isso-thread">';
        // For the default theme we use a FontAwesome icon which is better than an image
        if ($conf->get('resource.theme') === 'default') {
            $button .= '<i class="linklist-plugin-icon fa fa-comment"></i>';
        } else {
            $button .= '<img class="linklist-plugin-icon" src="plugins/isso/comment.png" ';
            $button .= 'title="Comment on this shaare" alt="Comments" />';
        }
        $button .= '</a></span>';
        foreach ($data['links'] as &$value) {
            $commentLink = sprintf($button, $value['shorturl']);
            $value['link_plugin'][] = $commentLink;
        }
    }

    return $data;
}

/**
 * When linklist is displayed, include isso CSS file.
 *
 * @param array $data - header data.
 *
 * @return mixed - header data with isso CSS file added.
 */
function hook_isso_render_includes($data)
{
    if ($data['_PAGE_'] == Router::$PAGE_LINKLIST) {
        $data['css_files'][] = PluginManager::$PLUGINS_PATH . '/isso/isso.css';
    }

    return $data;
}

/**
 * This function is never called, but contains translation calls for GNU gettext extraction.
 */
function isso_dummy_translation()
{
    // meta
    t('Let visitor comment your shaares on permalinks with Isso.');
    t('Isso server URL (without \'http://\')');
}
