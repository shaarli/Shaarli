<?php

// don't raise unnecessary warnings
if (is_file(PluginManager::$PLUGINS_PATH . '/wallabag/config.php')) {
    include PluginManager::$PLUGINS_PATH . '/wallabag/config.php';
}

if (!isset($GLOBALS['plugins']['WALLABAG_URL'])) {
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Wallabag plugin error: '. PHP_EOL;
    echo '  Please copy "plugins/wallabag/config.php.dist" to config.php and configure your Wallabag URL.'. PHP_EOL;
    echo '  You can also define "$GLOBALS[\'plugins\'][\'WALLABAG_URL\']" in your global Shaarli config.php file.';
    exit;
}

/**
 * Add wallabag icon to link_plugin when rendering linklist.
 *
 * @param $data - linklist data.
 * @return mixed - linklist data with wallabag plugin.
 */
function hook_wallabag_render_linklist($data) {
    $wallabag_html = file_get_contents(PluginManager::$PLUGINS_PATH . '/wallabag/wallabag.html');

    foreach ($data['links'] as &$value) {
        $wallabag = sprintf($wallabag_html, $GLOBALS['plugins']['WALLABAG_URL'], $value['url'], PluginManager::$PLUGINS_PATH);
        $value['link_plugin'][] = $wallabag;
    }

    return $data;
}
