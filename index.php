<?php
/**
 * Shaarli - The personal, minimalist, super-fast, database free, bookmarking service.
 *
 * Friendly fork by the Shaarli community:
 *  - https://github.com/shaarli/Shaarli
 *
 * Original project by sebsauvage.net:
 *  - http://sebsauvage.net/wiki/doku.php?id=php:shaarli
 *  - https://github.com/sebsauvage/Shaarli
 *
 * Licence: http://www.opensource.org/licenses/zlib-license.php
 *
 * Requires: PHP 5.5.x
 */

// Set 'UTC' as the default timezone if it is not defined in php.ini
// See http://php.net/manual/en/datetime.configuration.php#ini.date.timezone
if (date_default_timezone_get() == '') {
    date_default_timezone_set('UTC');
}

/*
 * PHP configuration
 */

// http://server.com/x/shaarli --> /shaarli/
define('WEB_PATH', substr($_SERVER['REQUEST_URI'], 0, 1+strrpos($_SERVER['REQUEST_URI'], '/', 0)));

// High execution time in case of problematic imports/exports.
ini_set('max_input_time','60');

// Try to set max upload file size and read
ini_set('memory_limit', '128M');
ini_set('post_max_size', '16M');
ini_set('upload_max_filesize', '16M');

// See all error except warnings
error_reporting(E_ALL^E_WARNING);
// See all errors (for debugging only)
//error_reporting(-1);


// 3rd-party libraries
if (! file_exists(__DIR__ . '/vendor/autoload.php')) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "Error: missing Composer configuration\n\n"
        ."If you installed Shaarli through Git or using the development branch,\n"
        ."please refer to the installation documentation to install PHP"
        ." dependencies using Composer:\n"
        ."- https://shaarli.readthedocs.io/en/master/Server-configuration/\n"
        ."- https://shaarli.readthedocs.io/en/master/Download-and-Installation/";
    exit;
}
require_once 'inc/rain.tpl.class.php';
require_once __DIR__ . '/vendor/autoload.php';

// Shaarli library
require_once 'application/ApplicationUtils.php';
require_once 'application/Cache.php';
require_once 'application/CachedPage.php';
require_once 'application/config/ConfigPlugin.php';
require_once 'application/FeedBuilder.php';
require_once 'application/FileUtils.php';
require_once 'application/History.php';
require_once 'application/HttpUtils.php';
require_once 'application/LinkDB.php';
require_once 'application/LinkFilter.php';
require_once 'application/LinkUtils.php';
require_once 'application/NetscapeBookmarkUtils.php';
require_once 'application/PageBuilder.php';
require_once 'application/TimeZone.php';
require_once 'application/Url.php';
require_once 'application/Utils.php';
require_once 'application/PluginManager.php';
require_once 'application/Router.php';
require_once 'application/Updater.php';
use \Shaarli\Config\ConfigManager;
use \Shaarli\Languages;
use \Shaarli\Security\LoginManager;
use \Shaarli\Security\SessionManager;
use \Shaarli\ThemeUtils;
use \Shaarli\Thumbnailer;

// Ensure the PHP version is supported
try {
    ApplicationUtils::checkPHPVersion('5.5', PHP_VERSION);
} catch(Exception $exc) {
    header('Content-Type: text/plain; charset=utf-8');
    echo $exc->getMessage();
    exit;
}

define('SHAARLI_VERSION', ApplicationUtils::getVersion(__DIR__ .'/'. ApplicationUtils::$VERSION_FILE));

// Force cookie path (but do not change lifetime)
$cookie = session_get_cookie_params();
$cookiedir = '';
if (dirname($_SERVER['SCRIPT_NAME']) != '/') {
    $cookiedir = dirname($_SERVER["SCRIPT_NAME"]).'/';
}
// Set default cookie expiration and path.
session_set_cookie_params($cookie['lifetime'], $cookiedir, $_SERVER['SERVER_NAME']);
// Set session parameters on server side.
// Use cookies to store session.
ini_set('session.use_cookies', 1);
// Force cookies for session (phpsessionID forbidden in URL).
ini_set('session.use_only_cookies', 1);
// Prevent PHP form using sessionID in URL if cookies are disabled.
ini_set('session.use_trans_sid', false);

session_name('shaarli');
// Start session if needed (Some server auto-start sessions).
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Regenerate session ID if invalid or not defined in cookie.
if (isset($_COOKIE['shaarli']) && !SessionManager::checkId($_COOKIE['shaarli'])) {
    session_regenerate_id(true);
    $_COOKIE['shaarli'] = session_id();
}

$conf = new ConfigManager();
$sessionManager = new SessionManager($_SESSION, $conf);
$loginManager = new LoginManager($GLOBALS, $conf, $sessionManager);
$loginManager->generateStaySignedInToken($_SERVER['REMOTE_ADDR']);
$clientIpId = client_ip_id($_SERVER);

// LC_MESSAGES isn't defined without php-intl, in this case use LC_COLLATE locale instead.
if (! defined('LC_MESSAGES')) {
    define('LC_MESSAGES', LC_COLLATE);
}

// Sniff browser language and set date format accordingly.
if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
    autoLocale($_SERVER['HTTP_ACCEPT_LANGUAGE']);
}

new Languages(setlocale(LC_MESSAGES, 0), $conf);

$conf->setEmpty('general.timezone', date_default_timezone_get());
$conf->setEmpty('general.title', t('Shared links on '). escape(index_url($_SERVER)));
RainTPL::$tpl_dir = $conf->get('resource.raintpl_tpl').'/'.$conf->get('resource.theme').'/'; // template directory
RainTPL::$cache_dir = $conf->get('resource.raintpl_tmp'); // cache directory

$pluginManager = new PluginManager($conf);
$pluginManager->load($conf->get('general.enabled_plugins'));

date_default_timezone_set($conf->get('general.timezone', 'UTC'));

ob_start();  // Output buffering for the page cache.

// Prevent caching on client side or proxy: (yes, it's ugly)
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (! is_file($conf->getConfigFileExt())) {
    // Ensure Shaarli has proper access to its resources
    $errors = ApplicationUtils::checkResourcePermissions($conf);

    if ($errors != array()) {
        $message = '<p>'. t('Insufficient permissions:') .'</p><ul>';

        foreach ($errors as $error) {
            $message .= '<li>'.$error.'</li>';
        }
        $message .= '</ul>';

        header('Content-Type: text/html; charset=utf-8');
        echo $message;
        exit;
    }

    // Display the installation form if no existing config is found
    install($conf, $sessionManager, $loginManager);
}

$loginManager->checkLoginState($_COOKIE, $clientIpId);

/**
 * Adapter function to ensure compatibility with third-party templates
 *
 * @see https://github.com/shaarli/Shaarli/pull/1086
 *
 * @return bool true when the user is logged in, false otherwise
 */
function isLoggedIn()
{
    global $loginManager;
    return $loginManager->isLoggedIn();
}


// ------------------------------------------------------------------------------------------
// Process login form: Check if login/password is correct.
if (isset($_POST['login'])) {
    if (! $loginManager->canLogin($_SERVER)) {
        die(t('I said: NO. You are banned for the moment. Go away.'));
    }
    if (isset($_POST['password'])
        && $sessionManager->checkToken($_POST['token'])
        && $loginManager->checkCredentials($_SERVER['REMOTE_ADDR'], $clientIpId, $_POST['login'], $_POST['password'])
    ) {
        $loginManager->handleSuccessfulLogin($_SERVER);

        $cookiedir = '';
        if (dirname($_SERVER['SCRIPT_NAME']) != '/') {
            // Note: Never forget the trailing slash on the cookie path!
            $cookiedir = dirname($_SERVER["SCRIPT_NAME"]) . '/';
        }

        if (!empty($_POST['longlastingsession'])) {
            // Keep the session cookie even after the browser closes
            $sessionManager->setStaySignedIn(true);
            $expirationTime = $sessionManager->extendSession();

            setcookie(
                $loginManager::$STAY_SIGNED_IN_COOKIE,
                $loginManager->getStaySignedInToken(),
                $expirationTime,
                WEB_PATH
            );

        } else {
            // Standard session expiration (=when browser closes)
            $expirationTime = 0;
        }

        // Send cookie with the new expiration date to the browser
        session_set_cookie_params($expirationTime, $cookiedir, $_SERVER['SERVER_NAME']);
        session_regenerate_id(true);

        // Optional redirect after login:
        if (isset($_GET['post'])) {
            $uri = '?post='. urlencode($_GET['post']);
            foreach (array('description', 'source', 'title', 'tags') as $param) {
                if (!empty($_GET[$param])) {
                    $uri .= '&'.$param.'='.urlencode($_GET[$param]);
                }
            }
            header('Location: '. $uri);
            exit;
        }

        if (isset($_GET['edit_link'])) {
            header('Location: ?edit_link='. escape($_GET['edit_link']));
            exit;
        }

        if (isset($_POST['returnurl'])) {
            // Prevent loops over login screen.
            if (strpos($_POST['returnurl'], 'do=login') === false) {
                header('Location: '. generateLocation($_POST['returnurl'], $_SERVER['HTTP_HOST']));
                exit;
            }
        }
        header('Location: ?'); exit;
    } else {
        $loginManager->handleFailedLogin($_SERVER);
        $redir = '&username='. urlencode($_POST['login']);
        if (isset($_GET['post'])) {
            $redir .= '&post=' . urlencode($_GET['post']);
            foreach (array('description', 'source', 'title', 'tags') as $param) {
                if (!empty($_GET[$param])) {
                    $redir .= '&' . $param . '=' . urlencode($_GET[$param]);
                }
            }
        }
        // Redirect to login screen.
        echo '<script>alert("'. t("Wrong login/password.") .'");document.location=\'?do=login'.$redir.'\';</script>';
        exit;
    }
}

