<?php
/**
 * Shaarli v0.8.1 - Shaare your links...
 *
 * The personal, minimalist, super-fast, database free, bookmarking service.
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
 * Requires: PHP 5.3.x
 */

// Set 'UTC' as the default timezone if it is not defined in php.ini
// See http://php.net/manual/en/datetime.configuration.php#ini.date.timezone
if (date_default_timezone_get() == '') {
    date_default_timezone_set('UTC');
}

/*
 * PHP configuration
 */
define('shaarli_version', '0.8.1');

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
        ."- https://github.com/shaarli/Shaarli/wiki/Server-requirements\n"
        ."- https://github.com/shaarli/Shaarli/wiki/Download-and-Installation";
    exit;
}
require_once 'inc/rain.tpl.class.php';
require_once __DIR__ . '/vendor/autoload.php';

// Shaarli library
require_once 'application/ApplicationUtils.php';
require_once 'application/Cache.php';
require_once 'application/CachedPage.php';
require_once 'application/config/ConfigManager.php';
require_once 'application/config/ConfigPlugin.php';
require_once 'application/FeedBuilder.php';
require_once 'application/FileUtils.php';
require_once 'application/HttpUtils.php';
require_once 'application/Languages.php';
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
use \Shaarli\ThemeUtils;

// Ensure the PHP version is supported
try {
    ApplicationUtils::checkPHPVersion('5.3', PHP_VERSION);
} catch(Exception $exc) {
    header('Content-Type: text/plain; charset=utf-8');
    echo $exc->getMessage();
    exit;
}

// Force cookie path (but do not change lifetime)
$cookie = session_get_cookie_params();
$cookiedir = '';
if (dirname($_SERVER['SCRIPT_NAME']) != '/') {
    $cookiedir = dirname($_SERVER["SCRIPT_NAME"]).'/';
}
// Set default cookie expiration and path.
session_set_cookie_params($cookie['lifetime'], $cookiedir, $_SERVER['SERVER_NAME']);
// Set session parameters on server side.
// If the user does not access any page within this time, his/her session is considered expired.
define('INACTIVITY_TIMEOUT', 3600); // in seconds.
// Use cookies to store session.
ini_set('session.use_cookies', 1);
// Force cookies for session (phpsessionID forbidden in URL).
ini_set('session.use_only_cookies', 1);
// Prevent PHP form using sessionID in URL if cookies are disabled.
ini_set('session.use_trans_sid', false);

session_name('shaarli');
// Start session if needed (Some server auto-start sessions).
if (session_id() == '') {
    session_start();
}

// Regenerate session ID if invalid or not defined in cookie.
if (isset($_COOKIE['shaarli']) && !is_session_id_valid($_COOKIE['shaarli'])) {
    session_regenerate_id(true);
    $_COOKIE['shaarli'] = session_id();
}

$conf = new ConfigManager();
$conf->setEmpty('general.timezone', date_default_timezone_get());
$conf->setEmpty('general.title', 'Shared links on '. escape(index_url($_SERVER)));
RainTPL::$tpl_dir = $conf->get('resource.raintpl_tpl').'/'.$conf->get('resource.theme').'/'; // template directory
RainTPL::$cache_dir = $conf->get('resource.raintpl_tmp'); // cache directory

$pluginManager = new PluginManager($conf);
$pluginManager->load($conf->get('general.enabled_plugins'));

date_default_timezone_set($conf->get('general.timezone', 'UTC'));

ob_start();  // Output buffering for the page cache.

// In case stupid admin has left magic_quotes enabled in php.ini:
if (get_magic_quotes_gpc())
{
    function stripslashes_deep($value) { $value = is_array($value) ? array_map('stripslashes_deep', $value) : stripslashes($value); return $value; }
    $_POST = array_map('stripslashes_deep', $_POST);
    $_GET = array_map('stripslashes_deep', $_GET);
    $_COOKIE = array_map('stripslashes_deep', $_COOKIE);
}

// Prevent caching on client side or proxy: (yes, it's ugly)
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (! is_file($conf->getConfigFileExt())) {
    // Ensure Shaarli has proper access to its resources
    $errors = ApplicationUtils::checkResourcePermissions($conf);

    if ($errors != array()) {
        $message = '<p>Insufficient permissions:</p><ul>';

        foreach ($errors as $error) {
            $message .= '<li>'.$error.'</li>';
        }
        $message .= '</ul>';

        header('Content-Type: text/html; charset=utf-8');
        echo $message;
        exit;
    }

    // Display the installation form if no existing config is found
    install($conf);
}

// a token depending of deployment salt, user password, and the current ip
define('STAY_SIGNED_IN_TOKEN', sha1($conf->get('credentials.hash') . $_SERVER['REMOTE_ADDR'] . $conf->get('credentials.salt')));

// Sniff browser language and set date format accordingly.
if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
    autoLocale($_SERVER['HTTP_ACCEPT_LANGUAGE']);
}

/**
 * Checking session state (i.e. is the user still logged in)
 *
 * @param ConfigManager $conf The configuration manager.
 *
 * @return bool: true if the user is logged in, false otherwise.
 */
function setup_login_state($conf)
{
	if ($conf->get('security.open_shaarli')) {
	    return true;
	}
	$userIsLoggedIn = false; // By default, we do not consider the user as logged in;
	$loginFailure = false; // If set to true, every attempt to authenticate the user will fail. This indicates that an important condition isn't met.
	if (! $conf->exists('credentials.login')) {
	    $userIsLoggedIn = false;  // Shaarli is not configured yet.
	    $loginFailure = true;
	}
	if (isset($_COOKIE['shaarli_staySignedIn']) &&
	    $_COOKIE['shaarli_staySignedIn']===STAY_SIGNED_IN_TOKEN &&
	    !$loginFailure)
	{
	    fillSessionInfo($conf);
	    $userIsLoggedIn = true;
	}
	// If session does not exist on server side, or IP address has changed, or session has expired, logout.
	if (empty($_SESSION['uid'])
        || ($conf->get('security.session_protection_disabled') === false && $_SESSION['ip'] != allIPs())
        || time() >= $_SESSION['expires_on'])
	{
	    logout();
	    $userIsLoggedIn = false;
	    $loginFailure = true;
	}
	if (!empty($_SESSION['longlastingsession'])) {
	    $_SESSION['expires_on']=time()+$_SESSION['longlastingsession']; // In case of "Stay signed in" checked.
	}
	else {
	    $_SESSION['expires_on']=time()+INACTIVITY_TIMEOUT; // Standard session expiration date.
	}
	if (!$loginFailure) {
	    $userIsLoggedIn = true;
	}

	return $userIsLoggedIn;
}
$userIsLoggedIn = setup_login_state($conf);

/**
 * PubSubHubbub protocol support (if enabled)  [UNTESTED]
 * (Source: http://aldarone.fr/les-flux-rss-shaarli-et-pubsubhubbub/ )
 *
 * @param ConfigManager $conf Configuration Manager instance.
 */
function pubsubhub($conf)
{
    $pshUrl = $conf->get('config.PUBSUBHUB_URL');
    if (!empty($pshUrl))
    {
        include_once './publisher.php';
        $p = new Publisher($pshUrl);
        $topic_url = array (
            index_url($_SERVER).'?do=atom',
            index_url($_SERVER).'?do=rss'
        );
        $p->publish_update($topic_url);
    }
}

// ------------------------------------------------------------------------------------------
// Session management

// Returns the IP address of the client (Used to prevent session cookie hijacking.)
function allIPs()
{
    $ip = $_SERVER['REMOTE_ADDR'];
    // Then we use more HTTP headers to prevent session hijacking from users behind the same proxy.
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) { $ip=$ip.'_'.$_SERVER['HTTP_X_FORWARDED_FOR']; }
    if (isset($_SERVER['HTTP_CLIENT_IP'])) { $ip=$ip.'_'.$_SERVER['HTTP_CLIENT_IP']; }
    return $ip;
}

/**
 * Load user session.
 *
 * @param ConfigManager $conf Configuration Manager instance.
 */
function fillSessionInfo($conf)
{
	$_SESSION['uid'] = sha1(uniqid('',true).'_'.mt_rand()); // Generate unique random number (different than phpsessionid)
	$_SESSION['ip']=allIPs();                // We store IP address(es) of the client to make sure session is not hijacked.
	$_SESSION['username']= $conf->get('credentials.login');
	$_SESSION['expires_on']=time()+INACTIVITY_TIMEOUT;  // Set session expiration.
}

