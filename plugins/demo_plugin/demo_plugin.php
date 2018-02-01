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

use Shaarli\Config\ConfigManager;

/**
 * In the footer hook, there is a working example of a translation extension for Shaarli.
 *
 * The extension must be attached to a new translation domain (i.e. NOT 'shaarli').
 * Use case: any custom theme or non official plugin can use the translation system.
 *
 * See the documentation for more information.
 */
const EXT_TRANSLATION_DOMAIN = 'demo';

/*
 * This is not necessary, but it's easier if you don't want Poedit to mix up your translations.
 */
function demo_plugin_t($text, $nText = '', $nb = 1)
{
    return t($text, $nText, $nb, EXT_TRANSLATION_DOMAIN);
}

/**
 * Initialization function.
 * It will be called when the plugin is loaded.
 * This function can be used to return a list of initialization errors.
 *
 * @param $conf ConfigManager instance.
 *
 * @return array List of errors (optional).
 */
function demo_plugin_init($conf)
{
    $conf->get('toto', 'nope');

    if (! $conf->exists('translation.extensions.demo')) {
        // Custom translation with the domain 'demo'
        $conf->set('translation.extensions.demo', 'plugins/demo_plugin/languages/');
        $conf->write(true);
    }

    $errors[] = 'This a demo init error.';
    return $errors;
}

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
            /*
             * Links in toolbar:
             * A link is an array of its attributes (key="value"),
             * and a mandatory `html` key, which contains its value.
             */
            $button = array(
                'attr' => array (
                    'href' => '#',
                    'class' => 'mybutton',
                    'title' => 'hover me',
                ),
                'html' => 'DEMO buttons toolbar',
            );
            $data['buttons_toolbar'][] = $button;
        }

        /*
         * Add additional input fields in the tools.
         * A field is an array containing:
         *  [
         *      'form-attribute-1' => 'form attribute 1 value',
         *      'form-attribute-2' => 'form attribute 2 value',
         *      'inputs' => [
         *          [
         *              'input-1-attribute-1 => 'input 1 attribute 1 value',
         *              'input-1-attribute-2 => 'input 1 attribute 2 value',
         *          ],
         *          [
         *              'input-2-attribute-1 => 'input 2 attribute 1 value',
         *          ],
         *      ],
         *  ]
         * This example renders as:
         *      <form form-attribute-1="form attribute 1 value" form-attribute-2="form attribute 2 value">
         *          <input input-1-attribute-1="input 1 attribute 1 value" input-1-attribute-2="input 1 attribute 2 value">
         *          <input input-2-attribute-1="input 2 attribute 1 value">
         *      </form>
         */
        $form = array(
            'attr' => array(
                'method' => 'GET',
                'action' => '?',
                'class' => 'addform',
            ),
            'inputs' => array(
                array(
                    'type' => 'text',
                    'name' => 'demo',
                    'placeholder' => 'demo',
                )
            )
        );
        $data['fields_toolbar'][] = $form;
    }
    // Another button always displayed
    $button = array(
        'attr' => array(
            'href' => '#',
        ),
        'html' => 'Demo',
    );
    $data['buttons_toolbar'][] = $button;

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
 *   - endofpage
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
    $data['text'][] = '<br>'. demo_plugin_t('Shaarli is now enhanced by the awesome demo_plugin.');

    // Free elements at the end of the page.
    $data['endofpage'][] = '<marquee id="demo_marquee">' .
            'DEMO: it\'s 1999 all over again!' .
        '</marquee>';

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
    /*
     * Action links (action_plugin):
     * A link is an array of its attributes (key="value"),
     * and a mandatory `html` key, which contains its value.
     * It's also recommended to add key 'on' or 'off' for theme rendering.
     */
    $action = array(
        'attr' => array(
            'href' => '?up',
            'title' => 'Uppercase!',
        ),
        'html' => '←',
    );

    if (isset($_GET['up'])) {
        // Manipulate link data
        foreach ($data['links'] as &$value) {
            $value['description'] = strtoupper($value['description']);
            $value['title'] = strtoupper($value['title']);
        }
        $action['on'] = true;
    } else {
        $action['off'] = true;
    }
    $data['action_plugin'][] = $action;

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
    foreach ($data['linksToDisplay'] as &$value) {
        $value['formatedDescription'] .= ' ಠ_ಠ';
    }

    // Add plugin content at the end of each link
    foreach ($data['linksToDisplay'] as &$value) {
        $value['link_plugin'][] = 'DEMO';
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

/**
 * Execute render_feed hook.
 * Called with ATOM and RSS feed.
 *
 * Special data keys:
 *   - _PAGE_: current page
 *   - _LOGGEDIN_: true/false
 *
 * @param array $data data passed to plugin
 *
 * @return array altered $data.
 */
function hook_demo_plugin_render_feed($data)
{
    foreach ($data['links'] as &$link) {
        if ($data['_PAGE_'] == Router::$PAGE_FEED_ATOM) {
            $link['description'] .= ' - ATOM Feed' ;
        }
        elseif ($data['_PAGE_'] == Router::$PAGE_FEED_RSS) {
            $link['description'] .= ' - RSS Feed';
        }
    }
    return $data;
}

/**
 * This function is never called, but contains translation calls for GNU gettext extraction.
 */
function demo_dummy_translation()
{
    // meta
    t('A demo plugin covering all use cases for template designers and plugin developers.');
}
