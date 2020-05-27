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
ini_set('max_input_time', '60');

// Try to set max upload file size and read
ini_set('memory_limit', '128M');
ini_set('post_max_size', '16M');
ini_set('upload_max_filesize', '16M');

// See all error except warnings
error_reporting(E_ALL^E_WARNING);

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
require_once 'application/bookmark/LinkUtils.php';
require_once 'application/config/ConfigPlugin.php';
require_once 'application/http/HttpUtils.php';
require_once 'application/http/UrlUtils.php';
require_once 'application/updater/UpdaterUtils.php';
require_once 'application/FileUtils.php';
require_once 'application/TimeZone.php';
require_once 'application/Utils.php';

use Shaarli\ApplicationUtils;
use Shaarli\Bookmark\Bookmark;
use Shaarli\Bookmark\BookmarkFileService;
use Shaarli\Bookmark\BookmarkFilter;
use Shaarli\Bookmark\BookmarkServiceInterface;
use Shaarli\Bookmark\Exception\BookmarkNotFoundException;
use Shaarli\Config\ConfigManager;
use Shaarli\Container\ContainerBuilder;
use Shaarli\Feed\CachedPage;
use Shaarli\Feed\FeedBuilder;
use Shaarli\Formatter\BookmarkMarkdownFormatter;
use Shaarli\Formatter\FormatterFactory;
use Shaarli\History;
use Shaarli\Languages;
use Shaarli\Netscape\NetscapeBookmarkUtils;
use Shaarli\Plugin\PluginManager;
use Shaarli\Render\PageBuilder;
use Shaarli\Render\PageCacheManager;
use Shaarli\Render\ThemeUtils;
use Shaarli\Router;
use Shaarli\Security\LoginManager;
use Shaarli\Security\SessionManager;
use Shaarli\Thumbnailer;
use Shaarli\Updater\Updater;
use Shaarli\Updater\UpdaterUtils;
use Slim\App;

// Ensure the PHP version is supported
try {
    ApplicationUtils::checkPHPVersion('7.1', PHP_VERSION);
} catch (Exception $exc) {
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

// In dev mode, throw exception on any warning
if ($conf->get('dev.debug', false)) {
    // See all errors (for debugging only)
    error_reporting(-1);

    set_error_handler(function($errno, $errstr, $errfile, $errline, array $errcontext) {
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    });
}

$sessionManager = new SessionManager($_SESSION, $conf);
$loginManager = new LoginManager($conf, $sessionManager);
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
$conf->setEmpty('general.title', t('Shared bookmarks on '). escape(index_url($_SERVER)));
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
        session_destroy();
        session_set_cookie_params($expirationTime, $cookiedir, $_SERVER['SERVER_NAME']);
        session_start();
        session_regenerate_id(true);

        // Optional redirect after login:
        if (isset($_GET['post'])) {
            $uri = './?post='. urlencode($_GET['post']);
            foreach (array('description', 'source', 'title', 'tags') as $param) {
                if (!empty($_GET[$param])) {
                    $uri .= '&'.$param.'='.urlencode($_GET[$param]);
                }
            }
            header('Location: '. $uri);
            exit;
        }

        if (isset($_GET['edit_link'])) {
            header('Location: ./?edit_link='. escape($_GET['edit_link']));
            exit;
        }

        if (isset($_POST['returnurl'])) {
            // Prevent loops over login screen.
            if (strpos($_POST['returnurl'], '/login') === false) {
                header('Location: '. generateLocation($_POST['returnurl'], $_SERVER['HTTP_HOST']));
                exit;
            }
        }
        header('Location: ./?');
        exit;
    } else {
        $loginManager->handleFailedLogin($_SERVER);
        $redir = '?username='. urlencode($_POST['login']);
        if (isset($_GET['post'])) {
            $redir .= '&post=' . urlencode($_GET['post']);
            foreach (array('description', 'source', 'title', 'tags') as $param) {
                if (!empty($_GET[$param])) {
                    $redir .= '&' . $param . '=' . urlencode($_GET[$param]);
                }
            }
        }
        // Redirect to login screen.
        echo '<script>alert("'. t("Wrong login/password.") .'");document.location=\'./login'.$redir.'\';</script>';
        exit;
    }
}

// ------------------------------------------------------------------------------------------
// Token management for XSRF protection
// Token should be used in any form which acts on data (create,update,delete,import...).
if (!isset($_SESSION['tokens'])) {
    $_SESSION['tokens']=array();  // Token are attached to the session.
}

/**
 * Renders the linklist
 *
 * @param pageBuilder              $PAGE          pageBuilder instance.
 * @param BookmarkServiceInterface $linkDb        instance.
 * @param ConfigManager            $conf          Configuration Manager instance.
 * @param PluginManager            $pluginManager Plugin Manager instance.
 */
function showLinkList($PAGE, $linkDb, $conf, $pluginManager, $loginManager)
{
    buildLinkList($PAGE, $linkDb, $conf, $pluginManager, $loginManager);
    $PAGE->renderPage('linklist');
}

