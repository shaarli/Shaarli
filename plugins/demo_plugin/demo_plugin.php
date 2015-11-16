<?php
/**
 * Demo Plugin.
 *
 * This plugin try to cover Shaarli's plugin API entirely.
 * Can be used by plugin developper to make their own.
 */

/*
 * RENDER HEADER, INCLUDES, FOOTER
 *
 * Those hooks are called at every page rendering.
 * You can filter its execution by checking _PAGE_ value
 * and check user status with _LOGGEDIN_.
 */

/**
 * Hook render_header.
 * Executed on every page redering.
 *
 * Template placeholders:
 *   - buttons_toolbar
 *   - fields_toolbar
 *
 * @param array $data data passed to plugin
 *
 * @return array altered $data.
 */
function hook_demo_plugin_render_header($data)
{
    // Only execute when linklist is rendered.
    if ($data['_PAGE_'] == Router::$PAGE_LINKLIST) {

        // If loggedin
        if ($data['_LOGGEDIN_'] === true) {
            // Buttons in toolbar
            $data['buttons_toolbar'][] = '<li><a href="#">DEMO_buttons_toolbar</a></li>';
        }

        // Fields in toolbar
        $data['fields_toolbar'][] = 'DEMO_fields_toolbar';
    }

    return $data;
}

/**
 * Hook render_includes.
 * Executed on every page redering.
 *
 * Template placeholders:
 *   - css_files
 *
 * Data:
 *   - _PAGE_: current page
 *   - _LOGGEDIN_: true/false
 *
 * @param array $data data passed to plugin
 *
 * @return array altered $data.
 */
function hook_demo_plugin_render_includes($data)
{
    // List of plugin's CSS files.
    // Note that you just need to specify CSS path.
    $data['css_files'][] = PluginManager::$PLUGINS_PATH . '/demo_plugin/custom_demo.css';

    return $data;
}

/**
 * Hook render_footer.
 * Executed on every page redering.
 *
 * Template placeholders:
 *   - text
 *   - js_files
 *
 * Data:
 *   - _PAGE_: current page
 *   - _LOGGEDIN_: true/false
 *
 * @param array $data data passed to plugin
 *
 * @return array altered $data.
 */
function hook_demo_plugin_render_footer($data)
{
    // footer text
    $data['text'][] = 'Shaarli is now enhanced by the awesome demo_plugin.';

    // List of plugin's JS files.
    // Note that you just need to specify CSS path.
    $data['js_files'][] = PluginManager::$PLUGINS_PATH . '/demo_plugin/demo_plugin.js';

    return $data;
}

/*
 * SPECIFIC PAGES
 */

/**
 * Hook render_linklist.
 *
 * Template placeholders:
 *   - action_plugin: next to 'private only' button.
 *   - plugin_start_zone: page start
 *   - plugin_end_zone: page end
 *   - link_plugin: icons below each links.
 *
 * Data:
 *   - _LOGGEDIN_: true/false
 *
 * @param array $data data passed to plugin
 *
 * @return array altered $data.
 */
function hook_demo_plugin_render_linklist($data)
{
    // action_plugin
    $data['action_plugin'][] = '<div class="upper_plugin_demo"><a href="?up" title="Uppercase!">←</a></div>';

    if (isset($_GET['up'])) {
        // Manipulate link data
        foreach ($data['links'] as &$value) {
            $value['description'] = strtoupper($value['description']);
            $value['title'] = strtoupper($value['title']);
        }
    }

    // link_plugin (for each link)
    foreach ($data['links'] as &$value) {
        $value['link_plugin'][] = ' DEMO \o/';
    }

    // plugin_start_zone
    $data['plugin_start_zone'][] = '<center>BEFORE</center>';
    // plugin_start_zone
    $data['plugin_end_zone'][] = '<center>AFTER</center>';

    return $data;
}

/**
 * Hook render_editlink.
 *
 * Template placeholders:
 *   - field_plugin: add link fields after tags.
 *
 * @param array $data data passed to plugin
 *
 * @return array altered $data.
 */