// ------------------------------------------------------------------------------------------
// Token management for XSRF protection
// Token should be used in any form which acts on data (create,update,delete,import...).
if (!isset($_SESSION['tokens'])) $_SESSION['tokens']=array();  // Token are attached to the session.

/**
 * Daily RSS feed: 1 RSS entry per day giving all the links on that day.
 * Gives the last 7 days (which have links).
 * This RSS feed cannot be filtered.
 *
 * @param ConfigManager $conf         Configuration Manager instance
 * @param LoginManager  $loginManager LoginManager instance
 */
function showDailyRSS($conf, $loginManager) {
    // Cache system
    $query = $_SERVER['QUERY_STRING'];
    $cache = new CachedPage(
        $conf->get('config.PAGE_CACHE'),
        page_url($_SERVER),
        startsWith($query,'do=dailyrss') && !$loginManager->isLoggedIn()
    );
    $cached = $cache->cachedVersion();
    if (!empty($cached)) {
        echo $cached;
        exit;
    }

    // If cached was not found (or not usable), then read the database and build the response:
    // Read links from database (and filter private links if used it not logged in).
    $LINKSDB = new LinkDB(
        $conf->get('resource.datastore'),
        $loginManager->isLoggedIn(),
        $conf->get('privacy.hide_public_links'),
        $conf->get('redirector.url'),
        $conf->get('redirector.encode_url')
    );

    /* Some Shaarlies may have very few links, so we need to look
       back in time until we have enough days ($nb_of_days).
    */
    $nb_of_days = 7; // We take 7 days.
    $today = date('Ymd');
    $days = array();

    foreach ($LINKSDB as $link) {
        $day = $link['created']->format('Ymd'); // Extract day (without time)
        if (strcmp($day, $today) < 0) {
            if (empty($days[$day])) {
                $days[$day] = array();
            }
            $days[$day][] = $link;
        }

        if (count($days) > $nb_of_days) {
            break; // Have we collected enough days?
        }
    }

    // Build the RSS feed.
    header('Content-Type: application/rss+xml; charset=utf-8');
    $pageaddr = escape(index_url($_SERVER));
    echo '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0">';
    echo '<channel>';
    echo '<title>Daily - '. $conf->get('general.title') . '</title>';
    echo '<link>'. $pageaddr .'</link>';
    echo '<description>Daily shared links</description>';
    echo '<language>en-en</language>';
    echo '<copyright>'. $pageaddr .'</copyright>'. PHP_EOL;

    // For each day.
    foreach ($days as $day => $links) {
        $dayDate = DateTime::createFromFormat(LinkDB::LINK_DATE_FORMAT, $day.'_000000');
        $absurl = escape(index_url($_SERVER).'?do=daily&day='.$day);  // Absolute URL of the corresponding "Daily" page.

        // We pre-format some fields for proper output.
        foreach ($links as &$link) {
            $link['formatedDescription'] = format_description(
                $link['description'],
                $conf->get('redirector.url'),
                $conf->get('redirector.encode_url')
            );
            $link['timestamp'] = $link['created']->getTimestamp();
            if (startsWith($link['url'], '?')) {
                $link['url'] = index_url($_SERVER) . $link['url'];  // make permalink URL absolute
            }
        }

        // Then build the HTML for this day:
        $tpl = new RainTPL;
        $tpl->assign('title', $conf->get('general.title'));
        $tpl->assign('daydate', $dayDate->getTimestamp());
        $tpl->assign('absurl', $absurl);
        $tpl->assign('links', $links);
        $tpl->assign('rssdate', escape($dayDate->format(DateTime::RSS)));
        $tpl->assign('hide_timestamps', $conf->get('privacy.hide_timestamps', false));
        $tpl->assign('index_url', $pageaddr);
        $html = $tpl->draw('dailyrss', true);

        echo $html . PHP_EOL;
    }
    echo '</channel></rss><!-- Cached version of '. escape(page_url($_SERVER)) .' -->';

    $cache->cache(ob_get_contents());
    ob_end_flush();
    exit;
}

/**
 * Show the 'Daily' page.
 *
 * @param PageBuilder   $pageBuilder   Template engine wrapper.
 * @param LinkDB        $LINKSDB       LinkDB instance.
 * @param ConfigManager $conf          Configuration Manager instance.
 * @param PluginManager $pluginManager Plugin Manager instance.
 * @param LoginManager  $loginManager  Login Manager instance
 */
function showDaily($pageBuilder, $LINKSDB, $conf, $pluginManager, $loginManager)
{
    $day = date('Ymd', strtotime('-1 day')); // Yesterday, in format YYYYMMDD.
    if (isset($_GET['day'])) {
      $day = $_GET['day'];
    }

    $days = $LINKSDB->days();
    $i = array_search($day, $days);
    if ($i === false && count($days)) {
        // no links for day, but at least one day with links
        $i = count($days) - 1;
        $day = $days[$i];
    }
    $previousday = '';
    $nextday = '';

    if ($i !== false) {
        if ($i >= 1) {
             $previousday=$days[$i - 1];
        }
        if ($i < count($days) - 1) {
          $nextday = $days[$i + 1];
        }
    }
    try {
        $linksToDisplay = $LINKSDB->filterDay($day);
    } catch (Exception $exc) {
        error_log($exc);
        $linksToDisplay = array();
    }

    // We pre-format some fields for proper output.
    foreach($linksToDisplay as $key => $link) {
        $taglist = explode(' ',$link['tags']);
        uasort($taglist, 'strcasecmp');
        $linksToDisplay[$key]['taglist']=$taglist;
        $linksToDisplay[$key]['formatedDescription'] = format_description(
            $link['description'],
            $conf->get('redirector.url'),
            $conf->get('redirector.encode_url')
        );
        $linksToDisplay[$key]['timestamp'] =  $link['created']->getTimestamp();
    }

    $dayDate = DateTime::createFromFormat(LinkDB::LINK_DATE_FORMAT, $day.'_000000');
    $data = array(
        'pagetitle' => $conf->get('general.title') .' - '. format_date($dayDate, false),
        'linksToDisplay' => $linksToDisplay,
        'day' => $dayDate->getTimestamp(),
        'dayDate' => $dayDate,
        'previousday' => $previousday,
        'nextday' => $nextday,
    );

    /* Hook is called before column construction so that plugins don't have
       to deal with columns. */
    $pluginManager->executeHooks('render_daily', $data, array('loggedin' => $loginManager->isLoggedIn()));

    /* We need to spread the articles on 3 columns.
       I did not want to use a JavaScript lib like http://masonry.desandro.com/
       so I manually spread entries with a simple method: I roughly evaluate the
       height of a div according to title and description length.
    */
    $columns = array(array(), array(), array()); // Entries to display, for each column.
    $fill = array(0, 0, 0);  // Rough estimate of columns fill.
    foreach($data['linksToDisplay'] as $key => $link) {
        // Roughly estimate length of entry (by counting characters)
        // Title: 30 chars = 1 line. 1 line is 30 pixels height.
        // Description: 836 characters gives roughly 342 pixel height.
        // This is not perfect, but it's usually OK.
        $length = strlen($link['title']) + (342 * strlen($link['description'])) / 836;
        if ($link['thumbnail']) {
          $length += 100; // 1 thumbnails roughly takes 100 pixels height.
        }
        // Then put in column which is the less filled:
        $smallest = min($fill); // find smallest value in array.
        $index = array_search($smallest, $fill); // find index of this smallest value.
        array_push($columns[$index], $link); // Put entry in this column.
        $fill[$index] += $length;
    }

    $data['cols'] = $columns;

    foreach ($data as $key => $value) {
        $pageBuilder->assign($key, $value);
    }

    $pageBuilder->assign('pagetitle', t('Daily') .' - '. $conf->get('general.title', 'Shaarli'));
    $pageBuilder->renderPage('daily');
    exit;
}

/**
 * Renders the linklist
 *
 * @param pageBuilder   $PAGE    pageBuilder instance.
 * @param LinkDB        $LINKSDB LinkDB instance.
 * @param ConfigManager $conf    Configuration Manager instance.
 * @param PluginManager $pluginManager Plugin Manager instance.
 */
function showLinkList($PAGE, $LINKSDB, $conf, $pluginManager, $loginManager) {
    buildLinkList($PAGE,$LINKSDB, $conf, $pluginManager, $loginManager);
    $PAGE->renderPage('linklist');
}

/**
 * Render HTML page (according to URL parameters and user rights)
 *
 * @param ConfigManager  $conf           Configuration Manager instance.
 * @param PluginManager  $pluginManager  Plugin Manager instance,
 * @param LinkDB         $LINKSDB
 * @param History        $history        instance
 * @param SessionManager $sessionManager SessionManager instance
 * @param LoginManager   $loginManager   LoginManager instance
 */