/**
 * Render HTML page (according to URL parameters and user rights)
 *
 * @param ConfigManager            $conf           Configuration Manager instance.
 * @param PluginManager            $pluginManager  Plugin Manager instance,
 * @param BookmarkServiceInterface $bookmarkService
 * @param History                  $history        instance
 * @param SessionManager           $sessionManager SessionManager instance
 * @param LoginManager             $loginManager   LoginManager instance
 */
function renderPage($conf, $pluginManager, $bookmarkService, $history, $sessionManager, $loginManager)
{
    $pageCacheManager = new PageCacheManager($conf->get('resource.page_cache'), $loginManager->isLoggedIn());
    $updater = new Updater(
        UpdaterUtils::read_updates_file($conf->get('resource.updates')),
        $bookmarkService,
        $conf,
        $loginManager->isLoggedIn()
    );
    try {
        $newUpdates = $updater->update();
        if (! empty($newUpdates)) {
            UpdaterUtils::write_updates_file(
                $conf->get('resource.updates'),
                $updater->getDoneUpdates()
            );

            $pageCacheManager->invalidateCaches();
        }
    } catch (Exception $e) {
        die($e->getMessage());
    }

    $PAGE = new PageBuilder($conf, $_SESSION, $bookmarkService, $sessionManager->generateToken(), $loginManager->isLoggedIn());
    $PAGE->assign('linkcount', $bookmarkService->count(BookmarkFilter::$ALL));
    $PAGE->assign('privateLinkcount', $bookmarkService->count(BookmarkFilter::$PRIVATE));
    $PAGE->assign('plugin_errors', $pluginManager->getErrors());

    // Determine which page will be rendered.
    $query = (isset($_SERVER['QUERY_STRING'])) ? $_SERVER['QUERY_STRING'] : '';
    $targetPage = Router::findPage($query, $_GET, $loginManager->isLoggedIn());

    if (// if the user isn't logged in
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

    foreach ($common_hooks as $name) {
        $plugin_data = array();
        $pluginManager->executeHooks(
            'render_' . $name,
            $plugin_data,
            array(
                'target' => $targetPage,
                'loggedin' => $loginManager->isLoggedIn()
            )
        );
        $PAGE->assign('plugins_' . $name, $plugin_data);
    }

    // -------- Display login form.
    if ($targetPage == Router::$PAGE_LOGIN) {
        header('Location: ./login');
        exit;
    }
    // -------- User wants to logout.
    if (isset($_SERVER['QUERY_STRING']) && startsWith($_SERVER['QUERY_STRING'], 'do=logout')) {
        header('Location: ./logout');
        exit;
    }

    // -------- Picture wall
    if ($targetPage == Router::$PAGE_PICWALL) {
        header('Location: ./picture-wall');
        exit;
    }

    // -------- Tag cloud
    if ($targetPage == Router::$PAGE_TAGCLOUD) {
        header('Location: ./tag-cloud');
        exit;
    }

    // -------- Tag list
    if ($targetPage == Router::$PAGE_TAGLIST) {
        header('Location: ./tag-list');
        exit;
    }

    // Daily page.
    if ($targetPage == Router::$PAGE_DAILY) {
        $dayParam = !empty($_GET['day']) ? '?day=' . escape($_GET['day']) : '';
        header('Location: ./daily'. $dayParam);
        exit;
    }

    // ATOM and RSS feed.
    if ($targetPage == Router::$PAGE_FEED_ATOM || $targetPage == Router::$PAGE_FEED_RSS) {
        $feedType = $targetPage == Router::$PAGE_FEED_RSS ? FeedBuilder::$FEED_RSS : FeedBuilder::$FEED_ATOM;

        header('Location: ./feed-'. $feedType .'?'. http_build_query($_GET));
        exit;
    }

    // Display opensearch plugin (XML)
    if ($targetPage == Router::$PAGE_OPENSEARCH) {
        header('Location: ./open-search');
        exit;
    }

    // -------- User clicks on a tag in a link: The tag is added to the list of searched tags (searchtags=...)
    if (isset($_GET['addtag'])) {
        header('Location: ./add-tag/'. $_GET['addtag']);
        exit;
    }

    // -------- User clicks on a tag in result count: Remove the tag from the list of searched tags (searchtags=...)
    if (isset($_GET['removetag'])) {
        header('Location: ./remove-tag/'. $_GET['removetag']);
        exit;
    }

    // -------- User wants to change the number of bookmarks per page (linksperpage=...)
    if (isset($_GET['linksperpage'])) {
        header('Location: ./links-per-page?nb='. $_GET['linksperpage']);
        exit;
    }

    // -------- User wants to see only private bookmarks (toggle)
    if (isset($_GET['visibility'])) {
        header('Location: ./visibility/'. $_GET['visibility']);
        exit;
    }

    // -------- User wants to see only untagged bookmarks (toggle)
    if (isset($_GET['untaggedonly'])) {
        header('Location: ./untagged-only');
        exit;
    }

    // -------- Handle other actions allowed for non-logged in users:
    if (!$loginManager->isLoggedIn()) {
        // User tries to post new link but is not logged in:
        // Show login screen, then redirect to ?post=...
        if (isset($_GET['post'])) {
            header( // Redirect to login page, then back to post link.
                'Location: ./login?post='.urlencode($_GET['post']).
                (!empty($_GET['title'])?'&title='.urlencode($_GET['title']):'').
                (!empty($_GET['description'])?'&description='.urlencode($_GET['description']):'').
                (!empty($_GET['tags'])?'&tags='.urlencode($_GET['tags']):'').
                (!empty($_GET['source'])?'&source='.urlencode($_GET['source']):'')
            );
            exit;
        }

        showLinkList($PAGE, $bookmarkService, $conf, $pluginManager, $loginManager);
        if (isset($_GET['edit_link'])) {
            header('Location: ./login?edit_link='. escape($_GET['edit_link']));
            exit;
        }

        exit; // Never remove this one! All operations below are reserved for logged in user.
    }

    // -------- All other functions are reserved for the registered user:

    // -------- Display the Tools menu if requested (import/export/bookmarklet...)
    if ($targetPage == Router::$PAGE_TOOLS) {
        header('Location: ./tools');
        exit;
    }

    // -------- User wants to change his/her password.
    if ($targetPage == Router::$PAGE_CHANGEPASSWORD) {
        header('Location: ./password');
        exit;
    }

    // -------- User wants to change configuration
    if ($targetPage == Router::$PAGE_CONFIGURE) {
        if (!empty($_POST['title'])) {
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
            $conf->set('general.retrieve_description', !empty($_POST['retrieveDescription']));
            $conf->set('resource.theme', escape($_POST['theme']));
            $conf->set('security.session_protection_disabled', !empty($_POST['disablesessionprotection']));
            $conf->set('privacy.default_private_links', !empty($_POST['privateLinkByDefault']));
            $conf->set('feed.rss_permalinks', !empty($_POST['enableRssPermalinks']));
            $conf->set('updates.check_updates', !empty($_POST['updateCheck']));
            $conf->set('privacy.hide_public_links', !empty($_POST['hidePublicLinks']));
            $conf->set('api.enabled', !empty($_POST['enableApi']));
            $conf->set('api.secret', escape($_POST['apiSecret']));
            $conf->set('formatter', escape($_POST['formatter']));

            if (! empty($_POST['language'])) {
                $conf->set('translation.language', escape($_POST['language']));
            }

            $thumbnailsMode = extension_loaded('gd') ? $_POST['enableThumbnails'] : Thumbnailer::MODE_NONE;
            if ($thumbnailsMode !== Thumbnailer::MODE_NONE
                && $thumbnailsMode !== $conf->get('thumbnails.mode', Thumbnailer::MODE_NONE)
            ) {
                $_SESSION['warnings'][] = t(
                    'You have enabled or changed thumbnails mode. '
                    .'<a href="./?do=thumbs_update">Please synchronize them</a>.'
                );
            }
            $conf->set('thumbnails.mode', $thumbnailsMode);

            try {
                $conf->write($loginManager->isLoggedIn());
                $history->updateSettings();
                $pageCacheManager->invalidateCaches();
            } catch (Exception $e) {
                error_log(
                    'ERROR while writing config file after configuration update.' . PHP_EOL .
                    $e->getMessage()
                );

                // TODO: do not handle exceptions/errors in JS.
                echo '<script>alert("'. $e->getMessage() .'");document.location=\'./?do=configure\';</script>';
                exit;
            }
            echo '<script>alert("'. t('Configuration was saved.') .'");document.location=\'./?do=configure\';</script>';
            exit;
        } else {
            // Show the configuration form.
            $PAGE->assign('title', $conf->get('general.title'));
            $PAGE->assign('theme', $conf->get('resource.theme'));
            $PAGE->assign('theme_available', ThemeUtils::getThemes($conf->get('resource.raintpl_tpl')));
            $PAGE->assign('formatter_available', ['default', 'markdown']);
            list($continents, $cities) = generateTimeZoneData(
                timezone_identifiers_list(),
                $conf->get('general.timezone')
            );
            $PAGE->assign('continents', $continents);
            $PAGE->assign('cities', $cities);
            $PAGE->assign('retrieve_description', $conf->get('general.retrieve_description'));
            $PAGE->assign('private_links_default', $conf->get('privacy.default_private_links', false));
            $PAGE->assign('session_protection_disabled', $conf->get('security.session_protection_disabled', false));
            $PAGE->assign('enable_rss_permalinks', $conf->get('feed.rss_permalinks', false));
            $PAGE->assign('enable_update_check', $conf->get('updates.check_updates', true));
            $PAGE->assign('hide_public_links', $conf->get('privacy.hide_public_links', false));
            $PAGE->assign('api_enabled', $conf->get('api.enabled', true));
            $PAGE->assign('api_secret', $conf->get('api.secret'));
            $PAGE->assign('languages', Languages::getAvailableLanguages());
            $PAGE->assign('gd_enabled', extension_loaded('gd'));
            $PAGE->assign('thumbnails_mode', $conf->get('thumbnails.mode', Thumbnailer::MODE_NONE));
            $PAGE->assign('pagetitle', t('Configure') .' - '. $conf->get('general.title', 'Shaarli'));
            $PAGE->renderPage('configure');
            exit;
        }
    }

    // -------- User wants to rename a tag or delete it
    if ($targetPage == Router::$PAGE_CHANGETAG) {
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
        $fromTag = escape($_POST['fromtag']);
        $count = 0;
        $bookmarks = $bookmarkService->search(['searchtags' => $fromTag], BookmarkFilter::$ALL, true);
        foreach ($bookmarks as $bookmark) {
            if ($toTag) {
                $bookmark->renameTag($fromTag, $toTag);
            } else {
                $bookmark->deleteTag($fromTag);
            }
            $bookmarkService->set($bookmark, false);
            $history->updateLink($bookmark);
            $count++;
        }
        $bookmarkService->save();
        $delete = empty($_POST['totag']);
        $redirect = $delete ? './do=changetag' : 'searchtags='. urlencode(escape($_POST['totag']));
        $alert = $delete
            ? sprintf(t('The tag was removed from %d link.', 'The tag was removed from %d bookmarks.', $count), $count)
            : sprintf(t('The tag was renamed in %d link.', 'The tag was renamed in %d bookmarks.', $count), $count);
        echo '<script>alert("'. $alert .'");document.location=\'?'. $redirect .'\';</script>';
        exit;
    }

    // -------- User wants to add a link without using the bookmarklet: Show form.
    if ($targetPage == Router::$PAGE_ADDLINK) {
        $PAGE->assign('pagetitle', t('Shaare a new link') .' - '. $conf->get('general.title', 'Shaarli'));
        $PAGE->renderPage('addlink');
        exit;
    }

    // -------- User clicked the "Save" button when editing a link: Save link to database.
    if (isset($_POST['save_edit'])) {
        // Go away!
        if (! $sessionManager->checkToken($_POST['token'])) {
            die(t('Wrong token.'));
        }

        // lf_id should only be present if the link exists.
        $id = isset($_POST['lf_id']) ? intval(escape($_POST['lf_id'])) : null;
        if ($id && $bookmarkService->exists($id)) {
            // Edit
            $bookmark = $bookmarkService->get($id);
        } else {
            // New link
            $bookmark = new Bookmark();
        }

        $bookmark->setTitle($_POST['lf_title']);
        $bookmark->setDescription($_POST['lf_description']);
        $bookmark->setUrl($_POST['lf_url'], $conf->get('security.allowed_protocols'));
        $bookmark->setPrivate(isset($_POST['lf_private']));
        $bookmark->setTagsString($_POST['lf_tags']);

        if ($conf->get('thumbnails.mode', Thumbnailer::MODE_NONE) !== Thumbnailer::MODE_NONE
            && ! $bookmark->isNote()
        ) {
            $thumbnailer = new Thumbnailer($conf);
            $bookmark->setThumbnail($thumbnailer->get($bookmark->getUrl()));
        }
        $bookmarkService->addOrSet($bookmark, false);

        // To preserve backward compatibility with 3rd parties, plugins still use arrays
        $factory = new FormatterFactory($conf, $loginManager->isLoggedIn());
        $formatter = $factory->getFormatter('raw');
        $data = $formatter->format($bookmark);
        $pluginManager->executeHooks('save_link', $data);

        $bookmark->fromArray($data);
        $bookmarkService->set($bookmark);

        // If we are called from the bookmarklet, we must close the popup:
        if (isset($_GET['source']) && ($_GET['source']=='bookmarklet' || $_GET['source']=='firefoxsocialapi')) {
            echo '<script>self.close();</script>';
            exit;
        }

        $returnurl = !empty($_POST['returnurl']) ? $_POST['returnurl'] : '?';
        $location = generateLocation($returnurl, $_SERVER['HTTP_HOST'], array('addlink', 'post', 'edit_link'));
        // Scroll to the link which has been edited.
        $location .= '#' . $bookmark->getShortUrl();
        // After saving the link, redirect to the page the user was on.
        header('Location: '. $location);
        exit;
    }

    // -------- User clicked the "Delete" button when editing a link: Delete link from database.
    if ($targetPage == Router::$PAGE_DELETELINK) {
        if (! $sessionManager->checkToken($_GET['token'])) {
            die(t('Wrong token.'));
        }

        $ids = trim($_GET['lf_linkdate']);
        if (strpos($ids, ' ') !== false) {
            // multiple, space-separated ids provided
            $ids = array_values(array_filter(
                preg_split('/\s+/', escape($ids)),
                function ($item) {
                    return $item !== '';
                }
            ));
        } else {
            // only a single id provided
            $shortUrl = $bookmarkService->get($ids)->getShortUrl();
            $ids = [$ids];
        }
        // assert at least one id is given
        if (!count($ids)) {
            die('no id provided');
        }
        $factory = new FormatterFactory($conf, $loginManager->isLoggedIn());
        $formatter = $factory->getFormatter('raw');
        foreach ($ids as $id) {
            $id = (int) escape($id);
            $bookmark = $bookmarkService->get($id);
            $data = $formatter->format($bookmark);
            $pluginManager->executeHooks('delete_link', $data);
            $bookmarkService->remove($bookmark, false);
        }
        $bookmarkService->save();

        // If we are called from the bookmarklet, we must close the popup:
        if (isset($_GET['source']) && ($_GET['source']=='bookmarklet' || $_GET['source']=='firefoxsocialapi')) {
            echo '<script>self.close();</script>';
            exit;
        }

        $location = '?';
        if (isset($_SERVER['HTTP_REFERER'])) {
            // Don't redirect to where we were previously if it was a permalink or an edit_link, because it would 404.
            $location = generateLocation(
                $_SERVER['HTTP_REFERER'],
                $_SERVER['HTTP_HOST'],
                ['delete_link', 'edit_link', ! empty($shortUrl) ? $shortUrl : null]
            );
        }

        header('Location: ' . $location); // After deleting the link, redirect to appropriate location
        exit;
    }

    // -------- User clicked either "Set public" or "Set private" bulk operation
    if ($targetPage == Router::$PAGE_CHANGE_VISIBILITY) {
        if (! $sessionManager->checkToken($_GET['token'])) {
            die(t('Wrong token.'));
        }

        $ids = trim($_GET['ids']);
        if (strpos($ids, ' ') !== false) {
            // multiple, space-separated ids provided
            $ids = array_values(array_filter(preg_split('/\s+/', escape($ids))));
        } else {
            // only a single id provided
            $ids = [$ids];
        }

        // assert at least one id is given
        if (!count($ids)) {
            die('no id provided');
        }
        // assert that the visibility is valid
        if (!isset($_GET['newVisibility']) || !in_array($_GET['newVisibility'], ['public', 'private'])) {
            die('invalid visibility');
        } else {
            $private = $_GET['newVisibility'] === 'private';
        }
        $factory = new FormatterFactory($conf, $loginManager->isLoggedIn());
        $formatter = $factory->getFormatter('raw');
        foreach ($ids as $id) {
            $id = (int) escape($id);
            $bookmark = $bookmarkService->get($id);
            $bookmark->setPrivate($private);

            // To preserve backward compatibility with 3rd parties, plugins still use arrays
            $data = $formatter->format($bookmark);
            $pluginManager->executeHooks('save_link', $data);
            $bookmark->fromArray($data);

            $bookmarkService->set($bookmark);
        }
        $bookmarkService->save();

        $location = '?';
        if (isset($_SERVER['HTTP_REFERER'])) {
            $location = generateLocation(
                $_SERVER['HTTP_REFERER'],
                $_SERVER['HTTP_HOST']
            );
        }
        header('Location: ' . $location); // After deleting the link, redirect to appropriate location
        exit;
    }

    // -------- User clicked the "EDIT" button on a link: Display link edit form.
    if (isset($_GET['edit_link'])) {
        $id = (int) escape($_GET['edit_link']);
        try {
            $link = $bookmarkService->get($id);  // Read database
        } catch (BookmarkNotFoundException $e) {
            // Link not found in database.
            header('Location: ?');
            exit;
        }

        $factory = new FormatterFactory($conf, $loginManager->isLoggedIn());
        $formatter = $factory->getFormatter('raw');
        $formattedLink = $formatter->format($link);
        $tags = $bookmarkService->bookmarksCountPerTag();
        if ($conf->get('formatter') === 'markdown') {
            $tags[BookmarkMarkdownFormatter::NO_MD_TAG] = 1;
        }
        $data = array(
            'link' => $formattedLink,
            'link_is_new' => false,
            'http_referer' => (isset($_SERVER['HTTP_REFERER']) ? escape($_SERVER['HTTP_REFERER']) : ''),
            'tags' => $tags,
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
        $bookmark = $bookmarkService->findByUrl($url);
        if (! $bookmark) {
            $link_is_new = true;
            // Get title if it was provided in URL (by the bookmarklet).
            $title = empty($_GET['title']) ? '' : escape($_GET['title']);
            // Get description if it was provided in URL (by the bookmarklet). [Bronco added that]
            $description = empty($_GET['description']) ? '' : escape($_GET['description']);
            $tags = empty($_GET['tags']) ? '' : escape($_GET['tags']);
            $private = !empty($_GET['private']) && $_GET['private'] === "1" ? 1 : 0;

            // If this is an HTTP(S) link, we try go get the page to extract
            // the title (otherwise we will to straight to the edit form.)
            if (empty($title) && strpos(get_url_scheme($url), 'http') !== false) {
                $retrieveDescription = $conf->get('general.retrieve_description');
                // Short timeout to keep the application responsive
                // The callback will fill $charset and $title with data from the downloaded page.
                get_http_response(
                    $url,
                    $conf->get('general.download_timeout', 30),
                    $conf->get('general.download_max_size', 4194304),
                    get_curl_download_callback($charset, $title, $description, $tags, $retrieveDescription)
                );
                if (! empty($title) && strtolower($charset) != 'utf-8') {
                    $title = mb_convert_encoding($title, 'utf-8', $charset);
                }
            }

            if ($url == '') {
                $title = $conf->get('general.default_note_title', t('Note: '));
            }
            $url = escape($url);
            $title = escape($title);

            $link = [
                'title' => $title,
                'url' => $url,
                'description' => $description,
                'tags' => $tags,
                'private' => $private,
            ];
        } else {
            $factory = new FormatterFactory($conf, $loginManager->isLoggedIn());
            $formatter = $factory->getFormatter('raw');
            $link = $formatter->format($bookmark);
        }

        $tags = $bookmarkService->bookmarksCountPerTag();
        if ($conf->get('formatter') === 'markdown') {
            $tags[BookmarkMarkdownFormatter::NO_MD_TAG] = 1;
        }
        $data = [
            'link' => $link,
            'link_is_new' => $link_is_new,
            'http_referer' => (isset($_SERVER['HTTP_REFERER']) ? escape($_SERVER['HTTP_REFERER']) : ''),
            'source' => (isset($_GET['source']) ? $_GET['source'] : ''),
            'tags' => $tags,
            'default_private_links' => $conf->get('privacy.default_private_links', false),
        ];
        $pluginManager->executeHooks('render_editlink', $data);

        foreach ($data as $key => $value) {
            $PAGE->assign($key, $value);
        }

        $PAGE->assign('pagetitle', t('Shaare') .' - '. $conf->get('general.title', 'Shaarli'));
        $PAGE->renderPage('editlink');
        exit;
    }

    if ($targetPage == Router::$PAGE_PINLINK) {
        if (! isset($_GET['id']) || !$bookmarkService->exists($_GET['id'])) {
            // FIXME! Use a proper error system.
            $msg = t('Invalid link ID provided');
            echo '<script>alert("'. $msg .'");document.location=\''. index_url($_SERVER) .'\';</script>';
            exit;
        }
        if (! $sessionManager->checkToken($_GET['token'])) {
            die('Wrong token.');
        }

        $link = $bookmarkService->get($_GET['id']);
        $link->setSticky(! $link->isSticky());
        $bookmarkService->set($link);
        header('Location: '.index_url($_SERVER));
        exit;
    }

    if ($targetPage == Router::$PAGE_EXPORT) {
        // Export bookmarks as a Netscape Bookmarks file

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
            $factory = new FormatterFactory($conf, $loginManager->isLoggedIn());
            $formatter = $factory->getFormatter('raw');
            $PAGE->assign(
                'links',
                NetscapeBookmarkUtils::filterAndFormat(
                    $bookmarkService,
                    $formatter,
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
            .$selection.'_'.$now->format(Bookmark::LINK_DATE_FORMAT).'.html'
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
            echo '<script>alert("'. $msg .'");document.location=\'./?do='.Router::$PAGE_IMPORT .'\';</script>';
            exit;
        }
        if (! $sessionManager->checkToken($_POST['token'])) {
            die('Wrong token.');
        }
        $status = NetscapeBookmarkUtils::import(
            $_POST,
            $_FILES,
            $bookmarkService,
            $conf,
            $history
        );
        echo '<script>alert("'.$status.'");document.location=\'./?do='
             .Router::$PAGE_IMPORT .'\';</script>';
        exit;
    }

    // Plugin administration page
    if ($targetPage == Router::$PAGE_PLUGINSADMIN) {
        $pluginMeta = $pluginManager->getPluginsMeta();

        // Split plugins into 2 arrays: ordered enabled plugins and disabled.
        $enabledPlugins = array_filter($pluginMeta, function ($v) {
            return $v['order'] !== false;
        });
        // Load parameters.
        $enabledPlugins = load_plugin_parameter_values($enabledPlugins, $conf->get('plugins', array()));
        uasort(
            $enabledPlugins,
            function ($a, $b) {
                return $a['order'] - $b['order'];
            }
        );
        $disabledPlugins = array_filter($pluginMeta, function ($v) {
            return $v['order'] === false;
        });

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
                $pluginManager->executeHooks('save_plugin_parameters', $_POST);
                unset($_POST['parameters_form']);
                foreach ($_POST as $param => $value) {
                    $conf->set('plugins.'. $param, escape($value));
                }
            } else {
                $conf->set('general.enabled_plugins', save_plugin_config($_POST));
            }
            $conf->write($loginManager->isLoggedIn());
            $history->updateSettings();
        } catch (Exception $e) {
            error_log(
                'ERROR while saving plugin configuration:.' . PHP_EOL .
                $e->getMessage()
            );

            // TODO: do not handle exceptions/errors in JS.
            echo '<script>alert("'
                . $e->getMessage()
                .'");document.location=\'./?do='
                . Router::$PAGE_PLUGINSADMIN
                .'\';</script>';
            exit;
        }
        header('Location: ./?do='. Router::$PAGE_PLUGINSADMIN);
        exit;
    }

    // Get a fresh token
    if ($targetPage == Router::$GET_TOKEN) {
        header('Content-Type:text/plain');
        echo $sessionManager->generateToken();
        exit;
    }

    // -------- Thumbnails Update
    if ($targetPage == Router::$PAGE_THUMBS_UPDATE) {
        $ids = [];
        foreach ($bookmarkService->search() as $bookmark) {
            // A note or not HTTP(S)
            if ($bookmark->isNote() || ! startsWith(strtolower($bookmark->getUrl()), 'http')) {
                continue;
            }
            $ids[] = $bookmark->getId();
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
        if (! $bookmarkService->exists($id)) {
            http_response_code(404);
            exit;
        }
        $thumbnailer = new Thumbnailer($conf);
        $bookmark = $bookmarkService->get($id);
        $bookmark->setThumbnail($thumbnailer->get($bookmark->getUrl()));
        $bookmarkService->set($bookmark);

        $factory = new FormatterFactory($conf, $loginManager->isLoggedIn());
        echo json_encode($factory->getFormatter('raw')->format($bookmark));
        exit;
    }

    // -------- Otherwise, simply display search form and bookmarks:
    showLinkList($PAGE, $bookmarkService, $conf, $pluginManager, $loginManager);
    exit;
}

/**
 * Template for the list of bookmarks (<div id="linklist">)
 * This function fills all the necessary fields in the $PAGE for the template 'linklist.html'
 *
 * @param pageBuilder              $PAGE          pageBuilder instance.
 * @param BookmarkServiceInterface $linkDb        LinkDB instance.
 * @param ConfigManager            $conf          Configuration Manager instance.
 * @param PluginManager            $pluginManager Plugin Manager instance.
 * @param LoginManager             $loginManager  LoginManager instance
 */
function buildLinkList($PAGE, $linkDb, $conf, $pluginManager, $loginManager)
{
    $factory = new FormatterFactory($conf, $loginManager->isLoggedIn());
    $formatter = $factory->getFormatter();

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
            $linksToDisplay = $linkDb->findByHash($_SERVER['QUERY_STRING']);
        } catch (BookmarkNotFoundException $e) {
            $PAGE->render404($e->getMessage());
            exit;
        }
    } else {
        // Filter bookmarks according search parameters.
        $visibility = ! empty($_SESSION['visibility']) ? $_SESSION['visibility'] : null;
        $request = [
            'searchtags' => $searchtags,
            'searchterm' => $searchterm,
        ];
        $linksToDisplay = $linkDb->search($request, $visibility, false, !empty($_SESSION['untaggedonly']));
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
    while ($i<$end && $i<count($keys)) {
        $link = $formatter->format($linksToDisplay[$keys[$i]]);

        // Logged in, thumbnails enabled, not a note,
        // and (never retrieved yet or no valid cache file)
        if ($loginManager->isLoggedIn()
            && $thumbnailsEnabled
            && !$linksToDisplay[$keys[$i]]->isNote()
            && $linksToDisplay[$keys[$i]]->getThumbnail() !== false
            && ! is_file($linksToDisplay[$keys[$i]]->getThumbnail())
        ) {
            $linksToDisplay[$keys[$i]]->setThumbnail($thumbnailer->get($link['url']));
            $linkDb->set($linksToDisplay[$keys[$i]], false);
            $updateDB = true;
            $link['thumbnail'] = $linksToDisplay[$keys[$i]]->getThumbnail();
        }

        // Check for both signs of a note: starting with ? and 7 chars long.
//        if ($link['url'][0] === '?' && strlen($link['url']) === 7) {
//            $link['url'] = index_url($_SERVER) . $link['url'];
//        }

        $linkDisp[$keys[$i]] = $link;
        $i++;
    }

    // If we retrieved new thumbnails, we update the database.
    if (!empty($updateDB)) {
        $linkDb->save();
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
        'links' => $linkDisp,
    );

    // If there is only a single link, we change on-the-fly the title of the page.
    if (count($linksToDisplay) == 1) {
        $data['pagetitle'] = $linksToDisplay[$keys[0]]->getTitle() .' - '. $conf->get('general.title');
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
function install($conf, $sessionManager, $loginManager)
{
    // On free.fr host, make sure the /sessions directory exists, otherwise login will not work.
    if (endsWith($_SERVER['HTTP_HOST'], '.free.fr') && !is_dir($_SERVER['DOCUMENT_ROOT'].'/sessions')) {
        mkdir($_SERVER['DOCUMENT_ROOT'].'/sessions', 0705);
    }


    // This part makes sure sessions works correctly.
    // (Because on some hosts, session.save_path may not be set correctly,
    // or we may not have write access to it.)
    if (isset($_GET['test_session'])
        && ( !isset($_SESSION) || !isset($_SESSION['session_tested']) || $_SESSION['session_tested']!='Working')) {
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
    if (!isset($_SESSION['session_tested'])) {
        // Step 1 : Try to store data in session and reload page.
        $_SESSION['session_tested'] = 'Working';  // Try to set a variable in session.
        header('Location: '.index_url($_SERVER).'?test_session');  // Redirect to check stored data.
    }
    if (isset($_GET['test_session'])) {
        // Step 3: Sessions are OK. Remove test parameter from URL.
        header('Location: '.index_url($_SERVER));
    }


    if (!empty($_POST['setlogin']) && !empty($_POST['setpassword'])) {
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
            $conf->set('general.title', 'Shared bookmarks on '.escape(index_url($_SERVER)));
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
        } catch (Exception $e) {
            error_log(
                'ERROR while writing config file after installation.' . PHP_EOL .
                    $e->getMessage()
            );

            // TODO: do not handle exceptions/errors in JS.
            echo '<script>alert("'. $e->getMessage() .'");document.location=\'?\';</script>';
            exit;
        }

        $history = new History($conf->get('resource.history'));
        $bookmarkService = new BookmarkFileService($conf, $history, true);
        if ($bookmarkService->count() === 0) {
            $bookmarkService->initialize();
        }

        echo '<script>alert('
            .'"Shaarli is now configured. '
            .'Please enter your login/password and start shaaring your bookmarks!"'
            .');document.location=\'./login\';</script>';
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

if (!isset($_SESSION['LINKS_PER_PAGE'])) {
    $_SESSION['LINKS_PER_PAGE'] = $conf->get('general.links_per_page', 20);
}

try {
    $history = new History($conf->get('resource.history'));
} catch (Exception $e) {
    die($e->getMessage());
}

$linkDb = new BookmarkFileService($conf, $history, $loginManager->isLoggedIn());

if (isset($_SERVER['QUERY_STRING']) && startsWith($_SERVER['QUERY_STRING'], 'do=dailyrss')) {
    header('Location: ./daily-rss');
    exit;
}

$containerBuilder = new ContainerBuilder($conf, $sessionManager, $loginManager, WEB_PATH);
$container = $containerBuilder->build();
$app = new App($container);

// REST API routes
$app->group('/api/v1', function () {
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

    $this->get('/history', '\Shaarli\Api\Controllers\HistoryController:getHistory')->setName('getHistory');
})->add('\Shaarli\Api\ApiMiddleware');

$app->group('', function () {
    /* -- PUBLIC --*/
    $this->get('/login', '\Shaarli\Front\Controller\Visitor\LoginController:index')->setName('login');
    $this->get('/picture-wall', '\Shaarli\Front\Controller\Visitor\PictureWallController:index')->setName('picwall');
    $this->get('/tag-cloud', '\Shaarli\Front\Controller\Visitor\TagCloudController:cloud')->setName('tagcloud');
    $this->get('/tag-list', '\Shaarli\Front\Controller\Visitor\TagCloudController:list')->setName('taglist');
    $this->get('/daily', '\Shaarli\Front\Controller\Visitor\DailyController:index')->setName('daily');
    $this->get('/daily-rss', '\Shaarli\Front\Controller\Visitor\DailyController:rss')->setName('dailyrss');
    $this->get('/feed-atom', '\Shaarli\Front\Controller\Visitor\FeedController:atom')->setName('feedatom');
    $this->get('/feed-rss', '\Shaarli\Front\Controller\Visitor\FeedController:rss')->setName('feedrss');
    $this->get('/open-search', '\Shaarli\Front\Controller\Visitor\OpenSearchController:index')->setName('opensearch');

    $this->get('/add-tag/{newTag}', '\Shaarli\Front\Controller\Visitor\TagController:addTag')->setName('add-tag');
    $this->get('/remove-tag/{tag}', '\Shaarli\Front\Controller\Visitor\TagController:removeTag')->setName('remove-tag');

    /* -- LOGGED IN -- */
    $this->get('/logout', '\Shaarli\Front\Controller\Admin\LogoutController:index')->setName('logout');
    $this->get('/tools', '\Shaarli\Front\Controller\Admin\ToolsController:index')->setName('tools');
    $this->get('/password', '\Shaarli\Front\Controller\Admin\PasswordController:index')->setName('password');
    $this->post('/password', '\Shaarli\Front\Controller\Admin\PasswordController:change')->setName('changePassword');

    $this
        ->get('/links-per-page', '\Shaarli\Front\Controller\Admin\SessionFilterController:linksPerPage')
        ->setName('filter-links-per-page')
    ;
    $this
        ->get('/visibility/{visibility}', '\Shaarli\Front\Controller\Admin\SessionFilterController:visibility')
        ->setName('visibility')
    ;
    $this
        ->get('/untagged-only', '\Shaarli\Front\Controller\Admin\SessionFilterController:untaggedOnly')
        ->setName('untagged-only')
    ;
})->add('\Shaarli\Front\ShaarliMiddleware');

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