/**
 * Check that user/password is correct.
 *
 * @param string        $login    Username
 * @param string        $password User password
 * @param ConfigManager $conf     Configuration Manager instance.
 *
 * @return bool: authentication successful or not.
 */
function check_auth($login, $password, $conf)
{
    $hash = sha1($password . $login . $conf->get('credentials.salt'));
    if ($login == $conf->get('credentials.login') && $hash == $conf->get('credentials.hash'))
    {   // Login/password is correct.
		fillSessionInfo($conf);
        logm($conf->get('resource.log'), $_SERVER['REMOTE_ADDR'], 'Login successful');
        return true;
    }
    logm($conf->get('resource.log'), $_SERVER['REMOTE_ADDR'], 'Login failed for user '.$login);
    return false;
}

// Returns true if the user is logged in.
function isLoggedIn()
{
    global $userIsLoggedIn;
    return $userIsLoggedIn;
}

// Force logout.
function logout() {
    if (isset($_SESSION)) {
        unset($_SESSION['uid']);
        unset($_SESSION['ip']);
        unset($_SESSION['username']);
        unset($_SESSION['privateonly']);
    }
    setcookie('shaarli_staySignedIn', FALSE, 0, WEB_PATH);
}


// ------------------------------------------------------------------------------------------
// Brute force protection system
// Several consecutive failed logins will ban the IP address for 30 minutes.
if (!is_file($conf->get('resource.ban_file', 'data/ipbans.php'))) {
    // FIXME! globals
    file_put_contents(
        $conf->get('resource.ban_file', 'data/ipbans.php'),
        "<?php\n\$GLOBALS['IPBANS']=".var_export(array('FAILURES'=>array(),'BANS'=>array()),true).";\n?>"
    );
}
include $conf->get('resource.ban_file', 'data/ipbans.php');
/**
 * Signal a failed login. Will ban the IP if too many failures:
 *
 * @param ConfigManager $conf Configuration Manager instance.
 */
function ban_loginFailed($conf)
{
    $ip = $_SERVER['REMOTE_ADDR'];
    $trusted = $conf->get('security.trusted_proxies', array());
    if (in_array($ip, $trusted)) {
        $ip = getIpAddressFromProxy($_SERVER, $trusted);
        if (!$ip) {
            return;
        }
    }
    $gb = $GLOBALS['IPBANS'];
    if (! isset($gb['FAILURES'][$ip])) {
        $gb['FAILURES'][$ip]=0;
    }
    $gb['FAILURES'][$ip]++;
    if ($gb['FAILURES'][$ip] > ($conf->get('security.ban_after') - 1))
    {
        $gb['BANS'][$ip] = time() + $conf->get('security.ban_after', 1800);
        logm($conf->get('resource.log'), $_SERVER['REMOTE_ADDR'], 'IP address banned from login');
    }
    $GLOBALS['IPBANS'] = $gb;
    file_put_contents(
        $conf->get('resource.ban_file', 'data/ipbans.php'),
        "<?php\n\$GLOBALS['IPBANS']=".var_export($gb,true).";\n?>"
    );
}

/**
 * Signals a successful login. Resets failed login counter.
 *
 * @param ConfigManager $conf Configuration Manager instance.
 */
function ban_loginOk($conf)
{
    $ip = $_SERVER['REMOTE_ADDR'];
    $gb = $GLOBALS['IPBANS'];
    unset($gb['FAILURES'][$ip]); unset($gb['BANS'][$ip]);
    $GLOBALS['IPBANS'] = $gb;
    file_put_contents(
        $conf->get('resource.ban_file', 'data/ipbans.php'),
        "<?php\n\$GLOBALS['IPBANS']=".var_export($gb,true).";\n?>"
    );
}

/**
 * Checks if the user CAN login. If 'true', the user can try to login.
 *
 * @param ConfigManager $conf Configuration Manager instance.
 *
 * @return bool: true if the user is allowed to login.
 */
function ban_canLogin($conf)
{
    $ip=$_SERVER["REMOTE_ADDR"]; $gb=$GLOBALS['IPBANS'];
    if (isset($gb['BANS'][$ip]))
    {
        // User is banned. Check if the ban has expired:
        if ($gb['BANS'][$ip]<=time())
        {   // Ban expired, user can try to login again.
            logm($conf->get('resource.log'), $_SERVER['REMOTE_ADDR'], 'Ban lifted.');
            unset($gb['FAILURES'][$ip]); unset($gb['BANS'][$ip]);
            file_put_contents(
                $conf->get('resource.ban_file', 'data/ipbans.php'),
                "<?php\n\$GLOBALS['IPBANS']=".var_export($gb,true).";\n?>"
            );
            return true; // Ban has expired, user can login.
        }
        return false; // User is banned.
    }
    return true; // User is not banned.
}