function renderPage($conf, $pluginManager, $LINKSDB, $history, $sessionManager, $loginManager)
{
    $updater = new Updater(
        read_updates_file($conf->get('resource.updates')),
        $LINKSDB,
        $conf,
        $loginManager->isLoggedIn(),
        $_SESSION
    );
    try {
        $newUpdates = $updater->update();
        if (! empty($newUpdates)) {
            write_updates_file(
                $conf->get('resource.updates'),
                $updater->getDoneUpdates()
            );
        }
    }
    catch(Exception $e) {
        die($e->getMessage());
    }

    $PAGE = new PageBuilder($conf, $_SESSION, $LINKSDB, $sessionManager->generateToken(), $loginManager->isLoggedIn());
    $PAGE->assign('linkcount', count($LINKSDB));
    $PAGE->assign('privateLinkcount', count_private($LINKSDB));
    $PAGE->assign('plugin_errors', $pluginManager->getErrors());

    // Determine which page will be rendered.
    $query = (isset($_SERVER['QUERY_STRING'])) ? $_SERVER['QUERY_STRING'] : '';
    $targetPage = Router::findPage($query, $_GET, $loginManager->isLoggedIn());

    if (
        // if the user isn't logged in
        !$loginManager->isLoggedIn() &&
        // and Shaarli doesn't have public content...
        $conf->get('privacy.hide_public_links') &&
        // and is configured to enforce the login
        $conf->get('privacy.force_login') &&
        // and the current page isn't already the login page
        $targetPage !== Router::$PAGE_LOGIN &&
        // and the user is not requesting a feed (which would lead to a different content-type as expected)
        $targetPage !== Router::$PAGE_FEED_ATOM &&
        $targetPage !== Router::$PAGE_FEED_RSS
    ) {
        // force current page to be the login page
        $targetPage = Router::$PAGE_LOGIN;
    }

    // Call plugin hooks for header, footer and includes, specifying which page will be rendered.
    // Then assign generated data to RainTPL.
    $common_hooks = array(
        'includes',
        'header',
        'footer',
    );

    foreach($common_hooks as $name) {
        $plugin_data = array();
        $pluginManager->executeHooks('render_' . $name, $plugin_data,
            array(
                'target' => $targetPage,
                'loggedin' => $loginManager->isLoggedIn()
            )
        );
        $PAGE->assign('plugins_' . $name, $plugin_data);
    }

    // -------- Display login form.
    if ($targetPage == Router::$PAGE_LOGIN)
    {
        if ($conf->get('security.open_shaarli')) { header('Location: ?'); exit; }  // No need to login for open Shaarli
        if (isset($_GET['username'])) {
            $PAGE->assign('username', escape($_GET['username']));
        }
        $PAGE->assign('returnurl',(isset($_SERVER['HTTP_REFERER']) ? escape($_SERVER['HTTP_REFERER']):''));
        // add default state of the 'remember me' checkbox
        $PAGE->assign('remember_user_default', $conf->get('privacy.remember_user_default'));
        $PAGE->assign('user_can_login', $loginManager->canLogin($_SERVER));
        $PAGE->assign('pagetitle', t('Login') .' - '. $conf->get('general.title', 'Shaarli'));
        $PAGE->renderPage('loginform');
        exit;
    }
    // -------- User wants to logout.
    if (isset($_SERVER['QUERY_STRING']) && startsWith($_SERVER['QUERY_STRING'], 'do=logout'))
    {
        invalidateCaches($conf->get('resource.page_cache'));
        $sessionManager->logout();
        setcookie(LoginManager::$STAY_SIGNED_IN_COOKIE, 'false', 0, WEB_PATH);
        header('Location: ?');
        exit;
    }

    // -------- Picture wall
    if ($targetPage == Router::$PAGE_PICWALL)
    {
        $PAGE->assign('pagetitle', t('Picture wall') .' - '. $conf->get('general.title', 'Shaarli'));
        if (! $conf->get('thumbnails.mode', Thumbnailer::MODE_NONE) === Thumbnailer::MODE_NONE) {
            $PAGE->assign('linksToDisplay', []);
            $PAGE->renderPage('picwall');
            exit;
        }

        // Optionally filter the results:
        $links = $LINKSDB->filterSearch($_GET);
        $linksToDisplay = array();

        // Get only links which have a thumbnail.
        // Note: we do not retrieve thumbnails here, the request is too heavy.
        foreach($links as $key => $link)
        {
            if (isset($link['thumbnail']) && $link['thumbnail'] !== false) {
                $linksToDisplay[] = $link; // Add to array.
            }
        }

        $data = array(
            'linksToDisplay' => $linksToDisplay,
        );
        $pluginManager->executeHooks('render_picwall', $data, array('loggedin' => $loginManager->isLoggedIn()));

        foreach ($data as $key => $value) {
            $PAGE->assign($key, $value);
        }


        $PAGE->renderPage('picwall');
        exit;
    }

    // -------- Tag cloud
    if ($targetPage == Router::$PAGE_TAGCLOUD)
    {
        $visibility = ! empty($_SESSION['visibility']) ? $_SESSION['visibility'] : '';
        $filteringTags = isset($_GET['searchtags']) ? explode(' ', $_GET['searchtags']) : [];
        $tags = $LINKSDB->linksCountPerTag($filteringTags, $visibility);

        // We sort tags alphabetically, then choose a font size according to count.
        // First, find max value.
        $maxcount = 0;
        foreach ($tags as $value) {
            $maxcount = max($maxcount, $value);
        }

        alphabetical_sort($tags, false, true);

        $tagList = array();
        foreach($tags as $key => $value) {
            if (in_array($key, $filteringTags)) {
                continue;
            }
            // Tag font size scaling:
            //   default 15 and 30 logarithm bases affect scaling,
            //   22 and 6 are arbitrary font sizes for max and min sizes.
            $size = log($value, 15) / log($maxcount, 30) * 2.2 + 0.8;
            $tagList[$key] = array(
                'count' => $value,
                'size' => number_format($size, 2, '.', ''),
            );
        }

        $searchTags = implode(' ', escape($filteringTags));
        $data = array(
            'search_tags' => $searchTags,
            'tags' => $tagList,
        );
        $pluginManager->executeHooks('render_tagcloud', $data, array('loggedin' => $loginManager->isLoggedIn()));

        foreach ($data as $key => $value) {
            $PAGE->assign($key, $value);
        }

        $searchTags = ! empty($searchTags) ? $searchTags .' - ' : '';
        $PAGE->assign('pagetitle', $searchTags. t('Tag cloud') .' - '. $conf->get('general.title', 'Shaarli'));
        $PAGE->renderPage('tag.cloud');
        exit;
    }

    // -------- Tag list
    if ($targetPage == Router::$PAGE_TAGLIST)
    {
        $visibility = ! empty($_SESSION['visibility']) ? $_SESSION['visibility'] : '';
        $filteringTags = isset($_GET['searchtags']) ? explode(' ', $_GET['searchtags']) : [];
        $tags = $LINKSDB->linksCountPerTag($filteringTags, $visibility);
        foreach ($filteringTags as $tag) {
            if (array_key_exists($tag, $tags)) {
                unset($tags[$tag]);
            }
        }

        if (! empty($_GET['sort']) && $_GET['sort'] === 'alpha') {
            alphabetical_sort($tags, false, true);
        }

        $searchTags = implode(' ', escape($filteringTags));
        $data = [
            'search_tags' => $searchTags,
            'tags' => $tags,
        ];
        $pluginManager->executeHooks('render_taglist', $data, ['loggedin' => $loginManager->isLoggedIn()]);

        foreach ($data as $key => $value) {
            $PAGE->assign($key, $value);
        }

        $searchTags = ! empty($searchTags) ? $searchTags .' - ' : '';
        $PAGE->assign('pagetitle', $searchTags . t('Tag list') .' - '. $conf->get('general.title', 'Shaarli'));
        $PAGE->renderPage('tag.list');
        exit;
    }

    // Daily page.
    if ($targetPage == Router::$PAGE_DAILY) {
        showDaily($PAGE, $LINKSDB, $conf, $pluginManager, $loginManager);
    }

    // ATOM and RSS feed.
    if ($targetPage == Router::$PAGE_FEED_ATOM || $targetPage == Router::$PAGE_FEED_RSS) {
        $feedType = $targetPage == Router::$PAGE_FEED_RSS ? FeedBuilder::$FEED_RSS : FeedBuilder::$FEED_ATOM;
        header('Content-Type: application/'. $feedType .'+xml; charset=utf-8');

        // Cache system
        $query = $_SERVER['QUERY_STRING'];
        $cache = new CachedPage(
            $conf->get('resource.page_cache'),
            page_url($_SERVER),
            startsWith($query,'do='. $targetPage) && !$loginManager->isLoggedIn()
        );
        $cached = $cache->cachedVersion();
        if (!empty($cached)) {
            echo $cached;
            exit;
        }

        // Generate data.
        $feedGenerator = new FeedBuilder($LINKSDB, $feedType, $_SERVER, $_GET, $loginManager->isLoggedIn());
        $feedGenerator->setLocale(strtolower(setlocale(LC_COLLATE, 0)));
        $feedGenerator->setHideDates($conf->get('privacy.hide_timestamps') && !$loginManager->isLoggedIn());
        $feedGenerator->setUsePermalinks(isset($_GET['permalinks']) || !$conf->get('feed.rss_permalinks'));
        $data = $feedGenerator->buildData();

        // Process plugin hook.
        $pluginManager->executeHooks('render_feed', $data, array(
            'loggedin' => $loginManager->isLoggedIn(),
            'target' => $targetPage,
        ));

        // Render the template.
        $PAGE->assignAll($data);
        $PAGE->renderPage('feed.'. $feedType);
        $cache->cache(ob_get_contents());
        ob_end_flush();
        exit;
    }

    // Display opensearch plugin (XML)
    if ($targetPage == Router::$PAGE_OPENSEARCH) {
        header('Content-Type: application/xml; charset=utf-8');
        $PAGE->assign('serverurl', index_url($_SERVER));
        $PAGE->renderPage('opensearch');
        exit;
    }

    // -------- User clicks on a tag in a link: The tag is added to the list of searched tags (searchtags=...)
    if (isset($_GET['addtag']))
    {
        // Get previous URL (http_referer) and add the tag to the searchtags parameters in query.
        if (empty($_SERVER['HTTP_REFERER'])) { header('Location: ?searchtags='.urlencode($_GET['addtag'])); exit; } // In case browser does not send HTTP_REFERER
        parse_str(parse_url($_SERVER['HTTP_REFERER'],PHP_URL_QUERY), $params);

        // Prevent redirection loop
        if (isset($params['addtag'])) {
            unset($params['addtag']);
        }

        // Check if this tag is already in the search query and ignore it if it is.
        // Each tag is always separated by a space
        if (isset($params['searchtags'])) {
            $current_tags = explode(' ', $params['searchtags']);
        } else {
            $current_tags = array();
        }
        $addtag = true;
        foreach ($current_tags as $value) {
            if ($value === $_GET['addtag']) {
                $addtag = false;
                break;
            }
        }
        // Append the tag if necessary
        if (empty($params['searchtags'])) {
            $params['searchtags'] = trim($_GET['addtag']);
        }
        elseif ($addtag) {
            $params['searchtags'] = trim($params['searchtags']).' '.trim($_GET['addtag']);
        }

        unset($params['page']); // We also remove page (keeping the same page has no sense, since the results are different)
        header('Location: ?'.http_build_query($params));
        exit;
    }

    // -------- User clicks on a tag in result count: Remove the tag from the list of searched tags (searchtags=...)
    if (isset($_GET['removetag'])) {
        // Get previous URL (http_referer) and remove the tag from the searchtags parameters in query.
        if (empty($_SERVER['HTTP_REFERER'])) {
            header('Location: ?');
            exit;
        }

        // In case browser does not send HTTP_REFERER
        parse_str(parse_url($_SERVER['HTTP_REFERER'], PHP_URL_QUERY), $params);

        // Prevent redirection loop
        if (isset($params['removetag'])) {
            unset($params['removetag']);
        }

        if (isset($params['searchtags'])) {
            $tags = explode(' ', $params['searchtags']);
            // Remove value from array $tags.
            $tags = array_diff($tags, array($_GET['removetag']));
            $params['searchtags'] = implode(' ',$tags);

            if (empty($params['searchtags'])) {
                unset($params['searchtags']);
            }

            unset($params['page']); // We also remove page (keeping the same page has no sense, since the results are different)
        }
        header('Location: ?'.http_build_query($params));
        exit;
    }

    // -------- User wants to change the number of links per page (linksperpage=...)
    if (isset($_GET['linksperpage'])) {
        if (is_numeric($_GET['linksperpage'])) {
            $_SESSION['LINKS_PER_PAGE']=abs(intval($_GET['linksperpage']));
        }

        if (! empty($_SERVER['HTTP_REFERER'])) {
            $location = generateLocation($_SERVER['HTTP_REFERER'], $_SERVER['HTTP_HOST'], array('linksperpage'));
        } else {
            $location = '?';
        }
        header('Location: '. $location);
        exit;
    }

    // -------- User wants to see only private links (toggle)
    if (isset($_GET['visibility'])) {
        if ($_GET['visibility'] === 'private') {
            // Visibility not set or not already private, set private, otherwise reset it
            if (empty($_SESSION['visibility']) || $_SESSION['visibility'] !== 'private') {
                // See only private links
                $_SESSION['visibility'] = 'private';
            } else {
                unset($_SESSION['visibility']);
            }
        } elseif ($_GET['visibility'] === 'public') {
            if (empty($_SESSION['visibility']) || $_SESSION['visibility'] !== 'public') {
                // See only public links
                $_SESSION['visibility'] = 'public';
            } else {
                unset($_SESSION['visibility']);
            }
        }

        if (! empty($_SERVER['HTTP_REFERER'])) {
            $location = generateLocation($_SERVER['HTTP_REFERER'], $_SERVER['HTTP_HOST'], array('visibility'));
        } else {
            $location = '?';
        }
        header('Location: '. $location);
        exit;
    }

    // -------- User wants to see only untagged links (toggle)
    if (isset($_GET['untaggedonly'])) {
        $_SESSION['untaggedonly'] = empty($_SESSION['untaggedonly']);

        if (! empty($_SERVER['HTTP_REFERER'])) {
            $location = generateLocation($_SERVER['HTTP_REFERER'], $_SERVER['HTTP_HOST'], array('untaggedonly'));
        } else {
            $location = '?';
        }
        header('Location: '. $location);
        exit;
    }

    // -------- Handle other actions allowed for non-logged in users:
    if (!$loginManager->isLoggedIn())
    {
        // User tries to post new link but is not logged in:
        // Show login screen, then redirect to ?post=...
        if (isset($_GET['post']))
        {
            header( // Redirect to login page, then back to post link.
                'Location: ?do=login&post='.urlencode($_GET['post']).
                (!empty($_GET['title'])?'&title='.urlencode($_GET['title']):'').
                (!empty($_GET['description'])?'&description='.urlencode($_GET['description']):'').
                (!empty($_GET['tags'])?'&tags='.urlencode($_GET['tags']):'').
                (!empty($_GET['source'])?'&source='.urlencode($_GET['source']):'')
            );
            exit;
        }

        showLinkList($PAGE, $LINKSDB, $conf, $pluginManager, $loginManager);
        if (isset($_GET['edit_link'])) {
            header('Location: ?do=login&edit_link='. escape($_GET['edit_link']));
            exit;
        }

        exit; // Never remove this one! All operations below are reserved for logged in user.
    }

    // -------- All other functions are reserved for the registered user:

    // -------- Display the Tools menu if requested (import/export/bookmarklet...)
    if ($targetPage == Router::$PAGE_TOOLS)
    {
        $data = [
            'pageabsaddr' => index_url($_SERVER),
            'sslenabled' => is_https($_SERVER),
        ];
        $pluginManager->executeHooks('render_tools', $data);

        foreach ($data as $key => $value) {
            $PAGE->assign($key, $value);
        }

        $PAGE->assign('pagetitle', t('Tools') .' - '. $conf->get('general.title', 'Shaarli'));
        $PAGE->renderPage('tools');
        exit;
    }

    // -------- User wants to change his/her password.
    if ($targetPage == Router::$PAGE_CHANGEPASSWORD)
    {
        if ($conf->get('security.open_shaarli')) {
            die(t('You are not supposed to change a password on an Open Shaarli.'));
        }

        if (!empty($_POST['setpassword']) && !empty($_POST['oldpassword']))
        {
            if (!$sessionManager->checkToken($_POST['token'])) die(t('Wrong token.')); // Go away!

            // Make sure old password is correct.
            $oldhash = sha1($_POST['oldpassword'].$conf->get('credentials.login').$conf->get('credentials.salt'));
            if ($oldhash!= $conf->get('credentials.hash')) {
                echo '<script>alert("'. t('The old password is not correct.') .'");document.location=\'?do=changepasswd\';</script>';
                exit;
            }
            // Save new password
            // Salt renders rainbow-tables attacks useless.
            $conf->set('credentials.salt', sha1(uniqid('', true) .'_'. mt_rand()));
            $conf->set('credentials.hash', sha1($_POST['setpassword'] . $conf->get('credentials.login') . $conf->get('credentials.salt')));
            try {
                $conf->write($loginManager->isLoggedIn());
            }
            catch(Exception $e) {
                error_log(
                    'ERROR while writing config file after changing password.' . PHP_EOL .
                    $e->getMessage()
                );

                // TODO: do not handle exceptions/errors in JS.
                echo '<script>alert("'. $e->getMessage() .'");document.location=\'?do=tools\';</script>';
                exit;
            }
            echo '<script>alert("'. t('Your password has been changed') .'");document.location=\'?do=tools\';</script>';
            exit;
        }
        else // show the change password form.
        {
            $PAGE->assign('pagetitle', t('Change password') .' - '. $conf->get('general.title', 'Shaarli'));
            $PAGE->renderPage('changepassword');
            exit;
        }
    }

    // -------- User wants to change configuration
    if ($targetPage == Router::$PAGE_CONFIGURE)
    {
        if (!empty($_POST['title']) )
        {
            if (!$sessionManager->checkToken($_POST['token'])) {
                die(t('Wrong token.')); // Go away!
            }
            $tz = 'UTC';
            if (!empty($_POST['continent']) && !empty($_POST['city'])
                && isTimeZoneValid($_POST['continent'], $_POST['city'])
            ) {
                $tz = $_POST['continent'] . '/' . $_POST['city'];
            }
            $conf->set('general.timezone', $tz);
            $conf->set('general.title', escape($_POST['title']));
            $conf->set('general.header_link', escape($_POST['titleLink']));
            $conf->set('resource.theme', escape($_POST['theme']));
            $conf->set('security.session_protection_disabled', !empty($_POST['disablesessionprotection']));
            $conf->set('privacy.default_private_links', !empty($_POST['privateLinkByDefault']));
            $conf->set('feed.rss_permalinks', !empty($_POST['enableRssPermalinks']));
            $conf->set('updates.check_updates', !empty($_POST['updateCheck']));
            $conf->set('privacy.hide_public_links', !empty($_POST['hidePublicLinks']));
            $conf->set('api.enabled', !empty($_POST['enableApi']));
            $conf->set('api.secret', escape($_POST['apiSecret']));
            $conf->set('translation.language', escape($_POST['language']));

            $thumbnailsMode = extension_loaded('gd') ? $_POST['enableThumbnails'] : Thumbnailer::MODE_NONE;
            if ($thumbnailsMode !== Thumbnailer::MODE_NONE
                && $thumbnailsMode !== $conf->get('thumbnails.mode', Thumbnailer::MODE_NONE)
            ) {
                $_SESSION['warnings'][] = t(
                    'You have enabled or changed thumbnails mode. <a href="?do=thumbs_update">Please synchronize them</a>.'
                );
            }
            $conf->set('thumbnails.mode', $thumbnailsMode);

            try {
                $conf->write($loginManager->isLoggedIn());
                $history->updateSettings();
                invalidateCaches($conf->get('resource.page_cache'));
            }
            catch(Exception $e) {
                error_log(
                    'ERROR while writing config file after configuration update.' . PHP_EOL .
                    $e->getMessage()
                );

                // TODO: do not handle exceptions/errors in JS.
                echo '<script>alert("'. $e->getMessage() .'");document.location=\'?do=configure\';</script>';
                exit;
            }
            echo '<script>alert("'. t('Configuration was saved.') .'");document.location=\'?do=configure\';</script>';
            exit;
        }
        else // Show the configuration form.
        {
            $PAGE->assign('title', $conf->get('general.title'));
            $PAGE->assign('theme', $conf->get('resource.theme'));
            $PAGE->assign('theme_available', ThemeUtils::getThemes($conf->get('resource.raintpl_tpl')));
            list($continents, $cities) = generateTimeZoneData(
                timezone_identifiers_list(),
                $conf->get('general.timezone')
            );
            $PAGE->assign('continents', $continents);
            $PAGE->assign('cities', $cities);
            $PAGE->assign('private_links_default', $conf->get('privacy.default_private_links', false));
            $PAGE->assign('session_protection_disabled', $conf->get('security.session_protection_disabled', false));
            $PAGE->assign('enable_rss_permalinks', $conf->get('feed.rss_permalinks', false));
            $PAGE->assign('enable_update_check', $conf->get('updates.check_updates', true));
            $PAGE->assign('hide_public_links', $conf->get('privacy.hide_public_links', false));
            $PAGE->assign('api_enabled', $conf->get('api.enabled', true));
            $PAGE->assign('api_secret', $conf->get('api.secret'));
            $PAGE->assign('languages', Languages::getAvailableLanguages());
            $PAGE->assign('language', $conf->get('translation.language'));
            $PAGE->assign('gd_enabled', extension_loaded('gd'));
            $PAGE->assign('thumbnails_mode', $conf->get('thumbnails.mode', Thumbnailer::MODE_NONE));
            $PAGE->assign('pagetitle', t('Configure') .' - '. $conf->get('general.title', 'Shaarli'));
            $PAGE->renderPage('configure');
            exit;
        }
    }

    // -------- User wants to rename a tag or delete it
    if ($targetPage == Router::$PAGE_CHANGETAG)
    {
        if (empty($_POST['fromtag']) || (empty($_POST['totag']) && isset($_POST['renametag']))) {
            $PAGE->assign('fromtag', ! empty($_GET['fromtag']) ? escape($_GET['fromtag']) : '');
            $PAGE->assign('pagetitle', t('Manage tags') .' - '. $conf->get('general.title', 'Shaarli'));
            $PAGE->renderPage('changetag');
            exit;
        }

        if (!$sessionManager->checkToken($_POST['token'])) {
            die(t('Wrong token.'));
        }

        $toTag = isset($_POST['totag']) ? escape($_POST['totag']) : null;
        $alteredLinks = $LINKSDB->renameTag(escape($_POST['fromtag']), $toTag);
        $LINKSDB->save($conf->get('resource.page_cache'));
        foreach ($alteredLinks as $link) {
            $history->updateLink($link);
        }
        $delete = empty($_POST['totag']);
        $redirect = $delete ? 'do=changetag' : 'searchtags='. urlencode(escape($_POST['totag']));
        $count = count($alteredLinks);
        $alert = $delete
            ? sprintf(t('The tag was removed from %d link.', 'The tag was removed from %d links.', $count), $count)
            : sprintf(t('The tag was renamed in %d link.', 'The tag was renamed in %d links.', $count), $count);
        echo '<script>alert("'. $alert .'");document.location=\'?'. $redirect .'\';</script>';
        exit;
    }

    // -------- User wants to add a link without using the bookmarklet: Show form.
    if ($targetPage == Router::$PAGE_ADDLINK)
    {
        $PAGE->assign('pagetitle', t('Shaare a new link') .' - '. $conf->get('general.title', 'Shaarli'));
        $PAGE->renderPage('addlink');
        exit;
    }

    // -------- User clicked the "Save" button when editing a link: Save link to database.
    if (isset($_POST['save_edit']))
    {
        // Go away!
        if (! $sessionManager->checkToken($_POST['token'])) {
            die(t('Wrong token.'));
        }

        // lf_id should only be present if the link exists.
        $id = isset($_POST['lf_id']) ? intval(escape($_POST['lf_id'])) : $LINKSDB->getNextId();
        // Linkdate is kept here to:
        //   - use the same permalink for notes as they're displayed when creating them
        //   - let users hack creation date of their posts
        //     See: https://shaarli.readthedocs.io/en/master/guides/various-hacks/#changing-the-timestamp-for-a-shaare
        $linkdate = escape($_POST['lf_linkdate']);
        if (isset($LINKSDB[$id])) {
            // Edit
            $created = DateTime::createFromFormat(LinkDB::LINK_DATE_FORMAT, $linkdate);
            $updated = new DateTime();
            $shortUrl = $LINKSDB[$id]['shorturl'];
            $new = false;
        } else {
            // New link
            $created = DateTime::createFromFormat(LinkDB::LINK_DATE_FORMAT, $linkdate);
            $updated = null;
            $shortUrl = link_small_hash($created, $id);
            $new = true;
        }

        // Remove multiple spaces.
        $tags = trim(preg_replace('/\s\s+/', ' ', $_POST['lf_tags']));
        // Remove first '-' char in tags.
        $tags = preg_replace('/(^| )\-/', '$1', $tags);
        // Remove duplicates.
        $tags = implode(' ', array_unique(explode(' ', $tags)));

        if (empty(trim($_POST['lf_url']))) {
            $_POST['lf_url'] = '?' . smallHash($linkdate . $id);
        }
        $url = whitelist_protocols(trim($_POST['lf_url']), $conf->get('security.allowed_protocols'));

        $link = array(
            'id' => $id,
            'title' => trim($_POST['lf_title']),
            'url' => $url,
            'description' => $_POST['lf_description'],
            'private' => (isset($_POST['lf_private']) ? 1 : 0),
            'created' => $created,
            'updated' => $updated,
            'tags' => str_replace(',', ' ', $tags),
            'shorturl' => $shortUrl,
        );

        // If title is empty, use the URL as title.
        if ($link['title'] == '') {
            $link['title'] = $link['url'];
        }

        if ($conf->get('thumbnails.mode', Thumbnailer::MODE_NONE) !== Thumbnailer::MODE_NONE) {
            $thumbnailer = new Thumbnailer($conf);
            $link['thumbnail'] = $thumbnailer->get($url);
        }

        $pluginManager->executeHooks('save_link', $link);

        $LINKSDB[$id] = $link;
        $LINKSDB->save($conf->get('resource.page_cache'));
        if ($new) {
            $history->addLink($link);
        } else {
            $history->updateLink($link);
        }

        // If we are called from the bookmarklet, we must close the popup:
        if (isset($_GET['source']) && ($_GET['source']=='bookmarklet' || $_GET['source']=='firefoxsocialapi')) {
            echo '<script>self.close();</script>';
            exit;
        }

        $returnurl = !empty($_POST['returnurl']) ? $_POST['returnurl'] : '?';
        $location = generateLocation($returnurl, $_SERVER['HTTP_HOST'], array('addlink', 'post', 'edit_link'));
        // Scroll to the link which has been edited.
        $location .= '#' . $link['shorturl'];
        // After saving the link, redirect to the page the user was on.
        header('Location: '. $location);
        exit;
    }

    // -------- User clicked the "Cancel" button when editing a link.
    if (isset($_POST['cancel_edit']))
    {
        $id = isset($_POST['lf_id']) ? (int) escape($_POST['lf_id']) : false;
        if (! isset($LINKSDB[$id])) {
            header('Location: ?');
        }
        // If we are called from the bookmarklet, we must close the popup:
        if (isset($_GET['source']) && ($_GET['source']=='bookmarklet' || $_GET['source']=='firefoxsocialapi')) { echo '<script>self.close();</script>'; exit; }
        $link = $LINKSDB[$id];
        $returnurl = ( isset($_POST['returnurl']) ? $_POST['returnurl'] : '?' );
        // Scroll to the link which has been edited.
        $returnurl .= '#'. $link['shorturl'];
        $returnurl = generateLocation($returnurl, $_SERVER['HTTP_HOST'], array('addlink', 'post', 'edit_link'));
        header('Location: '.$returnurl); // After canceling, redirect to the page the user was on.
        exit;
    }

    // -------- User clicked the "Delete" button when editing a link: Delete link from database.
    if ($targetPage == Router::$PAGE_DELETELINK)
    {
        if (! $sessionManager->checkToken($_GET['token'])) {
            die(t('Wrong token.'));
        }

        $ids = trim($_GET['lf_linkdate']);
        if (strpos($ids, ' ') !== false) {
            // multiple, space-separated ids provided
            $ids = array_values(array_filter(preg_split('/\s+/', escape($ids))));
        } else {
            // only a single id provided
            $ids = [$ids];
        }
        // assert at least one id is given
        if(!count($ids)){
            die('no id provided');
        }
        foreach ($ids as $id) {
            $id = (int) escape($id);
            $link = $LINKSDB[$id];
            $pluginManager->executeHooks('delete_link', $link);
            $history->deleteLink($link);
            unset($LINKSDB[$id]);
        }
        $LINKSDB->save($conf->get('resource.page_cache')); // save to disk

        // If we are called from the bookmarklet, we must close the popup:
        if (isset($_GET['source']) && ($_GET['source']=='bookmarklet' || $_GET['source']=='firefoxsocialapi')) { echo '<script>self.close();</script>'; exit; }

        $location = '?';
        if (isset($_SERVER['HTTP_REFERER'])) {
            // Don't redirect to where we were previously if it was a permalink or an edit_link, because it would 404.
            $location = generateLocation(
                    $_SERVER['HTTP_REFERER'],
                    $_SERVER['HTTP_HOST'],
                    ['delete_link', 'edit_link', $link['shorturl']]
            );
        }

        header('Location: ' . $location); // After deleting the link, redirect to appropriate location
        exit;
    }

    // -------- User clicked the "EDIT" button on a link: Display link edit form.
    if (isset($_GET['edit_link']))
    {
        $id = (int) escape($_GET['edit_link']);
        $link = $LINKSDB[$id];  // Read database
        if (!$link) { header('Location: ?'); exit; } // Link not found in database.
        $link['linkdate'] = $link['created']->format(LinkDB::LINK_DATE_FORMAT);
        $data = array(
            'link' => $link,
            'link_is_new' => false,
            'http_referer' => (isset($_SERVER['HTTP_REFERER']) ? escape($_SERVER['HTTP_REFERER']) : ''),
            'tags' => $LINKSDB->linksCountPerTag(),
        );
        $pluginManager->executeHooks('render_editlink', $data);

        foreach ($data as $key => $value) {
            $PAGE->assign($key, $value);
        }

        $PAGE->assign('pagetitle', t('Edit') .' '. t('Shaare') .' - '. $conf->get('general.title', 'Shaarli'));
        $PAGE->renderPage('editlink');
        exit;
    }

    // -------- User want to post a new link: Display link edit form.
    if (isset($_GET['post'])) {
        $url = cleanup_url($_GET['post']);

        $link_is_new = false;
        // Check if URL is not already in database (in this case, we will edit the existing link)
        $link = $LINKSDB->getLinkFromUrl($url);
        if (! $link)
        {
            $link_is_new = true;
            $linkdate = strval(date(LinkDB::LINK_DATE_FORMAT));
            // Get title if it was provided in URL (by the bookmarklet).
            $title = empty($_GET['title']) ? '' : escape($_GET['title']);
            // Get description if it was provided in URL (by the bookmarklet). [Bronco added that]
            $description = empty($_GET['description']) ? '' : escape($_GET['description']);
            $tags = empty($_GET['tags']) ? '' : escape($_GET['tags']);
            $private = !empty($_GET['private']) && $_GET['private'] === "1" ? 1 : 0;
            // If this is an HTTP(S) link, we try go get the page to extract the title (otherwise we will to straight to the edit form.)
            if (empty($title) && strpos(get_url_scheme($url), 'http') !== false) {
                // Short timeout to keep the application responsive
                // The callback will fill $charset and $title with data from the downloaded page.
                get_http_response(
                    $url,
                    $conf->get('general.download_timeout', 30),
                    $conf->get('general.download_max_size', 4194304),
                    get_curl_download_callback($charset, $title)
                );
                if (! empty($title) && strtolower($charset) != 'utf-8') {
                    $title = mb_convert_encoding($title, 'utf-8', $charset);
                }
            }

            if ($url == '') {
                $url = '?' . smallHash($linkdate . $LINKSDB->getNextId());
                $title = $conf->get('general.default_note_title', t('Note: '));
            }
            $url = escape($url);
            $title = escape($title);

            $link = array(
                'linkdate' => $linkdate,
                'title' => $title,
                'url' => $url,
                'description' => $description,
                'tags' => $tags,
                'private' => $private,
            );
        } else {
            $link['linkdate'] = $link['created']->format(LinkDB::LINK_DATE_FORMAT);
        }

        $data = array(
            'link' => $link,
            'link_is_new' => $link_is_new,
            'http_referer' => (isset($_SERVER['HTTP_REFERER']) ? escape($_SERVER['HTTP_REFERER']) : ''),
            'source' => (isset($_GET['source']) ? $_GET['source'] : ''),
            'tags' => $LINKSDB->linksCountPerTag(),
            'default_private_links' => $conf->get('privacy.default_private_links', false),
        );
        $pluginManager->executeHooks('render_editlink', $data);

        foreach ($data as $key => $value) {
            $PAGE->assign($key, $value);
        }

        $PAGE->assign('pagetitle', t('Shaare') .' - '. $conf->get('general.title', 'Shaarli'));
        $PAGE->renderPage('editlink');
        exit;
    }

    if ($targetPage == Router::$PAGE_PINLINK) {
        if (! isset($_GET['id']) || empty($LINKSDB[$_GET['id']])) {
            // FIXME! Use a proper error system.
            $msg = t('Invalid link ID provided');
            echo '<script>alert("'. $msg .'");document.location=\''. index_url($_SERVER) .'\';</script>';
            exit;
        }
        if (! $sessionManager->checkToken($_GET['token'])) {
            die('Wrong token.');
        }

        $link = $LINKSDB[$_GET['id']];
        $link['sticky'] = ! $link['sticky'];
        $LINKSDB[(int) $_GET['id']] = $link;
        $LINKSDB->save($conf->get('resource.page_cache'));
        header('Location: '.index_url($_SERVER));
        exit;
    }

    if ($targetPage == Router::$PAGE_EXPORT) {
        // Export links as a Netscape Bookmarks file

        if (empty($_GET['selection'])) {
            $PAGE->assign('pagetitle', t('Export') .' - '. $conf->get('general.title', 'Shaarli'));
            $PAGE->renderPage('export');
            exit;
        }

        // export as bookmarks_(all|private|public)_YYYYmmdd_HHMMSS.html
        $selection = $_GET['selection'];
        if (isset($_GET['prepend_note_url'])) {
            $prependNoteUrl = $_GET['prepend_note_url'];
        } else {
            $prependNoteUrl = false;
        }

        try {
            $PAGE->assign(
                'links',
                NetscapeBookmarkUtils::filterAndFormat(
                    $LINKSDB,
                    $selection,
                    $prependNoteUrl,
                    index_url($_SERVER)
                )
            );
        } catch (Exception $exc) {
            header('Content-Type: text/plain; charset=utf-8');
            echo $exc->getMessage();
            exit;
        }
        $now = new DateTime();
        header('Content-Type: text/html; charset=utf-8');
        header(
            'Content-disposition: attachment; filename=bookmarks_'
           .$selection.'_'.$now->format(LinkDB::LINK_DATE_FORMAT).'.html'
        );
        $PAGE->assign('date', $now->format(DateTime::RFC822));
        $PAGE->assign('eol', PHP_EOL);
        $PAGE->assign('selection', $selection);
        $PAGE->renderPage('export.bookmarks');
        exit;
    }

    if ($targetPage == Router::$PAGE_IMPORT) {
        // Upload a Netscape bookmark dump to import its contents

        if (! isset($_POST['token']) || ! isset($_FILES['filetoupload'])) {
            // Show import dialog
            $PAGE->assign(
                'maxfilesize',
                get_max_upload_size(
                    ini_get('post_max_size'),
                    ini_get('upload_max_filesize'),
                    false
                )
            );
            $PAGE->assign(
                'maxfilesizeHuman',
                get_max_upload_size(
                    ini_get('post_max_size'),
                    ini_get('upload_max_filesize'),
                    true
                )
            );
            $PAGE->assign('pagetitle', t('Import') .' - '. $conf->get('general.title', 'Shaarli'));
            $PAGE->renderPage('import');
            exit;
        }

        // Import bookmarks from an uploaded file
        if (isset($_FILES['filetoupload']['size']) && $_FILES['filetoupload']['size'] == 0) {
            // The file is too big or some form field may be missing.
            $msg = sprintf(
                t(
                    'The file you are trying to upload is probably bigger than what this webserver can accept'
                    .' (%s). Please upload in smaller chunks.'
                ),
                get_max_upload_size(ini_get('post_max_size'), ini_get('upload_max_filesize'))
            );
            echo '<script>alert("'. $msg .'");document.location=\'?do='.Router::$PAGE_IMPORT .'\';</script>';
            exit;
        }
        if (! $sessionManager->checkToken($_POST['token'])) {
            die('Wrong token.');
        }
        $status = NetscapeBookmarkUtils::import(
            $_POST,
            $_FILES,
            $LINKSDB,
            $conf,
            $history
        );
        echo '<script>alert("'.$status.'");document.location=\'?do='
             .Router::$PAGE_IMPORT .'\';</script>';
        exit;
    }

    // Plugin administration page
    if ($targetPage == Router::$PAGE_PLUGINSADMIN) {
        $pluginMeta = $pluginManager->getPluginsMeta();

        // Split plugins into 2 arrays: ordered enabled plugins and disabled.
        $enabledPlugins = array_filter($pluginMeta, function($v) { return $v['order'] !== false; });
        // Load parameters.
        $enabledPlugins = load_plugin_parameter_values($enabledPlugins, $conf->get('plugins', array()));
        uasort(
            $enabledPlugins,
            function($a, $b) { return $a['order'] - $b['order']; }
        );
        $disabledPlugins = array_filter($pluginMeta, function($v) { return $v['order'] === false; });

        $PAGE->assign('enabledPlugins', $enabledPlugins);
        $PAGE->assign('disabledPlugins', $disabledPlugins);
        $PAGE->assign('pagetitle', t('Plugin administration') .' - '. $conf->get('general.title', 'Shaarli'));
        $PAGE->renderPage('pluginsadmin');
        exit;
    }

    // Plugin administration form action
    if ($targetPage == Router::$PAGE_SAVE_PLUGINSADMIN) {
        try {
            if (isset($_POST['parameters_form'])) {
                unset($_POST['parameters_form']);
                foreach ($_POST as $param => $value) {
                    $conf->set('plugins.'. $param, escape($value));
                }
            }
            else {
                $conf->set('general.enabled_plugins', save_plugin_config($_POST));
            }
            $conf->write($loginManager->isLoggedIn());
            $history->updateSettings();
        }
        catch (Exception $e) {
            error_log(
                'ERROR while saving plugin configuration:.' . PHP_EOL .
                $e->getMessage()
            );

            // TODO: do not handle exceptions/errors in JS.
            echo '<script>alert("'. $e->getMessage() .'");document.location=\'?do='. Router::$PAGE_PLUGINSADMIN .'\';</script>';
            exit;
        }
        header('Location: ?do='. Router::$PAGE_PLUGINSADMIN);
        exit;
    }

    // Get a fresh token
    if ($targetPage == Router::$GET_TOKEN) {
        header('Content-Type:text/plain');
        echo $sessionManager->generateToken($conf);
        exit;
    }

    // -------- Thumbnails Update
    if ($targetPage == Router::$PAGE_THUMBS_UPDATE) {
        $ids = [];
        foreach ($LINKSDB as $link) {
            // A note or not HTTP(S)
            if ($link['url'][0] === '?' || ! startsWith(strtolower($link['url']), 'http')) {
                continue;
            }
            $ids[] = $link['id'];
        }
        $PAGE->assign('ids', $ids);
        $PAGE->assign('pagetitle', t('Thumbnails update') .' - '. $conf->get('general.title', 'Shaarli'));
        $PAGE->renderPage('thumbnails');
        exit;
    }

    // -------- Single Thumbnail Update
    if ($targetPage == Router::$AJAX_THUMB_UPDATE) {
        if (! isset($_POST['id']) || ! ctype_digit($_POST['id'])) {
            http_response_code(400);
            exit;
        }
        $id = (int) $_POST['id'];
        if (empty($LINKSDB[$id])) {
            http_response_code(404);
            exit;
        }
        $thumbnailer = new Thumbnailer($conf);
        $link = $LINKSDB[$id];
        $link['thumbnail'] = $thumbnailer->get($link['url']);
        $LINKSDB[$id] = $link;
        $LINKSDB->save($conf->get('resource.page_cache'));

        echo json_encode($link);
        exit;
    }

    // -------- Otherwise, simply display search form and links:
    showLinkList($PAGE, $LINKSDB, $conf, $pluginManager, $loginManager);
    exit;
}

