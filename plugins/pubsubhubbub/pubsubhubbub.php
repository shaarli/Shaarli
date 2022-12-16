<?php

/**
 * PubSubHubbub plugin.
 *
 * PubSub is a protocol which fasten up RSS fetching:
 *   - Every time a new link is posted, Shaarli notify the hub.
 *   - The hub notify all feed subscribers that a new link has been posted.
 *   - Subscribers retrieve the new link.
 */

use pubsubhubbub\publisher\Publisher;
use Shaarli\Config\ConfigManager;
use Shaarli\Feed\FeedBuilder;
use Shaarli\Plugin\PluginManager;
use Shaarli\Render\TemplatePage;

/**
 * Plugin init function - set the hub to the default appspot one.
 *
 * @param ConfigManager $conf instance.
 */
function pubsubhubbub_init($conf)
{
    $hub = $conf->get('plugins.PUBSUBHUB_URL');
    if (empty($hub)) {
        // Default hub.
        $conf->set('plugins.PUBSUBHUB_URL', 'https://pubsubhubbub.appspot.com/');
    }
}


/**
 * Render feed hook.
 * Adds the hub URL in ATOM and RSS feed.
 *
 * @param array         $data Template data.
 * @param ConfigManager $conf instance.
 *
 * @return array updated template data.
 */
function hook_pubsubhubbub_render_feed($data, $conf)
{
    $feedType = $data['_PAGE_'] == TemplatePage::FEED_RSS ? FeedBuilder::$FEED_RSS : FeedBuilder::$FEED_ATOM;
    $template = file_get_contents(PluginManager::$PLUGINS_PATH . '/pubsubhubbub/hub.' . $feedType . '.xml');
    $data['feed_plugins_header'][] = sprintf($template, $conf->get('plugins.PUBSUBHUB_URL'));

    return $data;
}

$published = false;

/**
 * Save link hook.
 * Publish to the hub when a link is saved.
 *
 * @param array         $data Template data.
 * @param ConfigManager $conf instance.
 *
 * @return array unaltered data.
 */
function hook_pubsubhubbub_save_link($data, $conf)
{
    global $published;
    if ($published) {
        return $data;
    }

    $feeds = [
        index_url($_SERVER) . 'feed/atom',
        index_url($_SERVER) . 'feed/rss',
    ];

    $httpPost = function_exists('curl_version') ? false : 'nocurl_http_post';
    try {
        $p = new Publisher($conf->get('plugins.PUBSUBHUB_URL'));
        $p->publish_update($feeds, $httpPost);
        $published = true;
    } catch (Exception $e) {
        error_log(sprintf(t('Could not publish to PubSubHubbub: %s'), $e->getMessage()));
    }

    return $data;
}

/**
 * Http function used to post to the hub endpoint without cURL extension.
 *
 * @param  string $url        Hub endpoint.
 * @param  string $postString String to POST.
 *
 * @return bool
 *
 * @throws Exception An error occurred.
 */
function nocurl_http_post($url, $postString)
{
    $params = ['http' => [
        'method' => 'POST',
        'content' => $postString,
        'user_agent' => 'PubSubHubbub-Publisher-PHP/1.0',
    ]];

    $context = stream_context_create($params);
    $fp = @fopen($url, 'rb', false, $context);
    if (!$fp) {
        throw new Exception(sprintf(t('Could not post to %s'), $url));
    }
    $response = @stream_get_contents($fp);
    if ($response === false) {
        throw new Exception(sprintf(t('Bad response from the hub %s'), $url));
    }
    return $response;
}

/**
 * This function is never called, but contains translation calls for GNU gettext extraction.
 */
function pubsubhubbub_dummy_translation()
{
    // meta
    t('Enable PubSubHubbub feed publishing.');
}
