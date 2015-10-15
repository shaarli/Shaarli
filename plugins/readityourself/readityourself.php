<?php

// If we're talking about https://github.com/memiks/readityourself
// it seems kinda dead.
// Not tested.

// don't raise unnecessary warnings
if (is_file(PluginManager::$PLUGINS_PATH . '/readityourself/config.php')) {
    include PluginManager::$PLUGINS_PATH . '/readityourself/config.php';
}

if (!isset($GLOBALS['plugins']['READITYOUSELF_URL'])) {
    header('Content-Type: text/plain; charset=utf-8');
    echo 'ReadItYourself plugin error: '. PHP_EOL;
    echo '  Please copy "plugins/readityourself/config.php.dist" to config.php and configure your readityourself URL.'. PHP_EOL;
    echo '  You can also define "$GLOBALS[\'plugins\'][\'READITYOUSELF_URL\']" in your global Shaarli config.php file.';
    exit;
}

/**
 * Add readityourself icon to link_plugin when rendering linklist.
 *
 * @param $data - linklist data.
 * @return mixed - linklist data with readityourself plugin.
 */
function hook_readityourself_render_linklist($data) {
    $readityourself_html = file_get_contents(PluginManager::$PLUGINS_PATH . '/readityourself/readityourself.html');

    foreach ($data['links'] as &$value) {
        $readityourself = sprintf($readityourself_html, $GLOBALS['plugins']['READITYOUSELF_URL'], $value['url'], PluginManager::$PLUGINS_PATH);
        $value['link_plugin'][] = $readityourself;
    }

    return $data;
}