/**
 * Template for the list of links (<div id="linklist">)
 * This function fills all the necessary fields in the $PAGE for the template 'linklist.html'
 *
 * @param pageBuilder   $PAGE          pageBuilder instance.
 * @param LinkDB        $LINKSDB       LinkDB instance.
 * @param ConfigManager $conf          Configuration Manager instance.
 * @param PluginManager $pluginManager Plugin Manager instance.
 * @param LoginManager  $loginManager  LoginManager instance
 */
function buildLinkList($PAGE, $LINKSDB, $conf, $pluginManager, $loginManager)
{
    // Used in templates
    if (isset($_GET['searchtags'])) {
        if (! empty($_GET['searchtags'])) {
            $searchtags = escape(normalize_spaces($_GET['searchtags']));
        } else {
            $searchtags = false;
        }
    } else {
        $searchtags = '';
    }
    $searchterm = !empty($_GET['searchterm']) ? escape(normalize_spaces($_GET['searchterm'])) : '';

    // Smallhash filter
    if (! empty($_SERVER['QUERY_STRING'])
        && preg_match('/^[a-zA-Z0-9-_@]{6}($|&|#)/', $_SERVER['QUERY_STRING'])) {
        try {
            $linksToDisplay = $LINKSDB->filterHash($_SERVER['QUERY_STRING']);
        } catch (LinkNotFoundException $e) {
            $PAGE->render404($e->getMessage());
            exit;
        }
    } else {
        // Filter links according search parameters.
        $visibility = ! empty($_SESSION['visibility']) ? $_SESSION['visibility'] : '';
        $request = [
            'searchtags' => $searchtags,
            'searchterm' => $searchterm,
        ];
        $linksToDisplay = $LINKSDB->filterSearch($request, false, $visibility, !empty($_SESSION['untaggedonly']));
    }

    // ---- Handle paging.
    $keys = array();
    foreach ($linksToDisplay as $key => $value) {
        $keys[] = $key;
    }

    // Select articles according to paging.
    $pagecount = ceil(count($keys) / $_SESSION['LINKS_PER_PAGE']);
    $pagecount = $pagecount == 0 ? 1 : $pagecount;
    $page= empty($_GET['page']) ? 1 : intval($_GET['page']);
    $page = $page < 1 ? 1 : $page;
    $page = $page > $pagecount ? $pagecount : $page;
    // Start index.
    $i = ($page-1) * $_SESSION['LINKS_PER_PAGE'];
    $end = $i + $_SESSION['LINKS_PER_PAGE'];

    $thumbnailsEnabled = $conf->get('thumbnails.mode', Thumbnailer::MODE_NONE) !== Thumbnailer::MODE_NONE;
    if ($thumbnailsEnabled) {
        $thumbnailer = new Thumbnailer($conf);
    }

    $linkDisp = array();
    while ($i<$end && $i<count($keys))
    {
        $link = $linksToDisplay[$keys[$i]];
        $link['description'] = format_description(
            $link['description'],
            $conf->get('redirector.url'),
            $conf->get('redirector.encode_url')
        );
        $classLi =  ($i % 2) != 0 ? '' : 'publicLinkHightLight';
        $link['class'] = $link['private'] == 0 ? $classLi : 'private';
        $link['timestamp'] = $link['created']->getTimestamp();
        if (! empty($link['updated'])) {
            $link['updated_timestamp'] = $link['updated']->getTimestamp();
        } else {
            $link['updated_timestamp'] = '';
        }
        $taglist = preg_split('/\s+/', $link['tags'], -1, PREG_SPLIT_NO_EMPTY);
        uasort($taglist, 'strcasecmp');
        $link['taglist'] = $taglist;

        // Logged in, thumbnails enabled, not a note,
        // and (never retrieved yet or no valid cache file)
        if ($loginManager->isLoggedIn() && $thumbnailsEnabled && $link['url'][0] != '?'
            && (! isset($link['thumbnail']) || ($link['thumbnail'] !== false && ! is_file($link['thumbnail'])))
        ) {
            $elem = $LINKSDB[$keys[$i]];
            $elem['thumbnail'] = $thumbnailer->get($link['url']);
            $LINKSDB[$keys[$i]] = $elem;
            $updateDB = true;
            $link['thumbnail'] = $elem['thumbnail'];
        }

        // Check for both signs of a note: starting with ? and 7 chars long.
        if ($link['url'][0] === '?' && strlen($link['url']) === 7) {
            $link['url'] = index_url($_SERVER) . $link['url'];
        }

        $linkDisp[$keys[$i]] = $link;
        $i++;
    }

    // If we retrieved new thumbnails, we update the database.
    if (!empty($updateDB)) {
        $LINKSDB->save($conf->get('resource.page_cache'));
    }

    // Compute paging navigation
    $searchtagsUrl = $searchtags === '' ? '' : '&searchtags=' . urlencode($searchtags);
    $searchtermUrl = empty($searchterm) ? '' : '&searchterm=' . urlencode($searchterm);
    $previous_page_url = '';
    if ($i != count($keys)) {
        $previous_page_url = '?page=' . ($page+1) . $searchtermUrl . $searchtagsUrl;
    }
    $next_page_url='';
    if ($page>1) {
        $next_page_url = '?page=' . ($page-1) . $searchtermUrl . $searchtagsUrl;
    }

    // Fill all template fields.
    $data = array(
        'previous_page_url' => $previous_page_url,
        'next_page_url' => $next_page_url,
        'page_current' => $page,
        'page_max' => $pagecount,
        'result_count' => count($linksToDisplay),
        'search_term' => $searchterm,
        'search_tags' => $searchtags,
        'visibility' => ! empty($_SESSION['visibility']) ? $_SESSION['visibility'] : '',
        'redirector' => $conf->get('redirector.url'),  // Optional redirector URL.
        'links' => $linkDisp,
    );

    // If there is only a single link, we change on-the-fly the title of the page.
    if (count($linksToDisplay) == 1) {
        $data['pagetitle'] = $linksToDisplay[$keys[0]]['title'] .' - '. $conf->get('general.title');
    } elseif (! empty($searchterm) || ! empty($searchtags)) {
        $data['pagetitle'] = t('Search: ');
        $data['pagetitle'] .= ! empty($searchterm) ? $searchterm .' ' : '';
        $bracketWrap = function ($tag) {
            return '['. $tag .']';
        };
        $data['pagetitle'] .= ! empty($searchtags)
            ? implode(' ', array_map($bracketWrap, preg_split('/\s+/', $searchtags))).' '
            : '';
        $data['pagetitle'] .= '- '. $conf->get('general.title');
    }

    $pluginManager->executeHooks('render_linklist', $data, array('loggedin' => $loginManager->isLoggedIn()));

    foreach ($data as $key => $value) {
        $PAGE->assign($key, $value);
    }

    return;
}