function hook_demo_plugin_render_editlink($data)
{
    // Load HTML into a string
    $html = file_get_contents(PluginManager::$PLUGINS_PATH .'/demo_plugin/field.html');

    // replace value in HTML if it exists in $data
    if (!empty($data['link']['stuff'])) {
        $html = sprintf($html, $data['link']['stuff']);
    } else {
        $html = sprintf($html, '');
    }

    // field_plugin
    $data['edit_link_plugin'][] = $html;

    return $data;
}

/**
 * Hook render_tools.
 *
 * Template placeholders:
 *   - tools_plugin: after other tools.
 *
 * @param array $data data passed to plugin
 *
 * @return array altered $data.
 */
function hook_demo_plugin_render_tools($data)
{
    // field_plugin
    $data['tools_plugin'][] = 'tools_plugin';

    return $data;
}

/**
 * Hook render_picwall.
 *
 * Template placeholders:
 *   - plugin_start_zone: page start.
 *   - plugin_end_zone: page end.
 *
 * Data:
 *   - _LOGGEDIN_: true/false
 *
 * @param array $data data passed to plugin
 *
 * @return array altered $data.
 */
function hook_demo_plugin_render_picwall($data)
{
    // plugin_start_zone
    $data['plugin_start_zone'][] = '<center>BEFORE</center>';
    // plugin_end_zone
    $data['plugin_end_zone'][] = '<center>AFTER</center>';

    return $data;
}

/**
 * Hook render_tagcloud.
 *
 * Template placeholders:
 *   - plugin_start_zone: page start.
 *   - plugin_end_zone: page end.
 *
 * Data:
 *   - _LOGGEDIN_: true/false
 *
 * @param array $data data passed to plugin
 *
 * @return array altered $data.
 */
function hook_demo_plugin_render_tagcloud($data)
{
    // plugin_start_zone
    $data['plugin_start_zone'][] = '<center>BEFORE</center>';
    // plugin_end_zone
    $data['plugin_end_zone'][] = '<center>AFTER</center>';

    return $data;
}

/**
 * Hook render_daily.
 *
 * Template placeholders:
 *   - plugin_start_zone: page start.
 *   - plugin_end_zone: page end.
 *
 * Data:
 *   - _LOGGEDIN_: true/false
 *
 * @param array $data data passed to plugin
 *
 * @return array altered $data.
 */
function hook_demo_plugin_render_daily($data)
{
    // plugin_start_zone
    $data['plugin_start_zone'][] = '<center>BEFORE</center>';
    // plugin_end_zone
    $data['plugin_end_zone'][] = '<center>AFTER</center>';


    // Manipulate columns data
    foreach ($data['cols'] as &$value) {
        foreach ($value as &$value2) {
            $value2['formatedDescription'] .= ' ಠ_ಠ';
        }
    }

    // Add plugin content at the end of each link
    foreach ($data['cols'] as &$value) {
        foreach ($value as &$value2) {
            $value2['link_plugin'][] = 'DEMO';
        }
    }

    return $data;
}

/*
 * DATA SAVING HOOK.
 */

/**
 * Hook savelink.
 *
 * Triggered when a link is save (new or edit).
 * All new links now contain a 'stuff' value.
 *
 * @param array $data contains the new link data.
 *
 * @return array altered $data.
 */
function hook_demo_plugin_save_link($data)
{

    // Save stuff added in editlink field
    if (!empty($_POST['lf_stuff'])) {
        $data['stuff'] = escape($_POST['lf_stuff']);
    }

    return $data;
}

/**
 * Hook delete_link.
 *
 * Triggered when a link is deleted.
 *
 * @param array $data contains the link to be deleted.
 *
 * @return array altered data.
 */
function hook_demo_plugin_delete_link($data)
{
    if (strpos($data['url'], 'youtube.com') !== false) {
        exit('You can not delete a YouTube link. Don\'t ask.');
    }
}