// ------------------------------------------------------------------------------------------
// Process login form: Check if login/password is correct.
if (isset($_POST['login']))
{
    if (!ban_canLogin($conf)) die('I said: NO. You are banned for the moment. Go away.');
    if (isset($_POST['password'])
        && tokenOk($_POST['token'])
        && (check_auth($_POST['login'], $_POST['password'], $conf))
    ) {   // Login/password is OK.
        ban_loginOk($conf);
        // If user wants to keep the session cookie even after the browser closes:
        if (!empty($_POST['longlastingsession']))
        {
			setcookie('shaarli_staySignedIn', STAY_SIGNED_IN_TOKEN, time()+31536000, WEB_PATH);
            $_SESSION['longlastingsession']=31536000;  // (31536000 seconds = 1 year)
            $_SESSION['expires_on']=time()+$_SESSION['longlastingsession'];  // Set session expiration on server-side.

            $cookiedir = ''; if(dirname($_SERVER['SCRIPT_NAME'])!='/') $cookiedir=dirname($_SERVER["SCRIPT_NAME"]).'/';
            session_set_cookie_params($_SESSION['longlastingsession'],$cookiedir,$_SERVER['SERVER_NAME']); // Set session cookie expiration on client side
            // Note: Never forget the trailing slash on the cookie path!
            session_regenerate_id(true);  // Send cookie with new expiration date to browser.
        }
        else // Standard session expiration (=when browser closes)
        {
            $cookiedir = ''; if(dirname($_SERVER['SCRIPT_NAME'])!='/') $cookiedir=dirname($_SERVER["SCRIPT_NAME"]).'/';
            session_set_cookie_params(0,$cookiedir,$_SERVER['SERVER_NAME']); // 0 means "When browser closes"
            session_regenerate_id(true);
        }

        // Optional redirect after login:
        if (isset($_GET['post'])) {
            $uri = '?post='. urlencode($_GET['post']);
            foreach (array('description', 'source', 'title') as $param) {
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
    }
    else
    {
        ban_loginFailed($conf);
        $redir = '&username='. $_POST['login'];
        if (isset($_GET['post'])) {
            $redir .= '&post=' . urlencode($_GET['post']);
            foreach (array('description', 'source', 'title') as $param) {
                if (!empty($_GET[$param])) {
                    $redir .= '&' . $param . '=' . urlencode($_GET[$param]);
                }
            }
        }
        echo '<script>alert("Wrong login/password.");document.location=\'?do=login'.$redir.'\';</script>'; // Redirect to login screen.
        exit;
    }
}

// ------------------------------------------------------------------------------------------
// Misc utility functions:

// Convert post_max_size/upload_max_filesize (e.g. '16M') parameters to bytes.
function return_bytes($val)
{
    $val = trim($val); $last=strtolower($val[strlen($val)-1]);
    switch($last)
    {
        case 'g': $val *= 1024;
        case 'm': $val *= 1024;
        case 'k': $val *= 1024;
    }
    return $val;
}

// Try to determine max file size for uploads (POST).
// Returns an integer (in bytes)
function getMaxFileSize()
{
    $size1 = return_bytes(ini_get('post_max_size'));
    $size2 = return_bytes(ini_get('upload_max_filesize'));
    // Return the smaller of two:
    $maxsize = min($size1,$size2);
    // FIXME: Then convert back to readable notations ? (e.g. 2M instead of 2000000)
    return $maxsize;
}

// ------------------------------------------------------------------------------------------
// Token management for XSRF protection
// Token should be used in any form which acts on data (create,update,delete,import...).
if (!isset($_SESSION['tokens'])) $_SESSION['tokens']=array();  // Token are attached to the session.

/**
 * Returns a token.
 *
 * @param ConfigManager $conf Configuration Manager instance.
 *
 * @return string token.
 */
function getToken($conf)
{
    $rnd = sha1(uniqid('', true) .'_'. mt_rand() . $conf->get('credentials.salt'));  // We generate a random string.
    $_SESSION['tokens'][$rnd]=1;  // Store it on the server side.
    return $rnd;
}

// Tells if a token is OK. Using this function will destroy the token.
// true=token is OK.
function tokenOk($token)
{
    if (isset($_SESSION['tokens'][$token]))
    {
        unset($_SESSION['tokens'][$token]); // Token is used: destroy it.
        return true; // Token is OK.
    }
    return false; // Wrong token, or already used.
}

/**
 * Daily RSS feed: 1 RSS entry per day giving all the links on that day.
 * Gives the last 7 days (which have links).
 * This RSS feed cannot be filtered.
 *
 * @param ConfigManager $conf Configuration Manager instance.
 */
function showDailyRSS($conf) {
    // Cache system
    $query = $_SERVER['QUERY_STRING'];
    $cache = new CachedPage(
        $conf->get('config.PAGE_CACHE'),
        page_url($_SERVER),
        startsWith($query,'do=dailyrss') && !isLoggedIn()
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
        isLoggedIn(),
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
            $link['formatedDescription'] = format_description($link['description'], $conf->get('redirector.url'));
            $link['thumbnail'] = thumbnail($conf, $link['url']);
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
 * @param PluginManager $pluginManager Plugin Manager instane.
 */
function showDaily($pageBuilder, $LINKSDB, $conf, $pluginManager)
{
    $day=date('Ymd',strtotime('-1 day')); // Yesterday, in format YYYYMMDD.
    if (isset($_GET['day'])) $day=$_GET['day'];

    $days = $LINKSDB->days();
    $i = array_search($day,$days);
    if ($i===false) { $i=count($days)-1; $day=$days[$i]; }
    $previousday='';
    $nextday='';
    if ($i!==false)
    {
        if ($i>=1) $previousday=$days[$i-1];
        if ($i<count($days)-1) $nextday=$days[$i+1];
    }

    try {
        $linksToDisplay = $LINKSDB->filterDay($day);
    } catch (Exception $exc) {
        error_log($exc);
        $linksToDisplay = array();
    }

    // We pre-format some fields for proper output.
    foreach($linksToDisplay as $key=>$link)
    {

        $taglist = explode(' ',$link['tags']);
        uasort($taglist, 'strcasecmp');
        $linksToDisplay[$key]['taglist']=$taglist;
        $linksToDisplay[$key]['formatedDescription'] = format_description($link['description'], $conf->get('redirector.url'));
        $linksToDisplay[$key]['thumbnail'] = thumbnail($conf, $link['url']);
        $linksToDisplay[$key]['timestamp'] =  $link['created']->getTimestamp();
    }

    /* We need to spread the articles on 3 columns.
       I did not want to use a JavaScript lib like http://masonry.desandro.com/
       so I manually spread entries with a simple method: I roughly evaluate the
       height of a div according to title and description length.
    */
    $columns=array(array(),array(),array()); // Entries to display, for each column.
    $fill=array(0,0,0);  // Rough estimate of columns fill.
    foreach($linksToDisplay as $key=>$link)
    {
        // Roughly estimate length of entry (by counting characters)
        // Title: 30 chars = 1 line. 1 line is 30 pixels height.
        // Description: 836 characters gives roughly 342 pixel height.
        // This is not perfect, but it's usually OK.
        $length=strlen($link['title'])+(342*strlen($link['description']))/836;
        if ($link['thumbnail']) $length +=100; // 1 thumbnails roughly takes 100 pixels height.
        // Then put in column which is the less filled:
        $smallest=min($fill); // find smallest value in array.
        $index=array_search($smallest,$fill); // find index of this smallest value.
        array_push($columns[$index],$link); // Put entry in this column.
        $fill[$index]+=$length;
    }

    $dayDate = DateTime::createFromFormat(LinkDB::LINK_DATE_FORMAT, $day.'_000000');
    $data = array(
        'linksToDisplay' => $linksToDisplay,
        'cols' => $columns,
        'day' => $dayDate->getTimestamp(),
        'previousday' => $previousday,
        'nextday' => $nextday,
    );

    $pluginManager->executeHooks('render_daily', $data, array('loggedin' => isLoggedIn()));

    foreach ($data as $key => $value) {
        $pageBuilder->assign($key, $value);
    }

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
function showLinkList($PAGE, $LINKSDB, $conf, $pluginManager) {
    buildLinkList($PAGE,$LINKSDB, $conf, $pluginManager); // Compute list of links to display
    $PAGE->renderPage('linklist');
}

/**
 * Render HTML page (according to URL parameters and user rights)
 *
 * @param ConfigManager $conf          Configuration Manager instance.
 * @param PluginManager $pluginManager Plugin Manager instance,
 * @param LinkDB        $LINKSDB
 */
function renderPage($conf, $pluginManager, $LINKSDB)
{
    $updater = new Updater(
        read_updates_file($conf->get('resource.updates')),
        $LINKSDB,
        $conf,
        isLoggedIn()
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

    $PAGE = new PageBuilder($conf);
    $PAGE->assign('linkcount', count($LINKSDB));
    $PAGE->assign('privateLinkcount', count_private($LINKSDB));
    $PAGE->assign('plugin_errors', $pluginManager->getErrors());

    // Determine which page will be rendered.
    $query = (isset($_SERVER['QUERY_STRING'])) ? $_SERVER['QUERY_STRING'] : '';
    $targetPage = Router::findPage($query, $_GET, isLoggedIn());

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
                'loggedin' => isLoggedIn()
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
        $PAGE->renderPage('loginform');
        exit;
    }
    // -------- User wants to logout.
    if (isset($_SERVER['QUERY_STRING']) && startsWith($_SERVER['QUERY_STRING'], 'do=logout'))
    {
        invalidateCaches($conf->get('resource.page_cache'));
        logout();
        header('Location: ?');
        exit;
    }

    // -------- Picture wall
    if ($targetPage == Router::$PAGE_PICWALL)
    {
        // Optionally filter the results:
        $links = $LINKSDB->filterSearch($_GET);
        $linksToDisplay = array();

        // Get only links which have a thumbnail.
        foreach($links as $link)
        {
            $permalink='?'.$link['shorturl'];
            $thumb=lazyThumbnail($conf, $link['url'],$permalink);
            if ($thumb!='') // Only output links which have a thumbnail.
            {
                $link['thumbnail']=$thumb; // Thumbnail HTML code.
                $linksToDisplay[]=$link; // Add to array.
            }
        }

        $data = array(
            'linksToDisplay' => $linksToDisplay,
        );
        $pluginManager->executeHooks('render_picwall', $data, array('loggedin' => isLoggedIn()));

        foreach ($data as $key => $value) {
            $PAGE->assign($key, $value);
        }

        $PAGE->renderPage('picwall');
        exit;
    }

    // -------- Tag cloud
    if ($targetPage == Router::$PAGE_TAGCLOUD)
    {
        $tags= $LINKSDB->allTags();

        // We sort tags alphabetically, then choose a font size according to count.
        // First, find max value.
        $maxcount = 0;
        foreach ($tags as $value) {
            $maxcount = max($maxcount, $value);
        }

        // Sort tags alphabetically: case insensitive, support locale if available.
        uksort($tags, function($a, $b) {
            // Collator is part of PHP intl.
            if (class_exists('Collator')) {
                $c = new Collator(setlocale(LC_COLLATE, 0));
                if (!intl_is_failure(intl_get_error_code())) {
                    return $c->compare($a, $b);
                }
            }
            return strcasecmp($a, $b);
        });

        $tagList = array();
        foreach($tags as $key => $value) {
            // Tag font size scaling:
            //   default 15 and 30 logarithm bases affect scaling,
            //   22 and 6 are arbitrary font sizes for max and min sizes.
            $size = log($value, 15) / log($maxcount, 30) * 2.2 + 0.8;
            $tagList[$key] = array(
                'count' => $value,
                'size' => number_format($size, 2, '.', ''),
            );
        }

        $data = array(
            'tags' => $tagList,
        );
        $pluginManager->executeHooks('render_tagcloud', $data, array('loggedin' => isLoggedIn()));

        foreach ($data as $key => $value) {
            $PAGE->assign($key, $value);
        }

        $PAGE->renderPage('tagcloud');
        exit;
    }

    // Daily page.
    if ($targetPage == Router::$PAGE_DAILY) {
        showDaily($PAGE, $LINKSDB, $conf, $pluginManager);
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
            startsWith($query,'do='. $targetPage) && !isLoggedIn()
        );
        $cached = $cache->cachedVersion();
        if (!empty($cached)) {
            echo $cached;
            exit;
        }

        // Generate data.
        $feedGenerator = new FeedBuilder($LINKSDB, $feedType, $_SERVER, $_GET, isLoggedIn());
        $feedGenerator->setLocale(strtolower(setlocale(LC_COLLATE, 0)));
        $feedGenerator->setHideDates($conf->get('privacy.hide_timestamps') && !isLoggedIn());
        $feedGenerator->setUsePermalinks(isset($_GET['permalinks']) || !$conf->get('feed.rss_permalinks'));
        $data = $feedGenerator->buildData();

        // Process plugin hook.
        $pluginManager->executeHooks('render_feed', $data, array(
            'loggedin' => isLoggedIn(),
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
        else if ($addtag) {
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

        header('Location: '. generateLocation($_SERVER['HTTP_REFERER'], $_SERVER['HTTP_HOST'], array('linksperpage')));
        exit;
    }

    // -------- User wants to see only private links (toggle)
    if (isset($_GET['privateonly'])) {
        if (empty($_SESSION['privateonly'])) {
            $_SESSION['privateonly'] = 1; // See only private links
        } else {
            unset($_SESSION['privateonly']); // See all links
        }

        header('Location: '. generateLocation($_SERVER['HTTP_REFERER'], $_SERVER['HTTP_HOST'], array('privateonly')));
        exit;
    }

    // -------- Handle other actions allowed for non-logged in users:
    if (!isLoggedIn())
    {
        // User tries to post new link but is not logged in:
        // Show login screen, then redirect to ?post=...
        if (isset($_GET['post']))
        {
            header('Location: ?do=login&post='.urlencode($_GET['post']).(!empty($_GET['title'])?'&title='.urlencode($_GET['title']):'').(!empty($_GET['description'])?'&description='.urlencode($_GET['description']):'').(!empty($_GET['source'])?'&source='.urlencode($_GET['source']):'')); // Redirect to login page, then back to post link.
            exit;
        }

        showLinkList($PAGE, $LINKSDB, $conf, $pluginManager);
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
        $data = array(
            'pageabsaddr' => index_url($_SERVER),
            'sslenabled' => !empty($_SERVER['HTTPS'])
        );
        $pluginManager->executeHooks('render_tools', $data);

        foreach ($data as $key => $value) {
            $PAGE->assign($key, $value);
        }

        $PAGE->renderPage('tools');
        exit;
    }

    // -------- User wants to change his/her password.
    if ($targetPage == Router::$PAGE_CHANGEPASSWORD)
    {
        if ($conf->get('security.open_shaarli')) {
            die('You are not supposed to change a password on an Open Shaarli.');
        }

        if (!empty($_POST['setpassword']) && !empty($_POST['oldpassword']))
        {
            if (!tokenOk($_POST['token'])) die('Wrong token.'); // Go away!

            // Make sure old password is correct.
            $oldhash = sha1($_POST['oldpassword'].$conf->get('credentials.login').$conf->get('credentials.salt'));
            if ($oldhash!= $conf->get('credentials.hash')) { echo '<script>alert("The old password is not correct.");document.location=\'?do=changepasswd\';</script>'; exit; }
            // Save new password
            // Salt renders rainbow-tables attacks useless.
            $conf->set('credentials.salt', sha1(uniqid('', true) .'_'. mt_rand()));
            $conf->set('credentials.hash', sha1($_POST['setpassword'] . $conf->get('credentials.login') . $conf->get('credentials.salt')));
            try {
                $conf->write(isLoggedIn());
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
            echo '<script>alert("Your password has been changed.");document.location=\'?do=tools\';</script>';
            exit;
        }
        else // show the change password form.
        {
            $PAGE->renderPage('changepassword');
            exit;
        }
    }

    // -------- User wants to change configuration
    if ($targetPage == Router::$PAGE_CONFIGURE)
    {
        if (!empty($_POST['title']) )
        {
            if (!tokenOk($_POST['token'])) {
                die('Wrong token.'); // Go away!
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
            $conf->set('redirector.url', escape($_POST['redirector']));
            $conf->set('security.session_protection_disabled', !empty($_POST['disablesessionprotection']));
            $conf->set('privacy.default_private_links', !empty($_POST['privateLinkByDefault']));
            $conf->set('feed.rss_permalinks', !empty($_POST['enableRssPermalinks']));
            $conf->set('updates.check_updates', !empty($_POST['updateCheck']));
            $conf->set('privacy.hide_public_links', !empty($_POST['hidePublicLinks']));
            $conf->set('api.enabled', !empty($_POST['apiEnabled']));
            $conf->set('api.secret', escape($_POST['apiSecret']));
            try {
                $conf->write(isLoggedIn());
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
            echo '<script>alert("Configuration was saved.");document.location=\'?do=configure\';</script>';
            exit;
        }
        else // Show the configuration form.
        {
            $PAGE->assign('title', $conf->get('general.title'));
            $PAGE->assign('theme', $conf->get('resource.theme'));
            $PAGE->assign('theme_available', ThemeUtils::getThemes($conf->get('resource.raintpl_tpl')));
            $PAGE->assign('redirector', $conf->get('redirector.url'));
            list($timezone_form, $timezone_js) = generateTimeZoneForm($conf->get('general.timezone'));
            $PAGE->assign('timezone_form', $timezone_form);
            $PAGE->assign('timezone_js',$timezone_js);
            $PAGE->assign('private_links_default', $conf->get('privacy.default_private_links', false));
            $PAGE->assign('session_protection_disabled', $conf->get('security.session_protection_disabled', false));
            $PAGE->assign('enable_rss_permalinks', $conf->get('feed.rss_permalinks', false));
            $PAGE->assign('enable_update_check', $conf->get('updates.check_updates', true));
            $PAGE->assign('hide_public_links', $conf->get('privacy.hide_public_links', false));
            $PAGE->assign('api_enabled', $conf->get('api.enabled', true));
            $PAGE->assign('api_secret', $conf->get('api.secret'));
            $PAGE->renderPage('configure');
            exit;
        }
    }

    // -------- User wants to rename a tag or delete it
    if ($targetPage == Router::$PAGE_CHANGETAG)
    {
        if (empty($_POST['fromtag']) || (empty($_POST['totag']) && isset($_POST['renametag']))) {
            $PAGE->assign('tags', $LINKSDB->allTags());
            $PAGE->renderPage('changetag');
            exit;
        }

        if (!tokenOk($_POST['token'])) {
            die('Wrong token.');
        }

        // Delete a tag:
        if (isset($_POST['deletetag']) && !empty($_POST['fromtag'])) {
            $needle = trim($_POST['fromtag']);
            // True for case-sensitive tag search.
            $linksToAlter = $LINKSDB->filterSearch(array('searchtags' => $needle), true);
            foreach($linksToAlter as $key=>$value)
            {
                $tags = explode(' ',trim($value['tags']));
                unset($tags[array_search($needle,$tags)]); // Remove tag.
                $value['tags']=trim(implode(' ',$tags));
                $LINKSDB[$key]=$value;
            }
            $LINKSDB->save($conf->get('resource.page_cache'));
            echo '<script>alert("Tag was removed from '.count($linksToAlter).' links.");document.location=\'?\';</script>';
            exit;
        }

        // Rename a tag:
        if (isset($_POST['renametag']) && !empty($_POST['fromtag']) && !empty($_POST['totag'])) {
            $needle = trim($_POST['fromtag']);
            // True for case-sensitive tag search.
            $linksToAlter = $LINKSDB->filterSearch(array('searchtags' => $needle), true);
            foreach($linksToAlter as $key=>$value)
            {
                $tags = explode(' ',trim($value['tags']));
                $tags[array_search($needle,$tags)] = trim($_POST['totag']); // Replace tags value.
                $value['tags']=trim(implode(' ',$tags));
                $LINKSDB[$key]=$value;
            }
            $LINKSDB->save($conf->get('resource.page_cache')); // Save to disk.
            echo '<script>alert("Tag was renamed in '.count($linksToAlter).' links.");document.location=\'?searchtags='.urlencode($_POST['totag']).'\';</script>';
            exit;
        }
    }

    // -------- User wants to add a link without using the bookmarklet: Show form.
    if ($targetPage == Router::$PAGE_ADDLINK)
    {
        $PAGE->renderPage('addlink');
        exit;
    }

    // -------- User clicked the "Save" button when editing a link: Save link to database.
    if (isset($_POST['save_edit']))
    {
        // Go away!
        if (! tokenOk($_POST['token'])) {
            die('Wrong token.');
        }

        // lf_id should only be present if the link exists.
        $id = !empty($_POST['lf_id']) ? intval(escape($_POST['lf_id'])) : $LINKSDB->getNextId();
        // Linkdate is kept here to:
        //   - use the same permalink for notes as they're displayed when creating them
        //   - let users hack creation date of their posts
        //     See: https://github.com/shaarli/Shaarli/wiki/Datastore-hacks#changing-the-timestamp-for-a-link
        $linkdate = escape($_POST['lf_linkdate']);
        if (isset($LINKSDB[$id])) {
            // Edit
            $created = DateTime::createFromFormat(LinkDB::LINK_DATE_FORMAT, $linkdate);
            $updated = new DateTime();
            $shortUrl = $LINKSDB[$id]['shorturl'];
        } else {
            // New link
            $created = DateTime::createFromFormat(LinkDB::LINK_DATE_FORMAT, $linkdate);
            $updated = null;
            $shortUrl = link_small_hash($created, $id);
        }

        // Remove multiple spaces.
        $tags = trim(preg_replace('/\s\s+/', ' ', $_POST['lf_tags']));
        // Remove first '-' char in tags.
        $tags = preg_replace('/(^| )\-/', '$1', $tags);
        // Remove duplicates.
        $tags = implode(' ', array_unique(explode(' ', $tags)));

        $url = trim($_POST['lf_url']);
        if (! startsWith($url, 'http:') && ! startsWith($url, 'https:')
            && ! startsWith($url, 'ftp:') && ! startsWith($url, 'magnet:')
            && ! startsWith($url, '?') && ! startsWith($url, 'javascript:')
        ) {
            $url = 'http://' . $url;
        }

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

        $pluginManager->executeHooks('save_link', $link);

        $LINKSDB[$id] = $link;
        $LINKSDB->save($conf->get('resource.page_cache'));

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
        // If we are called from the bookmarklet, we must close the popup:
        if (isset($_GET['source']) && ($_GET['source']=='bookmarklet' || $_GET['source']=='firefoxsocialapi')) { echo '<script>self.close();</script>'; exit; }
        $link = $LINKSDB[(int) escape($_POST['lf_id'])];
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
        // We do not need to ask for confirmation:
        // - confirmation is handled by JavaScript
        // - we are protected from XSRF by the token.

        if (! tokenOk($_GET['token'])) {
            die('Wrong token.');
        }

        $id = intval(escape($_GET['lf_linkdate']));
        $link = $LINKSDB[$id];
        $pluginManager->executeHooks('delete_link', $link);
        unset($LINKSDB[$id]);
        $LINKSDB->save($conf->get('resource.page_cache')); // save to disk

        // If we are called from the bookmarklet, we must close the popup:
        if (isset($_GET['source']) && ($_GET['source']=='bookmarklet' || $_GET['source']=='firefoxsocialapi')) { echo '<script>self.close();</script>'; exit; }
        // Pick where we're going to redirect
        // =============================================================
        // Basically, we can't redirect to where we were previously if it was a permalink
        // or an edit_link, because it would 404.
        // Cases:
        //    - /             : nothing in $_GET, redirect to self
        //    - /?page        : redirect to self
        //    - /?searchterm  : redirect to self (there might be other links)
        //    - /?searchtags  : redirect to self
        //    - /permalink    : redirect to / (the link does not exist anymore)
        //    - /?edit_link   : redirect to / (the link does not exist anymore)
        // PHP treats the permalink as a $_GET variable, so we need to check if every condition for self
        // redirect is not satisfied, and only then redirect to /
        $location = "?";
        // Self redirection
        if (count($_GET) == 0
            || isset($_GET['page'])
            || isset($_GET['searchterm'])
            || isset($_GET['searchtags'])
        ) {
            if (isset($_POST['returnurl'])) {
                $location = $_POST['returnurl']; // Handle redirects given by the form
            } else {
                $location = generateLocation($_SERVER['HTTP_REFERER'], $_SERVER['HTTP_HOST'], array('delete_link'));
            }
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
            'tags' => $LINKSDB->allTags(),
        );
        $pluginManager->executeHooks('render_editlink', $data);

        foreach ($data as $key => $value) {
            $PAGE->assign($key, $value);
        }

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
                list($headers, $content) = get_http_response($url, 4);
                if (strpos($headers[0], '200 OK') !== false) {
                    // Retrieve charset.
                    $charset = get_charset($headers, $content);
                    // Extract title.
                    $title = html_extract_title($content);
                    // Re-encode title in utf-8 if necessary.
                    if (! empty($title) && strtolower($charset) != 'utf-8') {
                        $title = mb_convert_encoding($title, 'utf-8', $charset);
                    }
                }
            }

            if ($url == '') {
                $url = '?' . smallHash($linkdate . $LINKSDB->getNextId());
                $title = 'Note: ';
            }
            $url = escape($url);
            $title = escape($title);

            $link = array(
                'linkdate' => $linkdate,
                'title' => $title,
                'url' => $url,
                'description' => $description,
                'tags' => $tags,
                'private' => $private
            );
        } else {
            $link['linkdate'] = $link['created']->format(LinkDB::LINK_DATE_FORMAT);
        }

        $data = array(
            'link' => $link,
            'link_is_new' => $link_is_new,
            'http_referer' => (isset($_SERVER['HTTP_REFERER']) ? escape($_SERVER['HTTP_REFERER']) : ''),
            'source' => (isset($_GET['source']) ? $_GET['source'] : ''),
            'tags' => $LINKSDB->allTags(),
            'default_private_links' => $conf->get('privacy.default_private_links', false),
        );
        $pluginManager->executeHooks('render_editlink', $data);

        foreach ($data as $key => $value) {
            $PAGE->assign($key, $value);
        }

        $PAGE->renderPage('editlink');
        exit;
    }

    if ($targetPage == Router::$PAGE_EXPORT) {
        // Export links as a Netscape Bookmarks file

        if (empty($_GET['selection'])) {
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
            $PAGE->assign('maxfilesize', getMaxFileSize());
            $PAGE->renderPage('import');
            exit;
        }

        // Import bookmarks from an uploaded file
        if (isset($_FILES['filetoupload']['size']) && $_FILES['filetoupload']['size'] == 0) {
            // The file is too big or some form field may be missing.
            echo '<script>alert("The file you are trying to upload is probably'
                .' bigger than what this webserver can accept ('
                .getMaxFileSize().' bytes).'
                .' Please upload in smaller chunks.");document.location=\'?do='
                .Router::$PAGE_IMPORT .'\';</script>';
            exit;
        }
        if (! tokenOk($_POST['token'])) {
            die('Wrong token.');
        }
        $status = NetscapeBookmarkUtils::import(
            $_POST,
            $_FILES,
            $LINKSDB,
            $conf->get('resource.page_cache')
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
            $conf->write(isLoggedIn());
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

    // -------- Otherwise, simply display search form and links:
    showLinkList($PAGE, $LINKSDB, $conf, $pluginManager);
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
 */
function buildLinkList($PAGE,$LINKSDB, $conf, $pluginManager)
{
    // Used in templates
    $searchtags = !empty($_GET['searchtags']) ? escape(normalize_spaces($_GET['searchtags'])) : '';
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
        $privateonly = !empty($_SESSION['privateonly']);
        $linksToDisplay = $LINKSDB->filterSearch($_GET, false, $privateonly);
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
    $linkDisp = array();
    while ($i<$end && $i<count($keys))
    {
        $link = $linksToDisplay[$keys[$i]];
        $link['description'] = format_description($link['description'], $conf->get('redirector.url'));
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
        // Check for both signs of a note: starting with ? and 7 chars long.
        if ($link['url'][0] === '?' &&
            strlen($link['url']) === 7) {
            $link['url'] = index_url($_SERVER) . $link['url'];
        }

        $linkDisp[$keys[$i]] = $link;
        $i++;
    }

    // Compute paging navigation
    $searchtagsUrl = empty($searchtags) ? '' : '&searchtags=' . urlencode($searchtags);
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
        'redirector' => $conf->get('redirector.url'),  // Optional redirector URL.
        'links' => $linkDisp,
        'tags' => $LINKSDB->allTags(),
    );

    // If there is only a single link, we change on-the-fly the title of the page.
    if (count($linksToDisplay) == 1) {
        $data['pagetitle'] = $linksToDisplay[$keys[0]]['title'] .' - '. $conf->get('general.title');
    }

    $pluginManager->executeHooks('render_linklist', $data, array('loggedin' => isLoggedIn()));

    foreach ($data as $key => $value) {
        $PAGE->assign($key, $value);
    }

    return;
}

/**
 * Compute the thumbnail for a link.
 *
 * With a link to the original URL.
 * Understands various services (youtube.com...)
 * Input: $url = URL for which the thumbnail must be found.
 *        $href = if provided, this URL will be followed instead of $url
 * Returns an associative array with thumbnail attributes (src,href,width,height,style,alt)
 * Some of them may be missing.
 * Return an empty array if no thumbnail available.
 *
 * @param ConfigManager $conf Configuration Manager instance.
 * @param string        $url
 * @param string|bool   $href
 *
 * @return array
 */
function computeThumbnail($conf, $url, $href = false)
{
    if (!$conf->get('thumbnail.enable_thumbnails')) return array();
    if ($href==false) $href=$url;

    // For most hosts, the URL of the thumbnail can be easily deduced from the URL of the link.
    // (e.g. http://www.youtube.com/watch?v=spVypYk4kto --->  http://img.youtube.com/vi/spVypYk4kto/default.jpg )
    //                                     ^^^^^^^^^^^                                 ^^^^^^^^^^^
    $domain = parse_url($url,PHP_URL_HOST);
    if ($domain=='youtube.com' || $domain=='www.youtube.com')
    {
        parse_str(parse_url($url,PHP_URL_QUERY), $params); // Extract video ID and get thumbnail
        if (!empty($params['v'])) return array('src'=>'https://img.youtube.com/vi/'.$params['v'].'/default.jpg',
                                               'href'=>$href,'width'=>'120','height'=>'90','alt'=>'YouTube thumbnail');
    }
    if ($domain=='youtu.be') // Youtube short links
    {
        $path = parse_url($url,PHP_URL_PATH);
        return array('src'=>'https://img.youtube.com/vi'.$path.'/default.jpg',
                     'href'=>$href,'width'=>'120','height'=>'90','alt'=>'YouTube thumbnail');
    }
    if ($domain=='pix.toile-libre.org') // pix.toile-libre.org image hosting
    {
        parse_str(parse_url($url,PHP_URL_QUERY), $params); // Extract image filename.
        if (!empty($params) && !empty($params['img'])) return array('src'=>'http://pix.toile-libre.org/upload/thumb/'.urlencode($params['img']),
                                                                    'href'=>$href,'style'=>'max-width:120px; max-height:150px','alt'=>'pix.toile-libre.org thumbnail');
    }

    if ($domain=='imgur.com')
    {
        $path = parse_url($url,PHP_URL_PATH);
        if (startsWith($path,'/a/')) return array(); // Thumbnails for albums are not available.
        if (startsWith($path,'/r/')) return array('src'=>'https://i.imgur.com/'.basename($path).'s.jpg',
                                                  'href'=>$href,'width'=>'90','height'=>'90','alt'=>'imgur.com thumbnail');
        if (startsWith($path,'/gallery/')) return array('src'=>'https://i.imgur.com'.substr($path,8).'s.jpg',
                                                        'href'=>$href,'width'=>'90','height'=>'90','alt'=>'imgur.com thumbnail');

        if (substr_count($path,'/')==1) return array('src'=>'https://i.imgur.com/'.substr($path,1).'s.jpg',
                                                     'href'=>$href,'width'=>'90','height'=>'90','alt'=>'imgur.com thumbnail');
    }
    if ($domain=='i.imgur.com')
    {
        $pi = pathinfo(parse_url($url,PHP_URL_PATH));
        if (!empty($pi['filename'])) return array('src'=>'https://i.imgur.com/'.$pi['filename'].'s.jpg',
                                                  'href'=>$href,'width'=>'90','height'=>'90','alt'=>'imgur.com thumbnail');
    }
    if ($domain=='dailymotion.com' || $domain=='www.dailymotion.com')
    {
        if (strpos($url,'dailymotion.com/video/')!==false)
        {
            $thumburl=str_replace('dailymotion.com/video/','dailymotion.com/thumbnail/video/',$url);
            return array('src'=>$thumburl,
                         'href'=>$href,'width'=>'120','style'=>'height:auto;','alt'=>'DailyMotion thumbnail');
        }
    }
    if (endsWith($domain,'.imageshack.us'))
    {
        $ext=strtolower(pathinfo($url,PATHINFO_EXTENSION));
        if ($ext=='jpg' || $ext=='jpeg' || $ext=='png' || $ext=='gif')
        {
            $thumburl = substr($url,0,strlen($url)-strlen($ext)).'th.'.$ext;
            return array('src'=>$thumburl,
                         'href'=>$href,'width'=>'120','style'=>'height:auto;','alt'=>'imageshack.us thumbnail');
        }
    }

    // Some other hosts are SLOW AS HELL and usually require an extra HTTP request to get the thumbnail URL.
    // So we deport the thumbnail generation in order not to slow down page generation
    // (and we also cache the thumbnail)

    if (! $conf->get('thumbnail.enable_localcache')) return array(); // If local cache is disabled, no thumbnails for services which require the use a local cache.

    if ($domain=='flickr.com' || endsWith($domain,'.flickr.com')
        || $domain=='vimeo.com'
        || $domain=='ted.com' || endsWith($domain,'.ted.com')
        || $domain=='xkcd.com' || endsWith($domain,'.xkcd.com')
    )
    {
        if ($domain=='vimeo.com')
        {   // Make sure this vimeo URL points to a video (/xxx... where xxx is numeric)
            $path = parse_url($url,PHP_URL_PATH);
            if (!preg_match('!/\d+.+?!',$path)) return array(); // This is not a single video URL.
        }
        if ($domain=='xkcd.com' || endsWith($domain,'.xkcd.com'))
        {   // Make sure this URL points to a single comic (/xxx... where xxx is numeric)
            $path = parse_url($url,PHP_URL_PATH);
            if (!preg_match('!/\d+.+?!',$path)) return array();
        }
        if ($domain=='ted.com' || endsWith($domain,'.ted.com'))
        {   // Make sure this TED URL points to a video (/talks/...)
            $path = parse_url($url,PHP_URL_PATH);
            if ("/talks/" !== substr($path,0,7)) return array(); // This is not a single video URL.
        }
        $sign = hash_hmac('sha256', $url, $conf->get('credentials.salt')); // We use the salt to sign data (it's random, secret, and specific to each installation)
        return array('src'=>index_url($_SERVER).'?do=genthumbnail&hmac='.$sign.'&url='.urlencode($url),
                     'href'=>$href,'width'=>'120','style'=>'height:auto;','alt'=>'thumbnail');
    }

    // For all other, we try to make a thumbnail of links ending with .jpg/jpeg/png/gif
    // Technically speaking, we should download ALL links and check their Content-Type to see if they are images.
    // But using the extension will do.
    $ext=strtolower(pathinfo($url,PATHINFO_EXTENSION));
    if ($ext=='jpg' || $ext=='jpeg' || $ext=='png' || $ext=='gif')
    {
        $sign = hash_hmac('sha256', $url, $conf->get('credentials.salt')); // We use the salt to sign data (it's random, secret, and specific to each installation)
        return array('src'=>index_url($_SERVER).'?do=genthumbnail&hmac='.$sign.'&url='.urlencode($url),
                     'href'=>$href,'width'=>'120','style'=>'height:auto;','alt'=>'thumbnail');
    }
    return array(); // No thumbnail.

}


// Returns the HTML code to display a thumbnail for a link
// with a link to the original URL.
// Understands various services (youtube.com...)
// Input: $url = URL for which the thumbnail must be found.
//        $href = if provided, this URL will be followed instead of $url
// Returns '' if no thumbnail available.
function thumbnail($url,$href=false)
{
    // FIXME!
    global $conf;
    $t = computeThumbnail($conf, $url,$href);
    if (count($t)==0) return ''; // Empty array = no thumbnail for this URL.

    $html='<a href="'.escape($t['href']).'"><img src="'.escape($t['src']).'"';
    if (!empty($t['width']))  $html.=' width="'.escape($t['width']).'"';
    if (!empty($t['height'])) $html.=' height="'.escape($t['height']).'"';
    if (!empty($t['style']))  $html.=' style="'.escape($t['style']).'"';
    if (!empty($t['alt']))    $html.=' alt="'.escape($t['alt']).'"';
    $html.='></a>';
    return $html;
}

// Returns the HTML code to display a thumbnail for a link
// for the picture wall (using lazy image loading)
// Understands various services (youtube.com...)
// Input: $url = URL for which the thumbnail must be found.
//        $href = if provided, this URL will be followed instead of $url
// Returns '' if no thumbnail available.
function lazyThumbnail($conf, $url,$href=false)
{
    // FIXME!
    global $conf;
    $t = computeThumbnail($conf, $url,$href);
    if (count($t)==0) return ''; // Empty array = no thumbnail for this URL.

    $html='<a href="'.escape($t['href']).'">';

    // Lazy image
    $html.='<img class="b-lazy" src="#" data-src="'.escape($t['src']).'"';

    if (!empty($t['width']))  $html.=' width="'.escape($t['width']).'"';
    if (!empty($t['height'])) $html.=' height="'.escape($t['height']).'"';
    if (!empty($t['style']))  $html.=' style="'.escape($t['style']).'"';
    if (!empty($t['alt']))    $html.=' alt="'.escape($t['alt']).'"';
    $html.='>';

    // No-JavaScript fallback.
    $html.='<noscript><img src="'.escape($t['src']).'"';
    if (!empty($t['width']))  $html.=' width="'.escape($t['width']).'"';
    if (!empty($t['height'])) $html.=' height="'.escape($t['height']).'"';
    if (!empty($t['style']))  $html.=' style="'.escape($t['style']).'"';
    if (!empty($t['alt']))    $html.=' alt="'.escape($t['alt']).'"';
    $html.='></noscript></a>';

    return $html;
}


/**
 * Installation
 * This function should NEVER be called if the file data/config.php exists.
 *
 * @param ConfigManager $conf Configuration Manager instance.
 */
function install($conf)
{
    // On free.fr host, make sure the /sessions directory exists, otherwise login will not work.
    if (endsWith($_SERVER['HTTP_HOST'],'.free.fr') && !is_dir($_SERVER['DOCUMENT_ROOT'].'/sessions')) mkdir($_SERVER['DOCUMENT_ROOT'].'/sessions',0705);


    // This part makes sure sessions works correctly.
    // (Because on some hosts, session.save_path may not be set correctly,
    // or we may not have write access to it.)
    if (isset($_GET['test_session']) && ( !isset($_SESSION) || !isset($_SESSION['session_tested']) || $_SESSION['session_tested']!='Working'))
    {   // Step 2: Check if data in session is correct.
        echo '<pre>Sessions do not seem to work correctly on your server.<br>';
        echo 'Make sure the variable session.save_path is set correctly in your php config, and that you have write access to it.<br>';
        echo 'It currently points to '.session_save_path().'<br>';
        echo 'Check that the hostname used to access Shaarli contains a dot. On some browsers, accessing your server via a hostname like \'localhost\' or any custom hostname without a dot causes cookie storage to fail. We recommend accessing your server via it\'s IP address or Fully Qualified Domain Name.<br>';
        echo '<br><a href="?">Click to try again.</a></pre>';
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
            $conf->write(isLoggedIn());
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

    // Display config form:
    list($timezone_form, $timezone_js) = generateTimeZoneForm();
    $timezone_html = '';
    if ($timezone_form != '') {
        $timezone_html = '<tr><td><b>Timezone:</b></td><td>'.$timezone_form.'</td></tr>';
    }

    $PAGE = new PageBuilder($conf);
    $PAGE->assign('timezone_html',$timezone_html);
    $PAGE->assign('timezone_js',$timezone_js);
    $PAGE->renderPage('install');
    exit;
}

/**
 * Because some f*cking services like flickr require an extra HTTP request to get the thumbnail URL,
 * I have deported the thumbnail URL code generation here, otherwise this would slow down page generation.
 * The following function takes the URL a link (e.g. a flickr page) and return the proper thumbnail.
 * This function is called by passing the URL:
 * http://mywebsite.com/shaarli/?do=genthumbnail&hmac=[HMAC]&url=[URL]
 * [URL] is the URL of the link (e.g. a flickr page)
 * [HMAC] is the signature for the [URL] (so that these URL cannot be forged).
 * The function below will fetch the image from the webservice and store it in the cache.
 *
 * @param ConfigManager $conf Configuration Manager instance,
 */
function genThumbnail($conf)
{
    // Make sure the parameters in the URL were generated by us.
    $sign = hash_hmac('sha256', $_GET['url'], $conf->get('credentials.salt'));
    if ($sign!=$_GET['hmac']) die('Naughty boy!');

    $cacheDir = $conf->get('resource.thumbnails_cache', 'cache');
    // Let's see if we don't already have the image for this URL in the cache.
    $thumbname=hash('sha1',$_GET['url']).'.jpg';
    if (is_file($cacheDir .'/'. $thumbname))
    {   // We have the thumbnail, just serve it:
        header('Content-Type: image/jpeg');
        echo file_get_contents($cacheDir .'/'. $thumbname);
        return;
    }
    // We may also serve a blank image (if service did not respond)
    $blankname=hash('sha1',$_GET['url']).'.gif';
    if (is_file($cacheDir .'/'. $blankname))
    {
        header('Content-Type: image/gif');
        echo file_get_contents($cacheDir .'/'. $blankname);
        return;
    }

    // Otherwise, generate the thumbnail.
    $url = $_GET['url'];
    $domain = parse_url($url,PHP_URL_HOST);

    if ($domain=='flickr.com' || endsWith($domain,'.flickr.com'))
    {
        // Crude replacement to handle new flickr domain policy (They prefer www. now)
        $url = str_replace('http://flickr.com/','http://www.flickr.com/',$url);

        // Is this a link to an image, or to a flickr page ?
        $imageurl='';
        if (endsWith(parse_url($url, PHP_URL_PATH), '.jpg'))
        {  // This is a direct link to an image. e.g. http://farm1.staticflickr.com/5/5921913_ac83ed27bd_o.jpg
            preg_match('!(http://farm\d+\.staticflickr\.com/\d+/\d+_\w+_)\w.jpg!',$url,$matches);
            if (!empty($matches[1])) $imageurl=$matches[1].'m.jpg';
        }
        else // This is a flickr page (html)
        {
            // Get the flickr html page.
            list($headers, $content) = get_http_response($url, 20);
            if (strpos($headers[0], '200 OK') !== false)
            {
                // flickr now nicely provides the URL of the thumbnail in each flickr page.
                preg_match('!<link rel=\"image_src\" href=\"(.+?)\"!', $content, $matches);
                if (!empty($matches[1])) $imageurl=$matches[1];

                // In albums (and some other pages), the link rel="image_src" is not provided,
                // but flickr provides:
                // <meta property="og:image" content="http://farm4.staticflickr.com/3398/3239339068_25d13535ff_z.jpg" />
                if ($imageurl=='')
                {
                    preg_match('!<meta property=\"og:image\" content=\"(.+?)\"!', $content, $matches);
                    if (!empty($matches[1])) $imageurl=$matches[1];
                }
            }
        }

        if ($imageurl!='')
        {   // Let's download the image.
            // Image is 240x120, so 10 seconds to download should be enough.
            list($headers, $content) = get_http_response($imageurl, 10);
            if (strpos($headers[0], '200 OK') !== false) {
                // Save image to cache.
                file_put_contents($cacheDir .'/'. $thumbname, $content);
                header('Content-Type: image/jpeg');
                echo $content;
                return;
            }
        }
    }

    elseif ($domain=='vimeo.com' )
    {
        // This is more complex: we have to perform a HTTP request, then parse the result.
        // Maybe we should deport this to JavaScript ? Example: http://stackoverflow.com/questions/1361149/get-img-thumbnails-from-vimeo/4285098#4285098
        $vid = substr(parse_url($url,PHP_URL_PATH),1);
        list($headers, $content) = get_http_response('https://vimeo.com/api/v2/video/'.escape($vid).'.php', 5);
        if (strpos($headers[0], '200 OK') !== false) {
            $t = unserialize($content);
            $imageurl = $t[0]['thumbnail_medium'];
            // Then we download the image and serve it to our client.
            list($headers, $content) = get_http_response($imageurl, 10);
            if (strpos($headers[0], '200 OK') !== false) {
                // Save image to cache.
                file_put_contents($cacheDir .'/'. $thumbname, $content);
                header('Content-Type: image/jpeg');
                echo $content;
                return;
            }
        }
    }

    elseif ($domain=='ted.com' || endsWith($domain,'.ted.com'))
    {
        // The thumbnail for TED talks is located in the <link rel="image_src" [...]> tag on that page
        // http://www.ted.com/talks/mikko_hypponen_fighting_viruses_defending_the_net.html
        // <link rel="image_src" href="http://images.ted.com/images/ted/28bced335898ba54d4441809c5b1112ffaf36781_389x292.jpg" />
        list($headers, $content) = get_http_response($url, 5);
        if (strpos($headers[0], '200 OK') !== false) {
            // Extract the link to the thumbnail
            preg_match('!link rel="image_src" href="(http://images.ted.com/images/ted/.+_\d+x\d+\.jpg)"!', $content, $matches);
            if (!empty($matches[1]))
            {   // Let's download the image.
                $imageurl=$matches[1];
                // No control on image size, so wait long enough
                list($headers, $content) = get_http_response($imageurl, 20);
                if (strpos($headers[0], '200 OK') !== false) {
                    $filepath = $cacheDir .'/'. $thumbname;
                    file_put_contents($filepath, $content); // Save image to cache.
                    if (resizeImage($filepath))
                    {
                        header('Content-Type: image/jpeg');
                        echo file_get_contents($filepath);
                        return;
                    }
                }
            }
        }
    }

    elseif ($domain=='xkcd.com' || endsWith($domain,'.xkcd.com'))
    {
        // There is no thumbnail available for xkcd comics, so download the whole image and resize it.
        // http://xkcd.com/327/
        // <img src="http://imgs.xkcd.com/comics/exploits_of_a_mom.png" title="<BLABLA>" alt="<BLABLA>" />
        list($headers, $content) = get_http_response($url, 5);
        if (strpos($headers[0], '200 OK') !== false) {
            // Extract the link to the thumbnail
            preg_match('!<img src="(http://imgs.xkcd.com/comics/.*)" title="[^s]!', $content, $matches);
            if (!empty($matches[1]))
            {   // Let's download the image.
                $imageurl=$matches[1];
                // No control on image size, so wait long enough
                list($headers, $content) = get_http_response($imageurl, 20);
                if (strpos($headers[0], '200 OK') !== false) {
                    $filepath = $cacheDir.'/'.$thumbname;
                    // Save image to cache.
                    file_put_contents($filepath, $content);
                    if (resizeImage($filepath))
                    {
                        header('Content-Type: image/jpeg');
                        echo file_get_contents($filepath);
                        return;
                    }
                }
            }
        }
    }

    else
    {
        // For all other domains, we try to download the image and make a thumbnail.
        // We allow 30 seconds max to download (and downloads are limited to 4 Mb)
        list($headers, $content) = get_http_response($url, 30);
        if (strpos($headers[0], '200 OK') !== false) {
            $filepath = $cacheDir .'/'.$thumbname;
            // Save image to cache.
            file_put_contents($filepath, $content);
            if (resizeImage($filepath))
            {
                header('Content-Type: image/jpeg');
                echo file_get_contents($filepath);
                return;
            }
        }
    }


    // Otherwise, return an empty image (8x8 transparent gif)
    $blankgif = base64_decode('R0lGODlhCAAIAIAAAP///////yH5BAEKAAEALAAAAAAIAAgAAAIHjI+py+1dAAA7');
    // Also put something in cache so that this URL is not requested twice.
    file_put_contents($cacheDir .'/'. $blankname, $blankgif);
    header('Content-Type: image/gif');
    echo $blankgif;
}

// Make a thumbnail of the image (to width: 120 pixels)
// Returns true if success, false otherwise.
function resizeImage($filepath)
{
    if (!function_exists('imagecreatefromjpeg')) return false; // GD not present: no thumbnail possible.

    // Trick: some stupid people rename GIF as JPEG... or else.
    // So we really try to open each image type whatever the extension is.
    $header=file_get_contents($filepath,false,NULL,0,256); // Read first 256 bytes and try to sniff file type.
    $im=false;
    $i=strpos($header,'GIF8'); if (($i!==false) && ($i==0)) $im = imagecreatefromgif($filepath); // Well this is crude, but it should be enough.
    $i=strpos($header,'PNG'); if (($i!==false) && ($i==1)) $im = imagecreatefrompng($filepath);
    $i=strpos($header,'JFIF'); if ($i!==false) $im = imagecreatefromjpeg($filepath);
    if (!$im) return false;  // Unable to open image (corrupted or not an image)
    $w = imagesx($im);
    $h = imagesy($im);
    $ystart = 0; $yheight=$h;
    if ($h>$w) { $ystart= ($h/2)-($w/2); $yheight=$w/2; }
    $nw = 120;   // Desired width
    $nh = min(floor(($h*$nw)/$w),120); // Compute new width/height, but maximum 120 pixels height.
    // Resize image:
    $im2 = imagecreatetruecolor($nw,$nh);
    imagecopyresampled($im2, $im, 0, 0, 0, $ystart, $nw, $nh, $w, $yheight);
    imageinterlace($im2,true); // For progressive JPEG.
    $tempname=$filepath.'_TEMP.jpg';
    imagejpeg($im2, $tempname, 90);
    imagedestroy($im);
    imagedestroy($im2);
    unlink($filepath);
    rename($tempname,$filepath);  // Overwrite original picture with thumbnail.
    return true;
}

if (isset($_SERVER['QUERY_STRING']) && startsWith($_SERVER['QUERY_STRING'], 'do=genthumbnail')) { genThumbnail($conf); exit; }  // Thumbnail generation/cache does not need the link database.
if (isset($_SERVER['QUERY_STRING']) && startsWith($_SERVER['QUERY_STRING'], 'do=dailyrss')) { showDailyRSS($conf); exit; }
if (!isset($_SESSION['LINKS_PER_PAGE'])) {
    $_SESSION['LINKS_PER_PAGE'] = $conf->get('general.links_per_page', 20);
}

$linkDb = new LinkDB(
    $conf->get('resource.datastore'),
    isLoggedIn(),
    $conf->get('privacy.hide_public_links'),
    $conf->get('redirector.url'),
    $conf->get('redirector.encode_url')
);

$container = new \Slim\Container();
$container['conf'] = $conf;
$container['plugins'] = $pluginManager;
$app = new \Slim\App($container);

// REST API routes
$app->group('/api/v1', function() {
    $this->get('/info', '\Shaarli\Api\Controllers\Info:getInfo');
})->add('\Shaarli\Api\ApiMiddleware');

$response = $app->run(true);
// Hack to make Slim and Shaarli router work together:
// If a Slim route isn't found, we call renderPage().
if ($response->getStatusCode() == 404) {
    // We use UTF-8 for proper international characters handling.
    header('Content-Type: text/html; charset=utf-8');
    renderPage($conf, $pluginManager, $linkDb);
} else {
    $app->respond($response);
}