/**
 * Installation
 * This function should NEVER be called if the file data/config.php exists.
 *
 * @param ConfigManager  $conf           Configuration Manager instance.
 * @param SessionManager $sessionManager SessionManager instance
 * @param LoginManager   $loginManager   LoginManager instance
 */
function install($conf, $sessionManager, $loginManager) {
    // On free.fr host, make sure the /sessions directory exists, otherwise login will not work.
    if (endsWith($_SERVER['HTTP_HOST'],'.free.fr') && !is_dir($_SERVER['DOCUMENT_ROOT'].'/sessions')) mkdir($_SERVER['DOCUMENT_ROOT'].'/sessions',0705);


    // This part makes sure sessions works correctly.
    // (Because on some hosts, session.save_path may not be set correctly,
    // or we may not have write access to it.)
    if (isset($_GET['test_session']) && ( !isset($_SESSION) || !isset($_SESSION['session_tested']) || $_SESSION['session_tested']!='Working'))
    {
        // Step 2: Check if data in session is correct.
        $msg = t(
            '<pre>Sessions do not seem to work correctly on your server.<br>'.
            'Make sure the variable "session.save_path" is set correctly in your PHP config, '.
            'and that you have write access to it.<br>'.
            'It currently points to %s.<br>'.
            'On some browsers, accessing your server via a hostname like \'localhost\' '.
            'or any custom hostname without a dot causes cookie storage to fail. '.
            'We recommend accessing your server via it\'s IP address or Fully Qualified Domain Name.<br>'
        );
        $msg = sprintf($msg, session_save_path());
        echo $msg;
        echo '<br><a href="?">'. t('Click to try again.') .'</a></pre>';
        die;
    }
    if (!isset($_SESSION['session_tested']))
    {   // Step 1 : Try to store data in session and reload page.
        $_SESSION['session_tested'] = 'Working';  // Try to set a variable in session.
        header('Location: '.index_url($_SERVER).'?test_session');  // Redirect to check stored data.
    }
    if (isset($_GET['test_session']))
    {   // Step 3: Sessions are OK. Remove test parameter from URL.
        header('Location: '.index_url($_SERVER));
    }


    if (!empty($_POST['setlogin']) && !empty($_POST['setpassword']))
    {
        $tz = 'UTC';
        if (!empty($_POST['continent']) && !empty($_POST['city'])
            && isTimeZoneValid($_POST['continent'], $_POST['city'])
        ) {
            $tz = $_POST['continent'].'/'.$_POST['city'];
        }
        $conf->set('general.timezone', $tz);
        $login = $_POST['setlogin'];
        $conf->set('credentials.login', $login);
        $salt = sha1(uniqid('', true) .'_'. mt_rand());
        $conf->set('credentials.salt', $salt);
        $conf->set('credentials.hash', sha1($_POST['setpassword'] . $login . $salt));
        if (!empty($_POST['title'])) {
            $conf->set('general.title', escape($_POST['title']));
        } else {
            $conf->set('general.title', 'Shared links on '.escape(index_url($_SERVER)));
        }
        $conf->set('translation.language', escape($_POST['language']));
        $conf->set('updates.check_updates', !empty($_POST['updateCheck']));
        $conf->set('api.enabled', !empty($_POST['enableApi']));
        $conf->set(
            'api.secret',
            generate_api_secret(
                $conf->get('credentials.login'),
                $conf->get('credentials.salt')
            )
        );
        try {
            // Everything is ok, let's create config file.
            $conf->write($loginManager->isLoggedIn());
        }
        catch(Exception $e) {
            error_log(
                    'ERROR while writing config file after installation.' . PHP_EOL .
                    $e->getMessage()
                );

            // TODO: do not handle exceptions/errors in JS.
            echo '<script>alert("'. $e->getMessage() .'");document.location=\'?\';</script>';
            exit;
        }
        echo '<script>alert("Shaarli is now configured. Please enter your login/password and start shaaring your links!");document.location=\'?do=login\';</script>';
        exit;
    }

    $PAGE = new PageBuilder($conf, $_SESSION, null, $sessionManager->generateToken());
    list($continents, $cities) = generateTimeZoneData(timezone_identifiers_list(), date_default_timezone_get());
    $PAGE->assign('continents', $continents);
    $PAGE->assign('cities', $cities);
    $PAGE->assign('languages', Languages::getAvailableLanguages());
    $PAGE->renderPage('install');
    exit;
}

