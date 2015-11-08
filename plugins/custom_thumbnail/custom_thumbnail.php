<?php
/**
 * Custom thumbnail Plugin.
 *
 * This plugin adds a field in the Edit Link page to provide custom URL for
 * the image used as link thumbnail.
 * 
 */

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

function hook_custom_thumbnail_render_editlink($data)
{
    $thumb_url = isset($data['link']['thumb']) ? htmlspecialchars($data['link']['thumb']) : "";
    $html = " 	 <label for='lf_thumb'><i>Custom thumbnail</i></label><br>\n". 
           "    <input type='text' name='lf_thumb' value='$thumb_url' style='width:100%'><br><br>\n";

    // field_plugin
    $data['edit_link_plugin'][] = $html;

    return $data;
}