if (isset($_SERVER['QUERY_STRING']) && startsWith($_SERVER['QUERY_STRING'], 'do=dailyrss')) {
    showDailyRSS($conf, $loginManager);
    exit;
}

if (!isset($_SESSION['LINKS_PER_PAGE'])) {
    $_SESSION['LINKS_PER_PAGE'] = $conf->get('general.links_per_page', 20);
}

try {
    $history = new History($conf->get('resource.history'));
} catch(Exception $e) {
    die($e->getMessage());
}

$linkDb = new LinkDB(
    $conf->get('resource.datastore'),
    $loginManager->isLoggedIn(),
    $conf->get('privacy.hide_public_links'),
    $conf->get('redirector.url'),
    $conf->get('redirector.encode_url')
);

$container = new \Slim\Container();
$container['conf'] = $conf;
$container['plugins'] = $pluginManager;
$container['history'] = $history;
$app = new \Slim\App($container);

// REST API routes
$app->group('/api/v1', function() {
    $this->get('/info', '\Shaarli\Api\Controllers\Info:getInfo')->setName('getInfo');
    $this->get('/links', '\Shaarli\Api\Controllers\Links:getLinks')->setName('getLinks');
    $this->get('/links/{id:[\d]+}', '\Shaarli\Api\Controllers\Links:getLink')->setName('getLink');
    $this->post('/links', '\Shaarli\Api\Controllers\Links:postLink')->setName('postLink');
    $this->put('/links/{id:[\d]+}', '\Shaarli\Api\Controllers\Links:putLink')->setName('putLink');
    $this->delete('/links/{id:[\d]+}', '\Shaarli\Api\Controllers\Links:deleteLink')->setName('deleteLink');

    $this->get('/tags', '\Shaarli\Api\Controllers\Tags:getTags')->setName('getTags');
    $this->get('/tags/{tagName:[\w]+}', '\Shaarli\Api\Controllers\Tags:getTag')->setName('getTag');
    $this->put('/tags/{tagName:[\w]+}', '\Shaarli\Api\Controllers\Tags:putTag')->setName('putTag');
    $this->delete('/tags/{tagName:[\w]+}', '\Shaarli\Api\Controllers\Tags:deleteTag')->setName('deleteTag');

    $this->get('/history', '\Shaarli\Api\Controllers\History:getHistory')->setName('getHistory');
})->add('\Shaarli\Api\ApiMiddleware');

$response = $app->run(true);

// Hack to make Slim and Shaarli router work together:
// If a Slim route isn't found and NOT API call, we call renderPage().
if ($response->getStatusCode() == 404 && strpos($_SERVER['REQUEST_URI'], '/api/v1') === false) {
    // We use UTF-8 for proper international characters handling.
    header('Content-Type: text/html; charset=utf-8');
    renderPage($conf, $pluginManager, $linkDb, $history, $sessionManager, $loginManager);
} else {
    $response = $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader(
            'Access-Control-Allow-Headers',
            'X-Requested-With, Content-Type, Accept, Origin, Authorization'
        )
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
    $app->respond($response);
}
