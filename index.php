<?php
// Shaarli 0.0.42 beta - Shaare your links...
// The personal, minimalist, super-fast, no-database delicious clone. By sebsauvage.net
// http://sebsauvage.net/wiki/doku.php?id=php:shaarli
// Licence: http://www.opensource.org/licenses/zlib-license.php
// Requires: php 5.1.x  (but autocomplete fields will only work if you have php 5.2.x)
// -----------------------------------------------------------------------------------------------
// NEVER TRUST IN PHP.INI
// Some hosts do not define a default timezone in php.ini,
// so we have to do this for avoid the strict standard error.
date_default_timezone_set('UTC');

// -----------------------------------------------------------------------------------------------
// Hardcoded parameter (These parameters can be overwritten by creating the file /config/options.php)
$GLOBALS['config']['DATADIR'] = 'data'; // Data subdirectory
$GLOBALS['config']['CONFIG_FILE'] = $GLOBALS['config']['DATADIR'].'/config.php'; // Configuration file (user login/password)
$GLOBALS['config']['DATASTORE'] = $GLOBALS['config']['DATADIR'].'/datastore.php'; // Data storage file.
$GLOBALS['config']['LINKS_PER_PAGE'] = 20; // Default links per page.
$GLOBALS['config']['IPBANS_FILENAME'] = $GLOBALS['config']['DATADIR'].'/ipbans.php'; // File storage for failures and bans.
$GLOBALS['config']['BAN_AFTER'] = 4;        // Ban IP after this many failures.
$GLOBALS['config']['BAN_DURATION'] = 1800;  // Ban duration for IP address after login failures (in seconds) (1800 sec. = 30 minutes)
$GLOBALS['config']['OPEN_SHAARLI'] = false; // If true, anyone can add/edit/delete links without having to login
$GLOBALS['config']['HIDE_TIMESTAMPS'] = false; // If true, the moment when links were saved are not shown to users that are not logged in.
$GLOBALS['config']['ENABLE_THUMBNAILS'] = true; // Enable thumbnails in links.
$GLOBALS['config']['CACHEDIR'] = 'cache'; // Cache directory for thumbnails for SLOW services (like flickr)
$GLOBALS['config']['PAGECACHE'] = 'pagecache'; // Page cache directory.
$GLOBALS['config']['ENABLE_LOCALCACHE'] = true; // Enable Shaarli to store thumbnail in a local cache. Disable to reduce webspace usage.
$GLOBALS['config']['PUBSUBHUB_URL'] = ''; // PubSubHubbub support. Put an empty string to disable, or put your hub url here to enable.
$GLOBALS['config']['UPDATECHECK_FILENAME'] = $GLOBALS['config']['DATADIR'].'/lastupdatecheck.txt'; // For updates check of Shaarli.
$GLOBALS['config']['UPDATECHECK_INTERVAL'] = 86400 ; // Updates check frequency for Shaarli. 86400 seconds=24 hours
                                          // Note: You must have publisher.php in the same directory as Shaarli index.php
// -----------------------------------------------------------------------------------------------
// You should not touch below (or at your own risks !)
// Optionnal config file.
if (is_file($GLOBALS['config']['DATADIR'].'/options.php')) require($GLOBALS['config']['DATADIR'].'/options.php');

define('shaarli_version','0.0.42 beta');
define('PHPPREFIX','<?php /* '); // Prefix to encapsulate data in php code.
define('PHPSUFFIX',' */ ?>'); // Suffix to encapsulate data in php code.
// http://server.com/x/shaarli --> /shaarli/
define('WEB_PATH', substr($_SERVER["REQUEST_URI"], 0, 1+strrpos($_SERVER["REQUEST_URI"], '/', 0)));

// Force cookie path (but do not change lifetime)
$cookie=session_get_cookie_params();
$cookiedir = ''; if(dirname($_SERVER['SCRIPT_NAME'])!='/') $cookiedir=dirname($_SERVER["SCRIPT_NAME"]).'/';
session_set_cookie_params($cookie['lifetime'],$cookiedir,$_SERVER['HTTP_HOST']); // Set default cookie expiration and path.

// Set session parameters on server side.
define('INACTIVITY_TIMEOUT',3600); // (in seconds). If the user does not access any page within this time, his/her session is considered expired.
ini_set('session.use_cookies', 1);       // Use cookies to store session.
ini_set('session.use_only_cookies', 1);  // Force cookies for session (phpsessionID forbidden in URL)
ini_set('session.use_trans_sid', false); // Prevent php to use sessionID in URL if cookies are disabled.
session_name('shaarli');
if (session_id() == '') session_start();  // Start session if needed (Some server auto-start sessions).

// PHP Settings
ini_set('max_input_time','60');  // High execution time in case of problematic imports/exports.
ini_set('memory_limit', '128M');  // Try to set max upload file size and read (May not work on some hosts).
ini_set('post_max_size', '16M');
ini_set('upload_max_filesize', '16M');
checkphpversion();
error_reporting(E_ALL^E_WARNING);  // See all error except warnings.
//error_reporting(-1); // See all errors (for debugging only)

include "inc/rain.tpl.class.php"; //include Rain TPL
raintpl::$tpl_dir = "tpl/"; // template directory
if (!is_dir('tmp')) { mkdir('tmp',0705); chmod('tmp',0705); }
raintpl::$cache_dir = "tmp/"; // cache directory

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

// Directories creations (Note that your web host may require differents rights than 705.)
if (!is_writable(realpath(dirname(__FILE__)))) die('<pre>ERROR: Shaarli does not have the right to write in its own directory ('.realpath(dirname(__FILE__)).').</pre>');
if (!is_dir($GLOBALS['config']['DATADIR'])) { mkdir($GLOBALS['config']['DATADIR'],0705); chmod($GLOBALS['config']['DATADIR'],0705); }
if (!is_dir('tmp')) { mkdir('tmp',0705); chmod('tmp',0705); } // For RainTPL temporary files.
if (!is_file($GLOBALS['config']['DATADIR'].'/.htaccess')) { file_put_contents($GLOBALS['config']['DATADIR'].'/.htaccess',"Allow from none\nDeny from all\n"); } // Protect data files.
// Second check to see if Shaarli can write in its directory, because on some hosts is_writable() is not reliable.
if (!is_file($GLOBALS['config']['DATADIR'].'/.htaccess')) die('<pre>ERROR: Shaarli does not have the right to write in its data directory ('.realpath($GLOBALS['config']['DATADIR']).').</pre>');
if ($GLOBALS['config']['ENABLE_LOCALCACHE'])
{
    if (!is_dir($GLOBALS['config']['CACHEDIR'])) { mkdir($GLOBALS['config']['CACHEDIR'],0705); chmod($GLOBALS['config']['CACHEDIR'],0705); }
    if (!is_file($GLOBALS['config']['CACHEDIR'].'/.htaccess')) { file_put_contents($GLOBALS['config']['CACHEDIR'].'/.htaccess',"Allow from none\nDeny from all\n"); } // Protect data files.
}

// Handling of old config file which do not have the new parameters.
if (empty($GLOBALS['title'])) $GLOBALS['title']='Shared links on '.htmlspecialchars(indexUrl());
if (empty($GLOBALS['timezone'])) $GLOBALS['timezone']=date_default_timezone_get();
if (empty($GLOBALS['redirector'])) $GLOBALS['redirector']='';
if (empty($GLOBALS['disablesessionprotection'])) $GLOBALS['disablesessionprotection']=false;
if (empty($GLOBALS['disablejquery'])) $GLOBALS['disablejquery']=false;
if (empty($GLOBALS['privateLinkByDefault'])) $GLOBALS['privateLinkByDefault']=false;
if (empty($GLOBALS['titleLink'])) $GLOBALS['titleLink']='?';
// I really need to rewrite Shaarli with a proper configuation manager.

// Run config screen if first run:
if (!is_file($GLOBALS['config']['CONFIG_FILE'])) install();

require $GLOBALS['config']['CONFIG_FILE'];  // Read login/password hash into $GLOBALS.

// a token depending of deployment salt, user password, and the current ip
define('STAY_SIGNED_IN_TOKEN', sha1($GLOBALS['hash'].$_SERVER["REMOTE_ADDR"].$GLOBALS['salt']));

autoLocale(); // Sniff browser language and set date format accordingly.
header('Content-Type: text/html; charset=utf-8'); // We use UTF-8 for proper international characters handling.

// Check php version
function checkphpversion()
{
    if (version_compare(PHP_VERSION, '5.1.0') < 0)
    {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Your server supports php '.PHP_VERSION.'. Shaarli requires at least php 5.1.0, and thus cannot run. Sorry.';
        exit;
    }
}

// Checks if an update is available for Shaarli.
// (at most once a day, and only for registered user.)
// Output: '' = no new version.
//         other= the available version.
function checkUpdate()
{
    if (!isLoggedIn()) return ''; // Do not check versions for visitors.

    // Get latest version number at most once a day.
    if (!is_file($GLOBALS['config']['UPDATECHECK_FILENAME']) || (filemtime($GLOBALS['config']['UPDATECHECK_FILENAME'])<time()-($GLOBALS['config']['UPDATECHECK_INTERVAL'])))
    {
        $version=shaarli_version;
        list($httpstatus,$headers,$data) = getHTTP('http://sebsauvage.net/files/shaarli_version.txt',2);
        if (strpos($httpstatus,'200 OK')!==false) $version=$data;
        // If failed, nevermind. We don't want to bother the user with that.
        file_put_contents($GLOBALS['config']['UPDATECHECK_FILENAME'],$version); // touch file date
    }
    // Compare versions:
    $newestversion=file_get_contents($GLOBALS['config']['UPDATECHECK_FILENAME']);
    if (version_compare($newestversion,shaarli_version)==1) return $newestversion;
    return '';
}


// -----------------------------------------------------------------------------------------------
// Simple cache system (mainly for the RSS/ATOM feeds).

class pageCache
{
    private $url; // Full URL of the page to cache (typically the value returned by pageUrl())
    private $shouldBeCached; // boolean: Should this url be cached ?
    private $filename; // Name of the cache file for this url

    /*
         $url = url (typically the value returned by pageUrl())
         $shouldBeCached = boolean. If false, the cache will be disabled.
    */
    public function __construct($url,$shouldBeCached)
    {
        $this->url = $url;
        $this->filename = $GLOBALS['config']['PAGECACHE'].'/'.sha1($url).'.cache';
        $this->shouldBeCached = $shouldBeCached;
    }

    // If the page should be cached and a cached version exists,
    // returns the cached version (otherwise, return null).
    public function cachedVersion()
    {
        if (!$this->shouldBeCached) return null;
        if (is_file($this->filename)) { return file_get_contents($this->filename); exit; }
        return null;
    }

    // Put a page in the cache.
    public function cache($page)
    {
        if (!$this->shouldBeCached) return;
        if (!is_dir($GLOBALS['config']['PAGECACHE'])) { mkdir($GLOBALS['config']['PAGECACHE'],0705); chmod($GLOBALS['config']['PAGECACHE'],0705); }
        file_put_contents($this->filename,$page);
    }

    // Purge the whole cache.
    // (call with pageCache::purgeCache())
    public static function purgeCache()
    {
        if (is_dir($GLOBALS['config']['PAGECACHE']))
        {
            $handler = opendir($GLOBALS['config']['PAGECACHE']);
            if ($handler!==false)
            {
                while (($filename = readdir($handler))!==false)
                {
                    if (endsWith($filename,'.cache')) { unlink($GLOBALS['config']['PAGECACHE'].'/'.$filename); }
                }
                closedir($handler);
            }
        }
    }

}


// -----------------------------------------------------------------------------------------------
// Log to text file
function logm($message)
{
    $t = strval(date('Y/m/d_H:i:s')).' - '.$_SERVER["REMOTE_ADDR"].' - '.strval($message)."\n";
    file_put_contents($GLOBALS['config']['DATADIR'].'/log.txt',$t,FILE_APPEND);
}

// Same as nl2br(), but escapes < and >
function nl2br_escaped($html)
{
    return str_replace('>','&gt;',str_replace('<','&lt;',nl2br($html)));
}

/* Returns the small hash of a string, using RFC 4648 base64url format
   eg. smallHash('20111006_131924') --> yZH23w
   Small hashes:
     - are unique (well, as unique as crc32, at last)
     - are always 6 characters long.
     - only use the following characters: a-z A-Z 0-9 - _ @
     - are NOT cryptographically secure (they CAN be forged)
   In Shaarli, they are used as a tinyurl-like link to individual entries.
*/
function smallHash($text)
{
    $t = rtrim(base64_encode(hash('crc32',$text,true)),'=');
    return strtr($t, '+/', '-_');
}

// In a string, converts urls to clickable links.
// Function inspired from http://www.php.net/manual/en/function.preg-replace.php#85722
function text2clickable($url)
{
    $redir = empty($GLOBALS['redirector']) ? '' : $GLOBALS['redirector'];
    return preg_replace('!(((?:https?|ftp|file)://|apt:|magnet:)\S+[[:alnum:]]/?)!si','<a href="'.$redir.'$1" rel="nofollow">$1</a>',$url);
}

// This function inserts &nbsp; where relevant so that multiple spaces are properly displayed in HTML
// even in the absence of <pre>  (This is used in description to keep text formatting)
function keepMultipleSpaces($text)
{
    return str_replace('  ',' &nbsp;',$text);

}
// ------------------------------------------------------------------------------------------
// Sniff browser language to display dates in the right format automatically.
// (Note that is may not work on your server if the corresponding local is not installed.)
function autoLocale()
{
    $loc='en_US'; // Default if browser does not send HTTP_ACCEPT_LANGUAGE
    if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) // eg. "fr,fr-fr;q=0.8,en;q=0.5,en-us;q=0.3"
    {   // (It's a bit crude, but it works very well. Prefered language is always presented first.)
        if (preg_match('/([a-z]{2}(-[a-z]{2})?)/i',$_SERVER['HTTP_ACCEPT_LANGUAGE'],$matches)) $loc=$matches[1];
    }
    setlocale(LC_TIME,$loc);  // LC_TIME = Set local for date/time format only.
}

// ------------------------------------------------------------------------------------------
// PubSubHubbub protocol support (if enabled)  [UNTESTED]
// (Source: http://aldarone.fr/les-flux-rss-shaarli-et-pubsubhubbub/ )
if (!empty($GLOBALS['config']['PUBSUBHUB_URL'])) include './publisher.php';
function pubsubhub()
{
    if (!empty($GLOBALS['config']['PUBSUBHUB_URL']))
    {
       $p = new Publisher($GLOBALS['config']['PUBSUBHUB_URL']);
       $topic_url = array (
                       indexUrl().'?do=atom',
                       indexUrl().'?do=rss'
                    );
       $p->publish_update($topic_url);
    }
}

// ------------------------------------------------------------------------------------------
// Session management

// Returns the IP address of the client (Used to prevent session cookie hijacking.)
function allIPs()
{
    $ip = $_SERVER["REMOTE_ADDR"];
    // Then we use more HTTP headers to prevent session hijacking from users behind the same proxy.
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) { $ip=$ip.'_'.$_SERVER['HTTP_X_FORWARDED_FOR']; }
    if (isset($_SERVER['HTTP_CLIENT_IP'])) { $ip=$ip.'_'.$_SERVER['HTTP_CLIENT_IP']; }
    return $ip;
}

function fillSessionInfo() {
	$_SESSION['uid'] = sha1(uniqid('',true).'_'.mt_rand()); // generate unique random number (different than phpsessionid)
	$_SESSION['ip']=allIPs();                // We store IP address(es) of the client to make sure session is not hijacked.
	$_SESSION['username']=$GLOBALS['login'];
	$_SESSION['expires_on']=time()+INACTIVITY_TIMEOUT;  // Set session expiration.
}

// Check that user/password is correct.
function check_auth($login,$password)
{
    $hash = sha1($password.$login.$GLOBALS['salt']);
    if ($login==$GLOBALS['login'] && $hash==$GLOBALS['hash'])
    {   // Login/password is correct.
		fillSessionInfo();
        logm('Login successful');
        return True;
    }
    logm('Login failed for user '.$login);
    return False;
}

// Returns true if the user is logged in.
function isLoggedIn()
{
    if ($GLOBALS['config']['OPEN_SHAARLI']) return true;

    if (!isset($GLOBALS['login'])) return false;  // Shaarli is not configured yet.

	if (@$_COOKIE['shaarli_staySignedIn']===STAY_SIGNED_IN_TOKEN)
	{
		fillSessionInfo();
		return true;
	}
    // If session does not exist on server side, or IP address has changed, or session has expired, logout.
    if (empty($_SESSION['uid']) || ($GLOBALS['disablesessionprotection']==false && $_SESSION['ip']!=allIPs()) || time()>=$_SESSION['expires_on'])
    {
        logout();
        return false;
    }
    if (!empty($_SESSION['longlastingsession']))  $_SESSION['expires_on']=time()+$_SESSION['longlastingsession']; // In case of "Stay signed in" checked.
    else $_SESSION['expires_on']=time()+INACTIVITY_TIMEOUT; // Standard session expiration date.

    return true;
}

// Force logout.
function logout() { if (isset($_SESSION)) { unset($_SESSION['uid']); unset($_SESSION['ip']); unset($_SESSION['username']); unset($_SESSION['privateonly']); }  
setcookie('shaarli_staySignedIn', FALSE, 0, WEB_PATH);
}


// ------------------------------------------------------------------------------------------
// Brute force protection system
// Several consecutive failed logins will ban the IP address for 30 minutes.
if (!is_file($GLOBALS['config']['IPBANS_FILENAME'])) file_put_contents($GLOBALS['config']['IPBANS_FILENAME'], "<?php\n\$GLOBALS['IPBANS']=".var_export(array('FAILURES'=>array(),'BANS'=>array()),true).";\n?>");
include $GLOBALS['config']['IPBANS_FILENAME'];
// Signal a failed login. Will ban the IP if too many failures:
function ban_loginFailed()
{
    $ip=$_SERVER["REMOTE_ADDR"]; $gb=$GLOBALS['IPBANS'];
    if (!isset($gb['FAILURES'][$ip])) $gb['FAILURES'][$ip]=0;
    $gb['FAILURES'][$ip]++;
    if ($gb['FAILURES'][$ip]>($GLOBALS['config']['BAN_AFTER']-1))
    {
        $gb['BANS'][$ip]=time()+$GLOBALS['config']['BAN_DURATION'];
        logm('IP address banned from login');
    }
    $GLOBALS['IPBANS'] = $gb;
    file_put_contents($GLOBALS['config']['IPBANS_FILENAME'], "<?php\n\$GLOBALS['IPBANS']=".var_export($gb,true).";\n?>");
}

// Signals a successful login. Resets failed login counter.
function ban_loginOk()
{
    $ip=$_SERVER["REMOTE_ADDR"]; $gb=$GLOBALS['IPBANS'];
    unset($gb['FAILURES'][$ip]); unset($gb['BANS'][$ip]);
    $GLOBALS['IPBANS'] = $gb;
    file_put_contents($GLOBALS['config']['IPBANS_FILENAME'], "<?php\n\$GLOBALS['IPBANS']=".var_export($gb,true).";\n?>");
}

// Checks if the user CAN login. If 'true', the user can try to login.
function ban_canLogin()
{
    $ip=$_SERVER["REMOTE_ADDR"]; $gb=$GLOBALS['IPBANS'];
    if (isset($gb['BANS'][$ip]))
    {
        // User is banned. Check if the ban has expired:
        if ($gb['BANS'][$ip]<=time())
        {   // Ban expired, user can try to login again.
            logm('Ban lifted.');
            unset($gb['FAILURES'][$ip]); unset($gb['BANS'][$ip]);
            file_put_contents($GLOBALS['config']['IPBANS_FILENAME'], "<?php\n\$GLOBALS['IPBANS']=".var_export($gb,true).";\n?>");
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
    if (!ban_canLogin()) die('I said: NO. You are banned for the moment. Go away.');
    if (isset($_POST['password']) && tokenOk($_POST['token']) && (check_auth($_POST['login'], $_POST['password'])))
    {   // Login/password is ok.
        ban_loginOk();
        // If user wants to keep the session cookie even after the browser closes:
        if (!empty($_POST['longlastingsession']))
        {
			setcookie('shaarli_staySignedIn', STAY_SIGNED_IN_TOKEN, time()+31536000, WEB_PATH);
            $_SESSION['longlastingsession']=31536000;  // (31536000 seconds = 1 year)
            $_SESSION['expires_on']=time()+$_SESSION['longlastingsession'];  // Set session expiration on server-side.

            $cookiedir = ''; if(dirname($_SERVER['SCRIPT_NAME'])!='/') $cookiedir=dirname($_SERVER["SCRIPT_NAME"]).'/';
            session_set_cookie_params($_SESSION['longlastingsession'],$cookiedir,$_SERVER['HTTP_HOST']); // Set session cookie expiration on client side
            // Note: Never forget the trailing slash on the cookie path !
            session_regenerate_id(true);  // Send cookie with new expiration date to browser.
        }
        else // Standard session expiration (=when browser closes)
        {
            $cookiedir = ''; if(dirname($_SERVER['SCRIPT_NAME'])!='/') $cookiedir=dirname($_SERVER["SCRIPT_NAME"]).'/';
            session_set_cookie_params(0,$cookiedir,$_SERVER['HTTP_HOST']); // 0 means "When browser closes"
            session_regenerate_id(true);
        }
        // Optional redirect after login:
        if (isset($_GET['post'])) { header('Location: ?post='.urlencode($_GET['post']).(!empty($_GET['title'])?'&title='.urlencode($_GET['title']):'').(!empty($_GET['source'])?'&source='.urlencode($_GET['source']):'')); exit; }
        if (isset($_POST['returnurl']))
        {
            if (endsWith($_POST['returnurl'],'?do=login')) { header('Location: ?'); exit; } // Prevent loops over login screen.
            header('Location: '.$_POST['returnurl']); exit;
        }
        header('Location: ?'); exit;
    }
    else
    {
        ban_loginFailed();
        $redir = '';
        if (isset($_GET['post'])) { $redir = '&post='.urlencode($_GET['post']).(!empty($_GET['title'])?'&title='.urlencode($_GET['title']):'').(!empty($_GET['source'])?'&source='.urlencode($_GET['source']):'');  }
        echo '<script language="JavaScript">alert("Wrong login/password.");document.location=\'?do=login'.$redir.'\';</script>'; // Redirect to login screen.
        exit;
    }
}

// ------------------------------------------------------------------------------------------
// Misc utility functions:

// Returns the server URL (including port and http/https), without path.
// eg. "http://myserver.com:8080"
// You can append $_SERVER['SCRIPT_NAME'] to get the current script URL.
function serverUrl()
{
    $https = (!empty($_SERVER['HTTPS']) && (strtolower($_SERVER['HTTPS'])=='on')) || $_SERVER["SERVER_PORT"]=='443'; // HTTPS detection.
    $serverport = ($_SERVER["SERVER_PORT"]=='80' || ($https && $_SERVER["SERVER_PORT"]=='443') ? '' : ':'.$_SERVER["SERVER_PORT"]);
    return 'http'.($https?'s':'').'://'.$_SERVER['HTTP_HOST'].$serverport;
}

// Returns the absolute URL of current script, without the query.
// (eg. http://sebsauvage.net/links/)
function indexUrl()
{
    $scriptname = $_SERVER["SCRIPT_NAME"];
    // If the script is named 'index.php', we remove it (for better looking URLs,
    // eg. http://mysite.com/shaarli/?abcde instead of http://mysite.com/shaarli/index.php?abcde)
    if (endswith($scriptname,'index.php')) $scriptname = substr($scriptname,0,strlen($scriptname)-9);
    return serverUrl() . $scriptname;
}

// Returns the absolute URL of current script, WITH the query.
// (eg. http://sebsauvage.net/links/?toto=titi&spamspamspam=humbug)
function pageUrl()
{
    return indexUrl().(!empty($_SERVER["QUERY_STRING"]) ? '?'.$_SERVER["QUERY_STRING"] : '');
}

// Convert post_max_size/upload_max_filesize (eg.'16M') parameters to bytes.
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
    // FIXME: Then convert back to readable notations ? (eg. 2M instead of 2000000)
    return $maxsize;
}

// Tells if a string start with a substring or not.
function startsWith($haystack,$needle,$case=true)
{
    if($case){return (strcmp(substr($haystack, 0, strlen($needle)),$needle)===0);}
    return (strcasecmp(substr($haystack, 0, strlen($needle)),$needle)===0);
}

// Tells if a string ends with a substring or not.
function endsWith($haystack,$needle,$case=true)
{
    if($case){return (strcmp(substr($haystack, strlen($haystack) - strlen($needle)),$needle)===0);}
    return (strcasecmp(substr($haystack, strlen($haystack) - strlen($needle)),$needle)===0);
}

/*  Converts a linkdate time (YYYYMMDD_HHMMSS) of an article to a timestamp (Unix epoch)
    (used to build the ADD_DATE attribute in Netscape-bookmarks file)
    PS: I could have used strptime(), but it does not exist on Windows. I'm too kind. */
function linkdate2timestamp($linkdate)
{
    $Y=$M=$D=$h=$m=$s=0;
    $r = sscanf($linkdate,'%4d%2d%2d_%2d%2d%2d',$Y,$M,$D,$h,$m,$s);
    return mktime($h,$m,$s,$M,$D,$Y);
}

/*  Converts a linkdate time (YYYYMMDD_HHMMSS) of an article to a RFC822 date.
    (used to build the pubDate attribute in RSS feed.)  */
function linkdate2rfc822($linkdate)
{
    return date('r',linkdate2timestamp($linkdate)); // 'r' is for RFC822 date format.
}

/*  Converts a linkdate time (YYYYMMDD_HHMMSS) of an article to a ISO 8601 date.
    (used to build the updated tags in ATOM feed.)  */
function linkdate2iso8601($linkdate)
{
    return date('c',linkdate2timestamp($linkdate)); // 'c' is for ISO 8601 date format.
}

/*  Converts a linkdate time (YYYYMMDD_HHMMSS) of an article to a localized date format.
    (used to display link date on screen)
    The date format is automatically chosen according to locale/languages sniffed from browser headers (see autoLocale()). */
function linkdate2locale($linkdate)
{
    return utf8_encode(strftime('%c',linkdate2timestamp($linkdate))); // %c is for automatic date format according to locale.
    // Note that if you use a local which is not installed on your webserver,
    // the date will not be displayed in the chosen locale, but probably in US notation.
}

// Parse HTTP response headers and return an associative array.
function http_parse_headers_shaarli( $headers )
{
    $res=array();
    foreach($headers as $header)
    {
        $i = strpos($header,': ');
        if ($i!==false)
        {
            $key=substr($header,0,$i);
            $value=substr($header,$i+2,strlen($header)-$i-2);
            $res[$key]=$value;
        }
    }
    return $res;
}

/* GET an URL.
   Input: $url : url to get (http://...)
          $timeout : Network timeout (will wait this many seconds for an anwser before giving up).
   Output: An array.  [0] = HTTP status message (eg. "HTTP/1.1 200 OK") or error message
                      [1] = associative array containing HTTP response headers (eg. echo getHTTP($url)[1]['Content-Type'])
                      [2] = data
    Example: list($httpstatus,$headers,$data) = getHTTP('http://sebauvage.net/');
             if (strpos($httpstatus,'200 OK')!==false)
                 echo 'Data type: '.htmlspecialchars($headers['Content-Type']);
             else
                 echo 'There was an error: '.htmlspecialchars($httpstatus)
*/
function getHTTP($url,$timeout=30)
{
    try
    {
        $options = array('http'=>array('method'=>'GET','timeout' => $timeout, 'user_agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:23.0) Gecko/20100101 Firefox/23.0')); // Force network timeout
        $context = stream_context_create($options);
        $data=file_get_contents($url,false,$context,-1, 4000000); // We download at most 4 Mb from source.
        if (!$data) { return array('HTTP Error',array(),''); }
        $httpStatus=$http_response_header[0]; // eg. "HTTP/1.1 200 OK"
        $responseHeaders=http_parse_headers_shaarli($http_response_header);
        return array($httpStatus,$responseHeaders,$data);
    }
    catch (Exception $e)  // getHTTP *can* fail silentely (we don't care if the title cannot be fetched)
    {
        return array($e->getMessage(),'','');
    }
}

// Extract title from an HTML document.
// (Returns an empty string if not found.)
function html_extract_title($html)
{
  return preg_match('!<title>(.*?)</title>!is', $html, $matches) ? trim(str_replace("\n",' ', $matches[1])) : '' ;
}

// ------------------------------------------------------------------------------------------
// Token management for XSRF protection
// Token should be used in any form which acts on data (create,update,delete,import...).
if (!isset($_SESSION['tokens'])) $_SESSION['tokens']=array();  // Token are attached to the session.

// Returns a token.
function getToken()
{
    $rnd = sha1(uniqid('',true).'_'.mt_rand().$GLOBALS['salt']);  // We generate a random string.
    $_SESSION['tokens'][$rnd]=1;  // Store it on the server side.
    return $rnd;
}

// Tells if a token is ok. Using this function will destroy the token.
// true=token is ok.
function tokenOk($token)
{
    if (isset($_SESSION['tokens'][$token]))
    {
        unset($_SESSION['tokens'][$token]); // Token is used: destroy it.
        return true; // Token is ok.
    }
    return false; // Wrong token, or already used.
}

// ------------------------------------------------------------------------------------------
/* This class is in charge of building the final page.
   (This is basically a wrapper around RainTPL which pre-fills some fields.)
   p = new pageBuilder;
   p.assign('myfield','myvalue');
   p.renderPage('mytemplate');

*/
class pageBuilder
{
    private $tpl; // RainTPL template

    function __construct()
    {
        $this->tpl=false;
    }

    private function initialize()
    {
        $this->tpl = new RainTPL;
        $this->tpl->assign('newversion',checkUpdate());
        $this->tpl->assign('feedurl',htmlspecialchars(indexUrl()));
        $searchcrits=''; // Search criteria
        if (!empty($_GET['searchtags'])) $searchcrits.='&searchtags='.urlencode($_GET['searchtags']);
        elseif (!empty($_GET['searchterm'])) $searchcrits.='&searchterm='.urlencode($_GET['searchterm']);
        $this->tpl->assign('searchcrits',$searchcrits);
        $this->tpl->assign('source',indexUrl());
        $this->tpl->assign('version',shaarli_version);
        $this->tpl->assign('scripturl',indexUrl());
        $this->tpl->assign('pagetitle','Shaarli');
        $this->tpl->assign('privateonly',!empty($_SESSION['privateonly'])); // Show only private links ?
        if (!empty($GLOBALS['title'])) $this->tpl->assign('pagetitle',$GLOBALS['title']);
        if (!empty($GLOBALS['titleLink'])) $this->tpl->assign('titleLink',$GLOBALS['titleLink']);
        if (!empty($GLOBALS['pagetitle'])) $this->tpl->assign('pagetitle',$GLOBALS['pagetitle']);
        $this->tpl->assign('shaarlititle',empty($GLOBALS['title']) ? 'Shaarli': $GLOBALS['title'] );
        return;
    }

    // The following assign() method is basically the same as RainTPL (except that it's lazy)
    public function assign($what,$where)
    {
        if ($this->tpl===false) $this->initialize(); // Lazy initialization
        $this->tpl->assign($what,$where);
    }

    // Render a specific page (using a template).
    // eg. pb.renderPage('picwall')
    public function renderPage($page)
    {
        if ($this->tpl===false) $this->initialize(); // Lazy initialization
        $this->tpl->draw($page);
    }
}

// ------------------------------------------------------------------------------------------
/* Data storage for links.
   This object behaves like an associative array.
   Example:
      $mylinks = new linkdb();
      echo $mylinks['20110826_161819']['title'];
      foreach($mylinks as $link)
         echo $link['title'].' at url '.$link['url'].' ; description:'.$link['description'];

   Available keys:
       title : Title of the link
       url : URL of the link. Can be absolute or relative. Relative URLs are permalinks (eg.'?m-ukcw')
       description : description of the entry
       private : Is this link private ? 0=no, other value=yes
       linkdate : date of the creation of this entry, in the form YYYYMMDD_HHMMSS (eg.'20110914_192317')
       tags : tags attached to this entry (separated by spaces)

   We implement 3 interfaces:
     - ArrayAccess so that this object behaves like an associative array.
     - Iterator so that this object can be used in foreach() loops.
     - Countable interface so that we can do a count() on this object.
*/
class linkdb implements Iterator, Countable, ArrayAccess
{
    private $links; // List of links (associative array. Key=linkdate (eg. "20110823_124546"), value= associative array (keys:title,description...)
    private $urls;  // List of all recorded URLs (key=url, value=linkdate) for fast reserve search (url-->linkdate)
    private $keys;  // List of linkdate keys (for the Iterator interface implementation)
    private $position; // Position in the $this->keys array. (for the Iterator interface implementation.)
    private $loggedin; // Is the used logged in ? (used to filter private links)

    // Constructor:
    function __construct($isLoggedIn)
    // Input : $isLoggedIn : is the used logged in ?
    {
        $this->loggedin = $isLoggedIn;
        $this->checkdb(); // Make sure data file exists.
        $this->readdb();  // Then read it.
    }

    // ---- Countable interface implementation
    public function count() { return count($this->links); }

    // ---- ArrayAccess interface implementation
    public function offsetSet($offset, $value)
    {
        if (!$this->loggedin) die('You are not authorized to add a link.');
        if (empty($value['linkdate']) || empty($value['url'])) die('Internal Error: A link should always have a linkdate and url.');
        if (empty($offset)) die('You must specify a key.');
        $this->links[$offset] = $value;
        $this->urls[$value['url']]=$offset;
    }
    public function offsetExists($offset) { return array_key_exists($offset,$this->links); }
    public function offsetUnset($offset)
    {
        if (!$this->loggedin) die('You are not authorized to delete a link.');
        $url = $this->links[$offset]['url']; unset($this->urls[$url]);
        unset($this->links[$offset]);
    }
    public function offsetGet($offset) { return isset($this->links[$offset]) ? $this->links[$offset] : null; }

    // ---- Iterator interface implementation
    function rewind() { $this->keys=array_keys($this->links); rsort($this->keys); $this->position=0; } // Start over for iteration, ordered by date (latest first).
    function key() { return $this->keys[$this->position]; } // current key
    function current() { return $this->links[$this->keys[$this->position]]; } // current value
    function next() { ++$this->position; } // go to next item
    function valid() { return isset($this->keys[$this->position]); }    // Check if current position is valid.

    // ---- Misc methods
    private function checkdb() // Check if db directory and file exists.
    {
        if (!file_exists($GLOBALS['config']['DATASTORE'])) // Create a dummy database for example.
        {
             $this->links = array();
             $link = array('title'=>'Shaarli - sebsauvage.net','url'=>'http://sebsauvage.net/wiki/doku.php?id=php:shaarli','description'=>'Welcome to Shaarli ! This is a bookmark. To edit or delete me, you must first login.','private'=>0,'linkdate'=>'20110914_190000','tags'=>'opensource software');
             $this->links[$link['linkdate']] = $link;
             $link = array('title'=>'My secret stuff... - Pastebin.com','url'=>'http://sebsauvage.net/paste/?8434b27936c09649#bR7XsXhoTiLcqCpQbmOpBi3rq2zzQUC5hBI7ZT1O3x8=','description'=>'SShhhh!!  I\'m a private link only YOU can see. You can delete me too.','private'=>1,'linkdate'=>'20110914_074522','tags'=>'secretstuff');
             $this->links[$link['linkdate']] = $link;
             file_put_contents($GLOBALS['config']['DATASTORE'], PHPPREFIX.base64_encode(gzdeflate(serialize($this->links))).PHPSUFFIX); // Write database to disk
        }
    }

    // Read database from disk to memory
    private function readdb()
    {
        // Read data
        $this->links=(file_exists($GLOBALS['config']['DATASTORE']) ? unserialize(gzinflate(base64_decode(substr(file_get_contents($GLOBALS['config']['DATASTORE']),strlen(PHPPREFIX),-strlen(PHPSUFFIX))))) : array() );
        // Note that gzinflate is faster than gzuncompress. See: http://www.php.net/manual/en/function.gzdeflate.php#96439

        // If user is not logged in, filter private links.
        if (!$this->loggedin)
        {
            $toremove=array();
            foreach($this->links as $link) { if ($link['private']!=0) $toremove[]=$link['linkdate']; }
            foreach($toremove as $linkdate) { unset($this->links[$linkdate]); }
        }

        // Keep the list of the mapping URLs-->linkdate up-to-date.
        $this->urls=array();
        foreach($this->links as $link) { $this->urls[$link['url']]=$link['linkdate']; }
    }

    // Save database from memory to disk.
    public function savedb()
    {
        if (!$this->loggedin) die('You are not authorized to change the database.');
        file_put_contents($GLOBALS['config']['DATASTORE'], PHPPREFIX.base64_encode(gzdeflate(serialize($this->links))).PHPSUFFIX);
        invalidateCaches();
    }

    // Returns the link for a given URL (if it exists). false it does not exist.
    public function getLinkFromUrl($url)
    {
        if (isset($this->urls[$url])) return $this->links[$this->urls[$url]];
        return false;
    }

    // Case insentitive search among links (in url, title and description). Returns filtered list of links.
    // eg. print_r($mydb->filterFulltext('hollandais'));
    public function filterFulltext($searchterms)
    {
        // FIXME: explode(' ',$searchterms) and perform a AND search.
        // FIXME: accept double-quotes to search for a string "as is" ?
        $filtered=array();
        $s = strtolower($searchterms);
        foreach($this->links as $l)
        {
            $found=   (strpos(strtolower($l['title']),$s)!==false)
                   || (strpos(strtolower($l['description']),$s)!==false)
                   || (strpos(strtolower($l['url']),$s)!==false)
                   || (strpos(strtolower($l['tags']),$s)!==false);
            if ($found) $filtered[$l['linkdate']] = $l;
        }
        krsort($filtered);
        return $filtered;
    }

    // Filter by tag.
    // You can specify one or more tags (tags can be separated by space or comma).
    // eg. print_r($mydb->filterTags('linux programming'));
    public function filterTags($tags,$casesensitive=false)
    {
        $t = str_replace(',',' ',($casesensitive?$tags:strtolower($tags)));
        $searchtags=explode(' ',$t);
        $filtered=array();
        foreach($this->links as $l)
        {
            $linktags = explode(' ',($casesensitive?$l['tags']:strtolower($l['tags'])));
            if (count(array_intersect($linktags,$searchtags)) == count($searchtags))
                $filtered[$l['linkdate']] = $l;
        }
        krsort($filtered);
        return $filtered;
    }

    // Filter by day. Day must be in the form 'YYYYMMDD' (eg. '20120125')
    // Sort order is: older articles first.
    // eg. print_r($mydb->filterDay('20120125'));
    public function filterDay($day)
    {
        $filtered=array();
        foreach($this->links as $l)
        {
            if (startsWith($l['linkdate'],$day)) $filtered[$l['linkdate']] = $l;
        }
        ksort($filtered);
        return $filtered;
    }
    // Filter by smallHash.
    // Only 1 article is returned.
    public function filterSmallHash($smallHash)
    {
        $filtered=array();
        foreach($this->links as $l)
        {
            if ($smallHash==smallHash($l['linkdate'])) // Yes, this is ugly and slow
            {
                $filtered[$l['linkdate']] = $l;
                return $filtered;
            }
        }
        return $filtered;
    }

    // Returns the list of all tags
    // Output: associative array key=tags, value=0
    public function allTags()
    {
        $tags=array();
        foreach($this->links as $link)
            foreach(explode(' ',$link['tags']) as $tag)
                if (!empty($tag)) $tags[$tag]=(empty($tags[$tag]) ? 1 : $tags[$tag]+1);
        arsort($tags); // Sort tags by usage (most used tag first)
        return $tags;
    }

    // Returns the list of days containing articles (oldest first)
    // Output: An array containing days (in format YYYYMMDD).
    public function days()
    {
        $linkdays=array();
        foreach(array_keys($this->links) as $day)
        {
            $linkdays[substr($day,0,8)]=0;
        }
        $linkdays=array_keys($linkdays);
        sort($linkdays);
        return $linkdays;
    }
}

// ------------------------------------------------------------------------------------------
// Ouput the last N links in RSS 2.0 format.
function showRSS()
{
    header('Content-Type: application/rss+xml; charset=utf-8');

    // $usepermalink : If true, use permalink instead of final link.
    // User just has to add 'permalink' in URL parameters. eg. http://mysite.com/shaarli/?do=rss&permalinks
    $usepermalinks = isset($_GET['permalinks']);

    // Cache system
    $query = $_SERVER["QUERY_STRING"];
    $cache = new pageCache(pageUrl(),startsWith($query,'do=rss') && !isLoggedIn());
    $cached = $cache->cachedVersion(); if (!empty($cached)) { echo $cached; exit; }

    // If cached was not found (or not usable), then read the database and build the response:
    $LINKSDB=new linkdb(isLoggedIn() || $GLOBALS['config']['OPEN_SHAARLI']);  // Read links from database (and filter private links if used it not logged in).

    // Optionnaly filter the results:
    $linksToDisplay=array();
    if (!empty($_GET['searchterm'])) $linksToDisplay = $LINKSDB->filterFulltext($_GET['searchterm']);
    elseif (!empty($_GET['searchtags']))   $linksToDisplay = $LINKSDB->filterTags(trim($_GET['searchtags']));
    else $linksToDisplay = $LINKSDB;
    $nblinksToDisplay = 50;  // Number of links to display.
    if (!empty($_GET['nb']))  // In URL, you can specificy the number of links. Example: nb=200 or nb=all for all links.
    { 
        $nblinksToDisplay = $_GET['nb']=='all' ? count($linksToDisplay) : max($_GET['nb']+0,1) ;
    }

    $pageaddr=htmlspecialchars(indexUrl());
    echo '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/">';
    echo '<channel><title>'.htmlspecialchars($GLOBALS['title']).'</title><link>'.$pageaddr.'</link>';
    echo '<description>Shared links</description><language>en-en</language><copyright>'.$pageaddr.'</copyright>'."\n\n";
    if (!empty($GLOBALS['config']['PUBSUBHUB_URL']))
    {
        echo '<!-- PubSubHubbub Discovery -->';
        echo '<link rel="hub" href="'.htmlspecialchars($GLOBALS['config']['PUBSUBHUB_URL']).'" xmlns="http://www.w3.org/2005/Atom" />';
        echo '<link rel="self" href="'.htmlspecialchars($pageaddr).'?do=rss" xmlns="http://www.w3.org/2005/Atom" />';
        echo '<!-- End Of PubSubHubbub Discovery -->';
    }
    $i=0;
    $keys=array(); foreach($linksToDisplay as $key=>$value) { $keys[]=$key; }  // No, I can't use array_keys().
    while ($i<$nblinksToDisplay && $i<count($keys))
    {
        $link = $linksToDisplay[$keys[$i]];
        $guid = $pageaddr.'?'.smallHash($link['linkdate']);
        $rfc822date = linkdate2rfc822($link['linkdate']);
        $absurl = htmlspecialchars($link['url']);
        if (startsWith($absurl,'?')) $absurl=$pageaddr.$absurl;  // make permalink URL absolute
        if ($usepermalinks===true)
            echo '<item><title>'.htmlspecialchars($link['title']).'</title><guid isPermaLink="false">'.$guid.'</guid><link>'.$guid.'</link>';
        else
            echo '<item><title>'.htmlspecialchars($link['title']).'</title><guid isPermaLink="false">'.$guid.'</guid><link>'.$absurl.'</link>';
        if (!$GLOBALS['config']['HIDE_TIMESTAMPS'] || isLoggedIn()) echo '<pubDate>'.htmlspecialchars($rfc822date)."</pubDate>\n";
        if ($link['tags']!='') // Adding tags to each RSS entry (as mentioned in RSS specification)
        {
            foreach(explode(' ',$link['tags']) as $tag) { echo '<category domain="'.htmlspecialchars($pageaddr).'">'.htmlspecialchars($tag).'</category>'."\n"; }
        }

        // Add permalink in description
        $descriptionlink = '(<a href="'.$guid.'">Permalink</a>)';
        // If user wants permalinks first, put the final link in description
        if ($usepermalinks===true) $descriptionlink = '(<a href="'.$absurl.'">Link</a>)';
        if (strlen($link['description'])>0) $descriptionlink = '<br>'.$descriptionlink;
        echo '<description><![CDATA['.nl2br(keepMultipleSpaces(text2clickable(htmlspecialchars($link['description'])))).$descriptionlink.']]></description>'."\n</item>\n";
        $i++;
    }
    echo '</channel></rss><!-- Cached version of '.htmlspecialchars(pageUrl()).' -->';

    $cache->cache(ob_get_contents());
    ob_end_flush();
    exit;
}

// ------------------------------------------------------------------------------------------
// Ouput the last N links in ATOM format.
function showATOM()
{
    header('Content-Type: application/atom+xml; charset=utf-8');

    // $usepermalink : If true, use permalink instead of final link.
    // User just has to add 'permalink' in URL parameters. eg. http://mysite.com/shaarli/?do=atom&permalinks
    $usepermalinks = isset($_GET['permalinks']);

    // Cache system
    $query = $_SERVER["QUERY_STRING"];
    $cache = new pageCache(pageUrl(),startsWith($query,'do=atom') && !isLoggedIn());
    $cached = $cache->cachedVersion(); if (!empty($cached)) { echo $cached; exit; }
    // If cached was not found (or not usable), then read the database and build the response:

    $LINKSDB=new linkdb(isLoggedIn() || $GLOBALS['config']['OPEN_SHAARLI']);  // Read links from database (and filter private links if used it not logged in).


    // Optionnaly filter the results:
    $linksToDisplay=array();
    if (!empty($_GET['searchterm'])) $linksToDisplay = $LINKSDB->filterFulltext($_GET['searchterm']);
    elseif (!empty($_GET['searchtags']))   $linksToDisplay = $LINKSDB->filterTags(trim($_GET['searchtags']));
    else $linksToDisplay = $LINKSDB;
    $nblinksToDisplay = 50;  // Number of links to display.
    if (!empty($_GET['nb']))  // In URL, you can specificy the number of links. Example: nb=200 or nb=all for all links.
    { 
        $nblinksToDisplay = $_GET['nb']=='all' ? count($linksToDisplay) : max($_GET['nb']+0,1) ;
    }

    $pageaddr=htmlspecialchars(indexUrl());
    $latestDate = '';
    $entries='';
    $i=0;
    $keys=array(); foreach($linksToDisplay as $key=>$value) { $keys[]=$key; }  // No, I can't use array_keys().
    while ($i<$nblinksToDisplay && $i<count($keys))
    {
        $link = $linksToDisplay[$keys[$i]];
        $guid = $pageaddr.'?'.smallHash($link['linkdate']);
        $iso8601date = linkdate2iso8601($link['linkdate']);
        $latestDate = max($latestDate,$iso8601date);
        $absurl = htmlspecialchars($link['url']);
        if (startsWith($absurl,'?')) $absurl=$pageaddr.$absurl;  // make permalink URL absolute
        $entries.='<entry><title>'.htmlspecialchars($link['title']).'</title>';
        if ($usepermalinks===true)
            $entries.='<link href="'.$guid.'" /><id>'.$guid.'</id>';
        else
            $entries.='<link href="'.$absurl.'" /><id>'.$guid.'</id>';
        if (!$GLOBALS['config']['HIDE_TIMESTAMPS'] || isLoggedIn()) $entries.='<updated>'.htmlspecialchars($iso8601date).'</updated>';

        // Add permalink in description
        $descriptionlink = htmlspecialchars('(<a href="'.$guid.'">Permalink</a>)');
        // If user wants permalinks first, put the final link in description
        if ($usepermalinks===true) $descriptionlink = htmlspecialchars('(<a href="'.$absurl.'">Link</a>)');
        if (strlen($link['description'])>0) $descriptionlink = '&lt;br&gt;'.$descriptionlink;

        $entries.='<content type="html">'.htmlspecialchars(nl2br(keepMultipleSpaces(text2clickable(htmlspecialchars($link['description']))))).$descriptionlink."</content>\n";
        if ($link['tags']!='') // Adding tags to each ATOM entry (as mentioned in ATOM specification)
        {
            foreach(explode(' ',$link['tags']) as $tag)
                { $entries.='<category scheme="'.htmlspecialchars($pageaddr,ENT_QUOTES).'" term="'.htmlspecialchars($tag,ENT_QUOTES).'" />'."\n"; }
        }
        $entries.="</entry>\n";
        $i++;
    }
    $feed='<?xml version="1.0" encoding="UTF-8"?><feed xmlns="http://www.w3.org/2005/Atom">';
    $feed.='<title>'.htmlspecialchars($GLOBALS['title']).'</title>';
    if (!$GLOBALS['config']['HIDE_TIMESTAMPS'] || isLoggedIn()) $feed.='<updated>'.htmlspecialchars($latestDate).'</updated>';
    $feed.='<link rel="self" href="'.htmlspecialchars(serverUrl().$_SERVER["REQUEST_URI"]).'" />';
    if (!empty($GLOBALS['config']['PUBSUBHUB_URL']))
    {
        $feed.='<!-- PubSubHubbub Discovery -->';
        $feed.='<link rel="hub" href="'.htmlspecialchars($GLOBALS['config']['PUBSUBHUB_URL']).'" />';
        $feed.='<!-- End Of PubSubHubbub Discovery -->';
    }
    $feed.='<author><name>'.htmlspecialchars($pageaddr).'</name><uri>'.htmlspecialchars($pageaddr).'</uri></author>';
    $feed.='<id>'.htmlspecialchars($pageaddr).'</id>'."\n\n"; // Yes, I know I should use a real IRI (RFC3987), but the site URL will do.
    $feed.=$entries;
    $feed.='</feed><!-- Cached version of '.htmlspecialchars(pageUrl()).' -->';
    echo $feed;

    $cache->cache(ob_get_contents());
    ob_end_flush();
    exit;
}

// ------------------------------------------------------------------------------------------
// Daily RSS feed: 1 RSS entry per day giving all the links on that day.
// Gives the last 7 days (which have links).
// This RSS feed cannot be filtered.
function showDailyRSS()
{
    // Cache system
    $query = $_SERVER["QUERY_STRING"];
    $cache = new pageCache(pageUrl(),startsWith($query,'do=dailyrss') && !isLoggedIn());
    $cached = $cache->cachedVersion(); if (!empty($cached)) { echo $cached; exit; }
    // If cached was not found (or not usable), then read the database and build the response:
    $LINKSDB=new linkdb(isLoggedIn() || $GLOBALS['config']['OPEN_SHAARLI']);  // Read links from database (and filter private links if used it not logged in).

    /* Some Shaarlies may have very few links, so we need to look
       back in time (rsort()) until we have enough days ($nb_of_days).
    */
    $linkdates=array(); foreach($LINKSDB as $linkdate=>$value) { $linkdates[]=$linkdate; }
    rsort($linkdates);
    $nb_of_days=7; // We take 7 days.
    $today=Date('Ymd');
    $days=array();
    foreach($linkdates as $linkdate)
    {
        $day=substr($linkdate,0,8); // Extract day (without time)
        if (strcmp($day,$today)<0)
        {
            if (empty($days[$day])) $days[$day]=array();
            $days[$day][]=$linkdate;
        }
        if (count($days)>$nb_of_days) break; // Have we collected enough days ?
    }

    // Build the RSS feed.
    header('Content-Type: application/rss+xml; charset=utf-8');
    $pageaddr=htmlspecialchars(indexUrl());
    echo '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0">';
    echo '<channel><title>Daily - '.htmlspecialchars($GLOBALS['title']).'</title><link>'.$pageaddr.'</link>';
    echo '<description>Daily shared links</description><language>en-en</language><copyright>'.$pageaddr.'</copyright>'."\n";

    foreach($days as $day=>$linkdates) // For each day.
    {
        $daydate = utf8_encode(strftime('%A %d, %B %Y',linkdate2timestamp($day.'_000000'))); // Full text date
        $rfc822date = linkdate2rfc822($day.'_000000');
        $absurl=htmlspecialchars(indexUrl().'?do=daily&day='.$day);  // Absolute URL of the corresponding "Daily" page.
        echo '<item><title>'.htmlspecialchars($GLOBALS['title'].' - '.$daydate).'</title><guid>'.$absurl.'</guid><link>'.$absurl.'</link>';
        echo '<pubDate>'.htmlspecialchars($rfc822date)."</pubDate>";

        // Build the HTML body of this RSS entry.
        $html='';
        $href='';
        $links=array();
        // We pre-format some fields for proper output.
        foreach($linkdates as $linkdate)
        {
            $l = $LINKSDB[$linkdate];
            $l['formatedDescription']=nl2br(keepMultipleSpaces(text2clickable(htmlspecialchars($l['description']))));
            $l['thumbnail'] = thumbnail($l['url']);
            $l['localdate']=linkdate2locale($l['linkdate']);
            if (startsWith($l['url'],'?')) $l['url']=indexUrl().$l['url'];  // make permalink URL absolute
            $links[$linkdate]=$l;
        }
        // Then build the HTML for this day:
        $tpl = new RainTPL;
        $tpl->assign('links',$links);
        $html = $tpl->draw('dailyrss',$return_string=true);
        echo "\n";
        echo '<description><![CDATA['.$html.']]></description>'."\n</item>\n\n";

    }
    echo '</channel></rss><!-- Cached version of '.htmlspecialchars(pageUrl()).' -->';

    $cache->cache(ob_get_contents());
    ob_end_flush();
    exit;
}

// "Daily" page.
function showDaily()
{
    $LINKSDB=new linkdb(isLoggedIn() || $GLOBALS['config']['OPEN_SHAARLI']);  // Read links from database (and filter private links if used it not logged in).


    $day=Date('Ymd',strtotime('-1 day')); // Yesterday, in format YYYYMMDD.
    if (isset($_GET['day'])) $day=$_GET['day'];

    $days = $LINKSDB->days();
    $i = array_search($day,$days);
    if ($i==false) { $i=count($days)-1; $day=$days[$i]; }
    $previousday='';
    $nextday='';
    if ($i!==false)
    {
        if ($i>1) $previousday=$days[$i-1];
        if ($i<count($days)-1) $nextday=$days[$i+1];
    }

    $linksToDisplay=$LINKSDB->filterDay($day);
    // We pre-format some fields for proper output.
    foreach($linksToDisplay as $key=>$link)
    {
        $taglist = explode(' ',$link['tags']);
        uasort($taglist, 'strcasecmp');
        $linksToDisplay[$key]['taglist']=$taglist;
        $linksToDisplay[$key]['formatedDescription']=nl2br(keepMultipleSpaces(text2clickable(htmlspecialchars($link['description']))));
        $linksToDisplay[$key]['thumbnail'] = thumbnail($link['url']);
    }

    /* We need to spread the articles on 3 columns.
       I did not want to use a javascript lib like http://masonry.desandro.com/
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
        // This is not perfect, but it's usually ok.
        $length=strlen($link['title'])+(342*strlen($link['description']))/836;
        if ($link['thumbnail']) $length +=100; // 1 thumbnails roughly takes 100 pixels height.
        // Then put in column which is the less filled:
        $smallest=min($fill); // find smallest value in array.
        $index=array_search($smallest,$fill); // find index of this smallest value.
        array_push($columns[$index],$link); // Put entry in this column.
        $fill[$index]+=$length;
    }
    $PAGE = new pageBuilder;
    $PAGE->assign('linksToDisplay',$linksToDisplay);
    $PAGE->assign('linkcount',count($LINKSDB));
    $PAGE->assign('col1',$columns[0]);
    $PAGE->assign('col1',$columns[0]);
    $PAGE->assign('col2',$columns[1]);
    $PAGE->assign('col3',$columns[2]);
    $PAGE->assign('day',utf8_encode(strftime('%A %d, %B %Y',linkdate2timestamp($day.'_000000'))));
    $PAGE->assign('previousday',$previousday);
    $PAGE->assign('nextday',$nextday);
    $PAGE->renderPage('daily');
    exit;
}


// ------------------------------------------------------------------------------------------
// Render HTML page (according to URL parameters and user rights)
function renderPage()
{
    $LINKSDB=new linkdb(isLoggedIn() || $GLOBALS['config']['OPEN_SHAARLI']);  // Read links from database (and filter private links if used it not logged in).

    // -------- Display login form.
    if (isset($_SERVER["QUERY_STRING"]) && startswith($_SERVER["QUERY_STRING"],'do=login'))
    {
        if ($GLOBALS['config']['OPEN_SHAARLI']) { header('Location: ?'); exit; }  // No need to login for open Shaarli
        $token=''; if (ban_canLogin()) $token=getToken(); // Do not waste token generation if not useful.
        $PAGE = new pageBuilder;
        $PAGE->assign('token',$token);
        $PAGE->assign('returnurl',(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER']:''));
        $PAGE->renderPage('loginform');
        exit;
    }
    // -------- User wants to logout.
    if (isset($_SERVER["QUERY_STRING"]) && startswith($_SERVER["QUERY_STRING"],'do=logout'))
    {
        invalidateCaches();
        logout();
        header('Location: ?');
        exit;
    }

    // -------- Picture wall
    if (isset($_SERVER["QUERY_STRING"]) && startswith($_SERVER["QUERY_STRING"],'do=picwall'))
    {
        // Optionnaly filter the results:
        $links=array();
        if (!empty($_GET['searchterm'])) $links = $LINKSDB->filterFulltext($_GET['searchterm']);
        elseif (!empty($_GET['searchtags']))   $links = $LINKSDB->filterTags(trim($_GET['searchtags']));
        else $links = $LINKSDB;
        $body='';
        $linksToDisplay=array();

        // Get only links which have a thumbnail.
        foreach($links as $link)
        {
            $permalink='?'.htmlspecialchars(smallhash($link['linkdate']),ENT_QUOTES);
            $thumb=lazyThumbnail($link['url'],$permalink);
            if ($thumb!='') // Only output links which have a thumbnail.
            {
                $link['thumbnail']=$thumb; // Thumbnail HTML code.
                $link['permalink']=$permalink;
                $linksToDisplay[]=$link; // Add to array.
            }
        }
        $PAGE = new pageBuilder;
        $PAGE->assign('linkcount',count($LINKSDB));
        $PAGE->assign('linksToDisplay',$linksToDisplay);
        $PAGE->renderPage('picwall');
        exit;
    }

    // -------- Tag cloud
    if (isset($_SERVER["QUERY_STRING"]) && startswith($_SERVER["QUERY_STRING"],'do=tagcloud'))
    {
        $tags= $LINKSDB->allTags();
        // We sort tags alphabetically, then choose a font size according to count.
        // First, find max value.
        $maxcount=0; foreach($tags as $key=>$value) $maxcount=max($maxcount,$value);
        ksort($tags);
        $tagList=array();
        foreach($tags as $key=>$value)
        {
            $tagList[$key] = array('count'=>$value,'size'=>max(40*$value/$maxcount,8));
        }
        $PAGE = new pageBuilder;
        $PAGE->assign('linkcount',count($LINKSDB));
        $PAGE->assign('tags',$tagList);
        $PAGE->renderPage('tagcloud');
        exit;
    }

    // -------- User clicks on a tag in a link: The tag is added to the list of searched tags (searchtags=...)
    if (isset($_GET['addtag']))
    {
        // Get previous URL (http_referer) and add the tag to the searchtags parameters in query.
        if (empty($_SERVER['HTTP_REFERER'])) { header('Location: ?searchtags='.urlencode($_GET['addtag'])); exit; } // In case browser does not send HTTP_REFERER
        parse_str(parse_url($_SERVER['HTTP_REFERER'],PHP_URL_QUERY), $params);
        $params['searchtags'] = (empty($params['searchtags']) ?  trim($_GET['addtag']) : trim($params['searchtags']).' '.trim($_GET['addtag']));
        unset($params['page']); // We also remove page (keeping the same page has no sense, since the results are different)
        header('Location: ?'.http_build_query($params));
        exit;
    }

    // -------- User clicks on a tag in result count: Remove the tag from the list of searched tags (searchtags=...)
    if (isset($_GET['removetag']))
    {
        // Get previous URL (http_referer) and remove the tag from the searchtags parameters in query.
        if (empty($_SERVER['HTTP_REFERER'])) { header('Location: ?'); exit; } // In case browser does not send HTTP_REFERER
        parse_str(parse_url($_SERVER['HTTP_REFERER'],PHP_URL_QUERY), $params);
        if (isset($params['searchtags']))
        {
            $tags = explode(' ',$params['searchtags']);
            $tags=array_diff($tags, array($_GET['removetag'])); // Remove value from array $tags.
            if (count($tags)==0) unset($params['searchtags']); else $params['searchtags'] = implode(' ',$tags);
            unset($params['page']); // We also remove page (keeping the same page has no sense, since the results are different)
        }
        header('Location: ?'.http_build_query($params));
        exit;
    }

    // -------- User wants to change the number of links per page (linksperpage=...)
    if (isset($_GET['linksperpage']))
    {
        if (is_numeric($_GET['linksperpage'])) { $_SESSION['LINKS_PER_PAGE']=abs(intval($_GET['linksperpage'])); }
        // Make sure the referer is from Shaarli itself.
        $referer = '?';
        if (!empty($_SERVER['HTTP_REFERER']) && strcmp(parse_url($_SERVER['HTTP_REFERER'],PHP_URL_HOST),$_SERVER['HTTP_HOST'])==0)
            $referer = $_SERVER['HTTP_REFERER'];
        header('Location: '.$referer);
        exit;
    }

    // -------- User wants to see only private links (toggle)
    if (isset($_GET['privateonly']))
    {
        if (empty($_SESSION['privateonly']))
        {
            $_SESSION['privateonly']=1; // See only private links
        }
        else
        {
            unset($_SESSION['privateonly']); // See all links
        }
        // Make sure the referer is from Shaarli itself.
        $referer = '?';
        if (!empty($_SERVER['HTTP_REFERER']) && strcmp(parse_url($_SERVER['HTTP_REFERER'],PHP_URL_HOST),$_SERVER['HTTP_HOST'])==0)
            $referer = $_SERVER['HTTP_REFERER'];
        header('Location: '.$referer);
        exit;
    }

    // -------- Handle other actions allowed for non-logged in users:
    if (!isLoggedIn())
    {
        // User tries to post new link but is not loggedin:
        // Show login screen, then redirect to ?post=...
        if (isset($_GET['post']))
        {
            header('Location: ?do=login&post='.urlencode($_GET['post']).(!empty($_GET['title'])?'&title='.urlencode($_GET['title']):'').(!empty($_GET['source'])?'&source='.urlencode($_GET['source']):'')); // Redirect to login page, then back to post link.
            exit;
        }
        $PAGE = new pageBuilder;
        buildLinkList($PAGE,$LINKSDB); // Compute list of links to display
        $PAGE->renderPage('linklist');
        exit; // Never remove this one ! All operations below are reserved for logged in user.
    }

    // -------- All other functions are reserved for the registered user:

    // -------- Display the Tools menu if requested (import/export/bookmarklet...)
    if (isset($_SERVER["QUERY_STRING"]) && startswith($_SERVER["QUERY_STRING"],'do=tools'))
    {
        $PAGE = new pageBuilder;
        $PAGE->assign('linkcount',count($LINKSDB));
        $PAGE->assign('pageabsaddr',indexUrl());
        $PAGE->renderPage('tools');
        exit;
    }

    // -------- User wants to change his/her password.
    if (isset($_SERVER["QUERY_STRING"]) && startswith($_SERVER["QUERY_STRING"],'do=changepasswd'))
    {
        if ($GLOBALS['config']['OPEN_SHAARLI']) die('You are not supposed to change a password on an Open Shaarli.');
        if (!empty($_POST['setpassword']) && !empty($_POST['oldpassword']))
        {
            if (!tokenOk($_POST['token'])) die('Wrong token.'); // Go away !

            // Make sure old password is correct.
            $oldhash = sha1($_POST['oldpassword'].$GLOBALS['login'].$GLOBALS['salt']);
            if ($oldhash!=$GLOBALS['hash']) { echo '<script language="JavaScript">alert("The old password is not correct.");document.location=\'?do=changepasswd\';</script>'; exit; }
            // Save new password
            $GLOBALS['salt'] = sha1(uniqid('',true).'_'.mt_rand()); // Salt renders rainbow-tables attacks useless.
            $GLOBALS['hash'] = sha1($_POST['setpassword'].$GLOBALS['login'].$GLOBALS['salt']);
            writeConfig();
            echo '<script language="JavaScript">alert("Your password has been changed.");document.location=\'?do=tools\';</script>';
            exit;
        }
        else // show the change password form.
        {
            $PAGE = new pageBuilder;
            $PAGE->assign('linkcount',count($LINKSDB));
            $PAGE->assign('token',getToken());
            $PAGE->renderPage('changepassword');
            exit;
        }
    }

    // -------- User wants to change configuration
    if (isset($_SERVER["QUERY_STRING"]) && startswith($_SERVER["QUERY_STRING"],'do=configure'))
    {
        if (!empty($_POST['title']) )
        {
            if (!tokenOk($_POST['token'])) die('Wrong token.'); // Go away !
            $tz = 'UTC';
            if (!empty($_POST['continent']) && !empty($_POST['city']))
                if (isTZvalid($_POST['continent'],$_POST['city']))
                    $tz = $_POST['continent'].'/'.$_POST['city'];
            $GLOBALS['timezone'] = $tz;
            $GLOBALS['title']=$_POST['title'];
            $GLOBALS['titleLink']=$_POST['titleLink'];
            $GLOBALS['redirector']=$_POST['redirector'];
            $GLOBALS['disablesessionprotection']=!empty($_POST['disablesessionprotection']);
            $GLOBALS['disablejquery']=!empty($_POST['disablejquery']);
            $GLOBALS['privateLinkByDefault']=!empty($_POST['privateLinkByDefault']);
            writeConfig();
            echo '<script language="JavaScript">alert("Configuration was saved.");document.location=\'?do=tools\';</script>';
            exit;
        }
        else // Show the configuration form.
        {
            $PAGE = new pageBuilder;
            $PAGE->assign('linkcount',count($LINKSDB));
            $PAGE->assign('token',getToken());
            $PAGE->assign('title',htmlspecialchars( empty($GLOBALS['title']) ? '' : $GLOBALS['title'] , ENT_QUOTES));
            $PAGE->assign('redirector',htmlspecialchars( empty($GLOBALS['redirector']) ? '' : $GLOBALS['redirector'] , ENT_QUOTES));
            list($timezone_form,$timezone_js) = templateTZform($GLOBALS['timezone']);
            $PAGE->assign('timezone_form',$timezone_form); // FIXME: put entire tz form generation in template ?
            $PAGE->assign('timezone_js',$timezone_js);
            $PAGE->renderPage('configure');
            exit;
        }
    }

    // -------- User wants to rename a tag or delete it
    if (isset($_SERVER["QUERY_STRING"]) && startswith($_SERVER["QUERY_STRING"],'do=changetag'))
    {
        if (empty($_POST['fromtag']))
        {
            $PAGE = new pageBuilder;
            $PAGE->assign('linkcount',count($LINKSDB));
            $PAGE->assign('token',getToken());
            $PAGE->renderPage('changetag');
            exit;
        }
        if (!tokenOk($_POST['token'])) die('Wrong token.');

        // Delete a tag:
        if (!empty($_POST['deletetag']) && !empty($_POST['fromtag']))
        {
            $needle=trim($_POST['fromtag']);
            $linksToAlter = $LINKSDB->filterTags($needle,true); // true for case-sensitive tag search.
            foreach($linksToAlter as $key=>$value)
            {
                $tags = explode(' ',trim($value['tags']));
                unset($tags[array_search($needle,$tags)]); // Remove tag.
                $value['tags']=trim(implode(' ',$tags));
                $LINKSDB[$key]=$value;
            }
            $LINKSDB->savedb(); // save to disk
            echo '<script language="JavaScript">alert("Tag was removed from '.count($linksToAlter).' links.");document.location=\'?\';</script>';
            exit;
        }

        // Rename a tag:
        if (!empty($_POST['renametag']) && !empty($_POST['fromtag']) && !empty($_POST['totag']))
        {
            $needle=trim($_POST['fromtag']);
            $linksToAlter = $LINKSDB->filterTags($needle,true); // true for case-sensitive tag search.
            foreach($linksToAlter as $key=>$value)
            {
                $tags = explode(' ',trim($value['tags']));
                $tags[array_search($needle,$tags)] = trim($_POST['totag']); // Remplace tags value.
                $value['tags']=trim(implode(' ',$tags));
                $LINKSDB[$key]=$value;
            }
            $LINKSDB->savedb(); // save to disk
            echo '<script language="JavaScript">alert("Tag was renamed in '.count($linksToAlter).' links.");document.location=\'?searchtags='.urlencode($_POST['totag']).'\';</script>';
            exit;
        }
    }

    // -------- User wants to add a link without using the bookmarklet: show form.
    if (isset($_SERVER["QUERY_STRING"]) && startswith($_SERVER["QUERY_STRING"],'do=addlink'))
    {
        $PAGE = new pageBuilder;
        $PAGE->assign('linkcount',count($LINKSDB));
        $PAGE->renderPage('addlink');
        exit;
    }

    // -------- User clicked the "Save" button when editing a link: Save link to database.
    if (isset($_POST['save_edit']))
    {
        if (!tokenOk($_POST['token'])) die('Wrong token.'); // Go away !
        $tags = trim(preg_replace('/\s\s+/',' ', $_POST['lf_tags'])); // Remove multiple spaces.
        $linkdate=$_POST['lf_linkdate'];
        $url = trim($_POST['lf_url']);
        if (!startsWith($url,'http:') && !startsWith($url,'https:') && !startsWith($url,'ftp:') && !startsWith($url,'magnet:') && !startsWith($url,'?'))
            $url = 'http://'.$url;
        $link = array('title'=>trim($_POST['lf_title']),'url'=>$url,'description'=>trim($_POST['lf_description']),'private'=>(isset($_POST['lf_private']) ? 1 : 0),
                      'linkdate'=>$linkdate,'tags'=>str_replace(',',' ',$tags));
        if ($link['title']=='') $link['title']=$link['url']; // If title is empty, use the URL as title.
        $LINKSDB[$linkdate] = $link;
        $LINKSDB->savedb(); // save to disk
        pubsubhub();

        // If we are called from the bookmarklet, we must close the popup:
        if (isset($_GET['source']) && $_GET['source']=='bookmarklet') { echo '<script language="JavaScript">self.close();</script>'; exit; }
        $returnurl = ( isset($_POST['returnurl']) ? $_POST['returnurl'] : '?' );
        $returnurl .= '#'.smallHash($linkdate);  // Scroll to the link which has been edited.
        header('Location: '.$returnurl); // After saving the link, redirect to the page the user was on.
        exit;
    }

    // -------- User clicked the "Cancel" button when editing a link.
    if (isset($_POST['cancel_edit']))
    {
        // If we are called from the bookmarklet, we must close the popup;
        if (isset($_GET['source']) && $_GET['source']=='bookmarklet') { echo '<script language="JavaScript">self.close();</script>'; exit; }
        $returnurl = ( isset($_POST['returnurl']) ? $_POST['returnurl'] : '?' );
        $returnurl .= '#'.smallHash($_POST['lf_linkdate']);  // Scroll to the link which has been edited.
        header('Location: '.$returnurl); // After canceling, redirect to the page the user was on.
        exit;
    }

    // -------- User clicked the "Delete" button when editing a link : Delete link from database.
    if (isset($_POST['delete_link']))
    {
        if (!tokenOk($_POST['token'])) die('Wrong token.');
        // We do not need to ask for confirmation:
        // - confirmation is handled by javascript
        // - we are protected from XSRF by the token.
        $linkdate=$_POST['lf_linkdate'];
        unset($LINKSDB[$linkdate]);
        $LINKSDB->savedb(); // save to disk

        // If we are called from the bookmarklet, we must close the popup:
        if (isset($_GET['source']) && $_GET['source']=='bookmarklet') { echo '<script language="JavaScript">self.close();</script>'; exit; }
        $returnurl = ( isset($_POST['returnurl']) ? $_POST['returnurl'] : '?' );
        if ($returnurl=='?') { $returnurl = (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '?'); }
        header('Location: '.$returnurl); // After deleting the link, redirect to the page the user was on.
        exit;
    }

    // -------- User clicked the "EDIT" button on a link: Display link edit form.
    if (isset($_GET['edit_link']))
    {
        $link = $LINKSDB[$_GET['edit_link']];  // Read database
        if (!$link) { header('Location: ?'); exit; } // Link not found in database.
        $PAGE = new pageBuilder;
        $PAGE->assign('linkcount',count($LINKSDB));
        $PAGE->assign('link',$link);
        $PAGE->assign('link_is_new',false);
        $PAGE->assign('token',getToken()); // XSRF protection.
        $PAGE->assign('http_referer',(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : ''));
        $PAGE->renderPage('editlink');
        exit;
    }

    // -------- User want to post a new link: Display link edit form.
    if (isset($_GET['post']))
    {
        $url=$_GET['post'];

        // We remove the annoying parameters added by FeedBurner and GoogleFeedProxy (?utm_source=...)
        $i=strpos($url,'&utm_source='); if ($i!==false) $url=substr($url,0,$i);
        $i=strpos($url,'?utm_source='); if ($i!==false) $url=substr($url,0,$i);
        $i=strpos($url,'#xtor=RSS-'); if ($i!==false) $url=substr($url,0,$i);

        $link_is_new = false;
        $link = $LINKSDB->getLinkFromUrl($url); // Check if URL is not already in database (in this case, we will edit the existing link)
        if (!$link)
        {
            $link_is_new = true;  // This is a new link
            $linkdate = strval(date('Ymd_His'));
            $title = (empty($_GET['title']) ? '' : $_GET['title'] ); // Get title if it was provided in URL (by the bookmarklet).
            $description = (empty($_GET['description']) ? '' : $_GET['description']); // Get description if it was provided in URL (by the bookmarklet). [Bronco added that]
            $tags = (empty($_GET['tags']) ? '' : $_GET['tags'] ); // Get tags if it was provided in URL
            $private = (!empty($_GET['private']) && $_GET['private'] === "1" ? 1 : 0); // Get private if it was provided in URL 
            if (($url!='') && parse_url($url,PHP_URL_SCHEME)=='') $url = 'http://'.$url;
            // If this is an HTTP link, we try go get the page to extact the title (otherwise we will to straight to the edit form.)
            if (empty($title) && parse_url($url,PHP_URL_SCHEME)=='http')
            {
                list($status,$headers,$data) = getHTTP($url,4); // Short timeout to keep the application responsive.
                // FIXME: Decode charset according to specified in either 1) HTTP response headers or 2) <head> in html
                if (strpos($status,'200 OK')!==false)
 					 {
                        // Look for charset in html header.
 						preg_match('#<meta .*charset=.*>#Usi', $data, $meta);
 
 						// If found, extract encoding.
 						if (!empty($meta[0]))
 						{
 							// Get encoding specified in header.
 							preg_match('#charset="?(.*)"#si', $meta[0], $enc);
 							// If charset not found, use utf-8.
							$html_charset = (!empty($enc[1])) ? strtolower($enc[1]) : 'utf-8';
 						}
 						else { $html_charset = 'utf-8'; }
 
 						// Extract title
 						$title = html_extract_title($data);
 						if (!empty($title))
 						{
 							// Re-encode title in utf-8 if necessary.
 							$title = ($html_charset == 'iso-8859-1') ? utf8_encode($title) : $title;
 						}
 					}
            }
            if ($url=='') $url='?'.smallHash($linkdate); // In case of empty URL, this is just a text (with a link that point to itself)
            $link = array('linkdate'=>$linkdate,'title'=>$title,'url'=>$url,'description'=>$description,'tags'=>$tags,'private'=>$private);
        }

        $PAGE = new pageBuilder;
        $PAGE->assign('linkcount',count($LINKSDB));
        $PAGE->assign('link',$link);
        $PAGE->assign('link_is_new',$link_is_new);
        $PAGE->assign('token',getToken()); // XSRF protection.
        $PAGE->assign('http_referer',(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : ''));
        $PAGE->renderPage('editlink');
        exit;
    }

    // -------- Export as Netscape Bookmarks HTML file.
    if (isset($_SERVER["QUERY_STRING"]) && startswith($_SERVER["QUERY_STRING"],'do=export'))
    {
        if (empty($_GET['what']))
        {
            $PAGE = new pageBuilder;
            $PAGE->assign('linkcount',count($LINKSDB));
            $PAGE->renderPage('export');
            exit;
        }
        $exportWhat=$_GET['what'];
        if (!array_intersect(array('all','public','private'),array($exportWhat))) die('What are you trying to export ???');

        header('Content-Type: text/html; charset=utf-8');
        header('Content-disposition: attachment; filename=bookmarks_'.$exportWhat.'_'.strval(date('Ymd_His')).'.html');
        $currentdate=date('Y/m/d H:i:s');
        echo <<<HTML
<!DOCTYPE NETSCAPE-Bookmark-file-1>
<!-- This is an automatically generated file.
     It will be read and overwritten.
     DO NOT EDIT! -->
<!-- Shaarli {$exportWhat} bookmarks export on {$currentdate} -->
<META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=UTF-8">
<TITLE>Bookmarks</TITLE>
<H1>Bookmarks</H1>
HTML;
        foreach($LINKSDB as $link)
        {
            if ($exportWhat=='all' ||
               ($exportWhat=='private' && $link['private']!=0) ||
               ($exportWhat=='public' && $link['private']==0))
            {
                echo '<DT><A HREF="'.htmlspecialchars($link['url']).'" ADD_DATE="'.linkdate2timestamp($link['linkdate']).'" PRIVATE="'.$link['private'].'"';
                if ($link['tags']!='') echo ' TAGS="'.htmlspecialchars(str_replace(' ',',',$link['tags'])).'"';
                echo '>'.htmlspecialchars($link['title'])."</A>\n";
                if ($link['description']!='') echo '<DD>'.htmlspecialchars($link['description'])."\n";
            }
        }
                exit;
    }

    // -------- User is uploading a file for import
    if (isset($_SERVER["QUERY_STRING"]) && startswith($_SERVER["QUERY_STRING"],'do=upload'))
    {
        // If file is too big, some form field may be missing.
        if (!isset($_POST['token']) || (!isset($_FILES)) || (isset($_FILES['filetoupload']['size']) && $_FILES['filetoupload']['size']==0))
        {
            $returnurl = ( empty($_SERVER['HTTP_REFERER']) ? '?' : $_SERVER['HTTP_REFERER'] );
            echo '<script language="JavaScript">alert("The file you are trying to upload is probably bigger than what this webserver can accept ('.getMaxFileSize().' bytes). Please upload in smaller chunks.");document.location=\''.htmlspecialchars($returnurl).'\';</script>';
            exit;
        }
        if (!tokenOk($_POST['token'])) die('Wrong token.');
        importFile();
        exit;
    }

    // -------- Show upload/import dialog:
    if (isset($_SERVER["QUERY_STRING"]) && startswith($_SERVER["QUERY_STRING"],'do=import'))
    {
        $PAGE = new pageBuilder;
        $PAGE->assign('linkcount',count($LINKSDB));
        $PAGE->assign('token',getToken());
        $PAGE->assign('maxfilesize',getMaxFileSize());
        $PAGE->renderPage('import');
        exit;
    }

    // -------- Otherwise, simply display search form and links:
    $PAGE = new pageBuilder;
    $PAGE->assign('linkcount',count($LINKSDB));
    buildLinkList($PAGE,$LINKSDB); // Compute list of links to display
    $PAGE->renderPage('linklist');
    exit;
}

// -----------------------------------------------------------------------------------------------
// Process the import file form.
function importFile()
{
    if (!(isLoggedIn() || $GLOBALS['config']['OPEN_SHAARLI'])) { die('Not allowed.'); }
    $LINKSDB=new linkdb(isLoggedIn() || $GLOBALS['config']['OPEN_SHAARLI']);  // Read links from database (and filter private links if used it not logged in).
    $filename=$_FILES['filetoupload']['name'];
    $filesize=$_FILES['filetoupload']['size'];
    $data=file_get_contents($_FILES['filetoupload']['tmp_name']);
    $private = (empty($_POST['private']) ? 0 : 1); // Should the links be imported as private ?
    $overwrite = !empty($_POST['overwrite']) ; // Should the imported links overwrite existing ones ?
    $import_count=0;

    // Sniff file type:
    $type='unknown';
    if (startsWith($data,'<!DOCTYPE NETSCAPE-Bookmark-file-1>')) $type='netscape'; // Netscape bookmark file (aka Firefox).

    // Then import the bookmarks.
    if ($type=='netscape')
    {
        // This is a standard Netscape-style bookmark file.
        // This format is supported by all browsers (except IE, of course), also delicious, diigo and others.
        foreach(explode('<DT>',$data) as $html) // explode is very fast
        {
            $link = array('linkdate'=>'','title'=>'','url'=>'','description'=>'','tags'=>'','private'=>0);
            $d = explode('<DD>',$html);
            if (startswith($d[0],'<A '))
            {
                $link['description'] = (isset($d[1]) ? html_entity_decode(trim($d[1]),ENT_QUOTES,'UTF-8') : '');  // Get description (optional)
                preg_match('!<A .*?>(.*?)</A>!i',$d[0],$matches); $link['title'] = (isset($matches[1]) ? trim($matches[1]) : '');  // Get title
                $link['title'] = html_entity_decode($link['title'],ENT_QUOTES,'UTF-8');
                preg_match_all('! ([A-Z_]+)=\"(.*?)"!i',$html,$matches,PREG_SET_ORDER);  // Get all other attributes
                $raw_add_date=0;
                foreach($matches as $m)
                {
                    $attr=$m[1]; $value=$m[2];
                    if ($attr=='HREF') $link['url']=html_entity_decode($value,ENT_QUOTES,'UTF-8');
                    elseif ($attr=='ADD_DATE')
                    {
                        $raw_add_date=intval($value);
                        if ($raw_add_date>30000000000) $raw_add_date/=1000;	//If larger than year 2920, then was likely stored in milliseconds instead of seconds
                    }
                    elseif ($attr=='PRIVATE') $link['private']=($value=='0'?0:1);
                    elseif ($attr=='TAGS') $link['tags']=html_entity_decode(str_replace(',',' ',$value),ENT_QUOTES,'UTF-8');
                }
                if ($link['url']!='')
                {
                    if ($private==1) $link['private']=1;
                    $dblink = $LINKSDB->getLinkFromUrl($link['url']); // See if the link is already in database.
                    if ($dblink==false)
                    {  // Link not in database, let's import it...
                       if (empty($raw_add_date)) $raw_add_date=time(); // In case of shitty bookmark file with no ADD_DATE

                       // Make sure date/time is not already used by another link.
                       // (Some bookmark files have several different links with the same ADD_DATE)
                       // We increment date by 1 second until we find a date which is not used in db.
                       // (so that links that have the same date/time are more or less kept grouped by date, but do not conflict.)
                       while (!empty($LINKSDB[date('Ymd_His',$raw_add_date)])) { $raw_add_date++; }// Yes, I know it's ugly.
                       $link['linkdate']=date('Ymd_His',$raw_add_date);
                       $LINKSDB[$link['linkdate']] = $link;
                       $import_count++;
                    }
                    else // link already present in database.
                    {
                        if ($overwrite)
                        {   // If overwrite is required, we import link data, except date/time.
                            $link['linkdate']=$dblink['linkdate'];
                            $LINKSDB[$link['linkdate']] = $link;
                            $import_count++;
                        }
                    }

                }
            }
        }
        $LINKSDB->savedb();

        echo '<script language="JavaScript">alert("File '.json_encode($filename).' ('.$filesize.' bytes) was successfully processed: '.$import_count.' links imported.");document.location=\'?\';</script>';
    }
    else
    {
        echo '<script language="JavaScript">alert("File '.json_encode($filename).' ('.$filesize.' bytes) has an unknown file format. Nothing was imported.");document.location=\'?\';</script>';
    }
}

// -----------------------------------------------------------------------------------------------
// Template for the list of links (<div id="linklist">)
// This function fills all the necessary fields in the $PAGE for the template 'linklist.html'
function buildLinkList($PAGE,$LINKSDB)
{
    // ---- Filter link database according to parameters
    $linksToDisplay=array();
    $search_type='';
    $search_crits='';
    if (isset($_GET['searchterm'])) // Fulltext search
    {
        $linksToDisplay = $LINKSDB->filterFulltext(trim($_GET['searchterm']));
        $search_crits=htmlspecialchars(trim($_GET['searchterm']));
        $search_type='fulltext';
    }
    elseif (isset($_GET['searchtags'])) // Search by tag
    {
        $linksToDisplay = $LINKSDB->filterTags(trim($_GET['searchtags']));
        $search_crits=explode(' ',trim($_GET['searchtags']));
        $search_type='tags';
    }
    elseif (isset($_SERVER['QUERY_STRING']) && preg_match('/[a-zA-Z0-9-_@]{6}(&.+?)?/',$_SERVER['QUERY_STRING'])) // Detect smallHashes in URL
    {
        $linksToDisplay = $LINKSDB->filterSmallHash(substr(trim($_SERVER["QUERY_STRING"], '/'),0,6));
        if (count($linksToDisplay)==0)
        {
            header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
            echo '<h1>404 Not found.</h1>Oh crap. The link you are trying to reach does not exist or has been deleted.';
            echo '<br>You would mind <a href="?">clicking here</a> ?';
            exit;
        }
        $search_type='permalink';
    }
    else
        $linksToDisplay = $LINKSDB;  // otherwise, display without filtering.

    // Option: Show only private links
    if (!empty($_SESSION['privateonly']))
    {
        $tmp = array();
        foreach($linksToDisplay as $linkdate=>$link)
        {
            if ($link['private']!=0) $tmp[$linkdate]=$link;
        }
        $linksToDisplay=$tmp;
    }

    // ---- Handle paging.
    /* Can someone explain to me why you get the following error when using array_keys() on an object which implements the interface ArrayAccess ???
       "Warning: array_keys() expects parameter 1 to be array, object given in ... "
       If my class implements ArrayAccess, why won't array_keys() accept it ?  ( $keys=array_keys($linksToDisplay); )
    */
    $keys=array(); foreach($linksToDisplay as $key=>$value) { $keys[]=$key; } // Stupid and ugly. Thanks php.

    // If there is only a single link, we change on-the-fly the title of the page.
    if (count($linksToDisplay)==1) $GLOBALS['pagetitle'] = $linksToDisplay[$keys[0]]['title'].' - '.$GLOBALS['title'];

    // Select articles according to paging.
    $pagecount = ceil(count($keys)/$_SESSION['LINKS_PER_PAGE']);
    $pagecount = ($pagecount==0 ? 1 : $pagecount);
    $page=( empty($_GET['page']) ? 1 : intval($_GET['page']));
    $page = ( $page<1 ? 1 : $page );
    $page = ( $page>$pagecount ? $pagecount : $page );
    $i = ($page-1)*$_SESSION['LINKS_PER_PAGE']; // Start index.
    $end = $i+$_SESSION['LINKS_PER_PAGE'];
    $linkDisp=array(); // Links to display
    while ($i<$end && $i<count($keys))
    {
        $link = $linksToDisplay[$keys[$i]];
        $link['description']=nl2br(keepMultipleSpaces(text2clickable(htmlspecialchars($link['description']))));
        $title=$link['title'];
        $classLi =  $i%2!=0 ? '' : 'publicLinkHightLight';
        $link['class'] = ($link['private']==0 ? $classLi : 'private');
        $link['localdate']=linkdate2locale($link['linkdate']);
        $taglist = explode(' ',$link['tags']);
        uasort($taglist, 'strcasecmp');
        $link['taglist']=$taglist;
        $linkDisp[$keys[$i]] = $link;
        $i++;
    }

    // Compute paging navigation
    $searchterm= ( empty($_GET['searchterm']) ? '' : '&searchterm='.$_GET['searchterm'] );
    $searchtags= ( empty($_GET['searchtags']) ? '' : '&searchtags='.$_GET['searchtags'] );
    $paging='';
    $previous_page_url=''; if ($i!=count($keys)) $previous_page_url='?page='.($page+1).$searchterm.$searchtags;
    $next_page_url='';if ($page>1) $next_page_url='?page='.($page-1).$searchterm.$searchtags;

    $token = ''; if (isLoggedIn()) $token=getToken();

    // Fill all template fields.
    $PAGE->assign('linkcount',count($LINKSDB));
    $PAGE->assign('previous_page_url',$previous_page_url);
    $PAGE->assign('next_page_url',$next_page_url);
    $PAGE->assign('page_current',$page);
    $PAGE->assign('page_max',$pagecount);
    $PAGE->assign('result_count',count($linksToDisplay));
    $PAGE->assign('search_type',$search_type);
    $PAGE->assign('search_crits',$search_crits);
    $PAGE->assign('redirector',empty($GLOBALS['redirector']) ? '' : $GLOBALS['redirector']); // optional redirector URL
    $PAGE->assign('token',$token);
    $PAGE->assign('links',$linkDisp);
    return;
}

// Compute the thumbnail for a link.
//
// with a link to the original URL.
// Understands various services (youtube.com...)
// Input: $url = url for which the thumbnail must be found.
//        $href = if provided, this URL will be followed instead of $url
// Returns an associative array with thumbnail attributes (src,href,width,height,style,alt)
// Some of them may be missing.
// Return an empty array if no thumbnail available.
function computeThumbnail($url,$href=false)
{
    if (!$GLOBALS['config']['ENABLE_THUMBNAILS']) return array();
    if ($href==false) $href=$url;

    // For most hosts, the URL of the thumbnail can be easily deduced from the URL of the link.
    // (eg. http://www.youtube.com/watch?v=spVypYk4kto --->  http://img.youtube.com/vi/spVypYk4kto/default.jpg )
    //                                     ^^^^^^^^^^^                                 ^^^^^^^^^^^
    $domain = parse_url($url,PHP_URL_HOST);
    if ($domain=='youtube.com' || $domain=='www.youtube.com')
    {
        parse_str(parse_url($url,PHP_URL_QUERY), $params); // Extract video ID and get thumbnail
        if (!empty($params['v'])) return array('src'=>'http://img.youtube.com/vi/'.$params['v'].'/default.jpg',
                                               'href'=>$href,'width'=>'120','height'=>'90','alt'=>'YouTube thumbnail');
    }
    if ($domain=='youtu.be') // Youtube short links
    {
        $path = parse_url($url,PHP_URL_PATH);
        return array('src'=>'http://img.youtube.com/vi'.$path.'/default.jpg',
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
        if (startsWith($path,'/r/')) return array('src'=>'http://i.imgur.com/'.basename($path).'s.jpg',
                                                  'href'=>$href,'width'=>'90','height'=>'90','alt'=>'imgur.com thumbnail');
        if (startsWith($path,'/gallery/')) return array('src'=>'http://i.imgur.com'.substr($path,8).'s.jpg',
                                                        'href'=>$href,'width'=>'90','height'=>'90','alt'=>'imgur.com thumbnail');

        if (substr_count($path,'/')==1) return array('src'=>'http://i.imgur.com/'.substr($path,1).'s.jpg',
                                                     'href'=>$href,'width'=>'90','height'=>'90','alt'=>'imgur.com thumbnail');
    }
    if ($domain=='i.imgur.com')
    {
        $pi = pathinfo(parse_url($url,PHP_URL_PATH));
        if (!empty($pi['filename'])) return array('src'=>'http://i.imgur.com/'.$pi['filename'].'s.jpg',
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

    if (!$GLOBALS['config']['ENABLE_LOCALCACHE']) return array(); // If local cache is disabled, no thumbnails for services which require the use a local cache.

    if ($domain=='flickr.com' || endsWith($domain,'.flickr.com')
        || $domain=='vimeo.com'
        || $domain=='ted.com' || endsWith($domain,'.ted.com')
        || $domain=='xkcd.com' || endsWith($domain,'.xkcd.com')
    )
    {
        if ($domain=='vimeo.com')
        {   // Make sure this vimeo url points to a video (/xxx... where xxx is numeric)
            $path = parse_url($url,PHP_URL_PATH);
            if (!preg_match('!/\d+.+?!',$path)) return array(); // This is not a single video URL.
        }
        if ($domain=='xkcd.com' || endsWith($domain,'.xkcd.com'))
        {   // Make sure this url points to a single comic (/xxx... where xxx is numeric)
            $path = parse_url($url,PHP_URL_PATH);
            if (!preg_match('!/\d+.+?!',$path)) return array();
        }
        if ($domain=='ted.com' || endsWith($domain,'.ted.com'))
        {   // Make sure this TED url points to a video (/talks/...)
            $path = parse_url($url,PHP_URL_PATH);
            if ("/talks/" !== substr($path,0,7)) return array(); // This is not a single video URL.
        }
        $sign = hash_hmac('sha256', $url, $GLOBALS['salt']); // We use the salt to sign data (it's random, secret, and specific to each installation)
        return array('src'=>indexUrl().'?do=genthumbnail&hmac='.htmlspecialchars($sign).'&url='.urlencode($url),
                     'href'=>$href,'width'=>'120','style'=>'height:auto;','alt'=>'thumbnail');
    }

    // For all other, we try to make a thumbnail of links ending with .jpg/jpeg/png/gif
    // Technically speaking, we should download ALL links and check their Content-Type to see if they are images.
    // But using the extension will do.
    $ext=strtolower(pathinfo($url,PATHINFO_EXTENSION));
    if ($ext=='jpg' || $ext=='jpeg' || $ext=='png' || $ext=='gif')
    {
        $sign = hash_hmac('sha256', $url, $GLOBALS['salt']); // We use the salt to sign data (it's random, secret, and specific to each installation)
        return array('src'=>indexUrl().'?do=genthumbnail&hmac='.htmlspecialchars($sign).'&url='.urlencode($url),
                     'href'=>$href,'width'=>'120','style'=>'height:auto;','alt'=>'thumbnail');
    }
    return array(); // No thumbnail.

}


// Returns the HTML code to display a thumbnail for a link
// with a link to the original URL.
// Understands various services (youtube.com...)
// Input: $url = url for which the thumbnail must be found.
//        $href = if provided, this URL will be followed instead of $url
// Returns '' if no thumbnail available.
function thumbnail($url,$href=false)
{
    $t = computeThumbnail($url,$href);
    if (count($t)==0) return ''; // Empty array = no thumbnail for this URL.

    $html='<a href="'.htmlspecialchars($t['href']).'"><img src="'.htmlspecialchars($t['src']).'"';
    if (!empty($t['width']))  $html.=' width="'.htmlspecialchars($t['width']).'"';
    if (!empty($t['height'])) $html.=' height="'.htmlspecialchars($t['height']).'"';
    if (!empty($t['style']))  $html.=' style="'.htmlspecialchars($t['style']).'"';
    if (!empty($t['alt']))    $html.=' alt="'.htmlspecialchars($t['alt']).'"';
    $html.='></a>';
    return $html;
}


// Returns the HTML code to display a thumbnail for a link
// for the picture wall (using lazy image loading)
// Understands various services (youtube.com...)
// Input: $url = url for which the thumbnail must be found.
//        $href = if provided, this URL will be followed instead of $url
// Returns '' if no thumbnail available.
function lazyThumbnail($url,$href=false)
{
    $t = computeThumbnail($url,$href);
    if (count($t)==0) return ''; // Empty array = no thumbnail for this URL.

    $html='<a href="'.htmlspecialchars($t['href']).'">';

    // Lazy image (only loaded by javascript when in the viewport).
    if (!empty($GLOBALS['disablejquery'])) // (except if jQuery is disabled)
        $html.='<img class="lazyimage" src="'.htmlspecialchars($t['src']).'"';
    else
        $html.='<img class="lazyimage" src="#" data-original="'.htmlspecialchars($t['src']).'"';

    if (!empty($t['width']))  $html.=' width="'.htmlspecialchars($t['width']).'"';
    if (!empty($t['height'])) $html.=' height="'.htmlspecialchars($t['height']).'"';
    if (!empty($t['style']))  $html.=' style="'.htmlspecialchars($t['style']).'"';
    if (!empty($t['alt']))    $html.=' alt="'.htmlspecialchars($t['alt']).'"';
    $html.='>';

    // No-javascript fallback.
    $html.='<noscript><img src="'.htmlspecialchars($t['src']).'"';
    if (!empty($t['width']))  $html.=' width="'.htmlspecialchars($t['width']).'"';
    if (!empty($t['height'])) $html.=' height="'.htmlspecialchars($t['height']).'"';
    if (!empty($t['style']))  $html.=' style="'.htmlspecialchars($t['style']).'"';
    if (!empty($t['alt']))    $html.=' alt="'.htmlspecialchars($t['alt']).'"';
    $html.='></noscript></a>';

    return $html;
}


// -----------------------------------------------------------------------------------------------
// Installation
// This function should NEVER be called if the file data/config.php exists.
function install()
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
        echo 'It currently points to '.session_save_path().'<br><br><a href="?">Click to try again.</a></pre>';
        die;
    }
    if (!isset($_SESSION['session_tested']))
    {   // Step 1 : Try to store data in session and reload page.
        $_SESSION['session_tested'] = 'Working';  // Try to set a variable in session.
        header('Location: '.indexUrl().'?test_session');  // Redirect to check stored data.
    }
    if (isset($_GET['test_session']))
    {   // Step 3: Sessions are ok. Remove test parameter from URL.
        header('Location: '.indexUrl());
    }


    if (!empty($_POST['setlogin']) && !empty($_POST['setpassword']))
    {
        $tz = 'UTC';
        if (!empty($_POST['continent']) && !empty($_POST['city']))
            if (isTZvalid($_POST['continent'],$_POST['city']))
                $tz = $_POST['continent'].'/'.$_POST['city'];
        $GLOBALS['timezone'] = $tz;
        // Everything is ok, let's create config file.
        $GLOBALS['login'] = $_POST['setlogin'];
        $GLOBALS['salt'] = sha1(uniqid('',true).'_'.mt_rand()); // Salt renders rainbow-tables attacks useless.
        $GLOBALS['hash'] = sha1($_POST['setpassword'].$GLOBALS['login'].$GLOBALS['salt']);
        $GLOBALS['title'] = (empty($_POST['title']) ? 'Shared links on '.htmlspecialchars(indexUrl()) : $_POST['title'] );
        writeConfig();
        echo '<script language="JavaScript">alert("Shaarli is now configured. Please enter your login/password and start shaaring your links !");document.location=\'?do=login\';</script>';
        exit;
    }

    // Display config form:
    list($timezone_form,$timezone_js) = templateTZform();
    $timezone_html=''; if ($timezone_form!='') $timezone_html='<tr><td valign="top"><b>Timezone:</b></td><td>'.$timezone_form.'</td></tr>';

    $PAGE = new pageBuilder;
    $PAGE->assign('timezone_html',$timezone_html);
    $PAGE->assign('timezone_js',$timezone_js);
    $PAGE->renderPage('install');
    exit;
}

// Generates the timezone selection form and javascript.
// Input: (optional) current timezone (can be 'UTC/UTC'). It will be pre-selected.
// Output: array(html,js)
// Example: list($htmlform,$js) = templateTZform('Europe/Paris');  // Europe/Paris pre-selected.
// Returns array('','') if server does not support timezones list. (eg. php 5.1 on free.fr)
function templateTZform($ptz=false)
{
    if (function_exists('timezone_identifiers_list')) // because of old php version (5.1) which can be found on free.fr
    {
        // Try to split the provided timezone.
        if ($ptz==false) { $l=timezone_identifiers_list(); $ptz=$l[0]; }
        $spos=strpos($ptz,'/'); $pcontinent=substr($ptz,0,$spos); $pcity=substr($ptz,$spos+1);

        // Display config form:
        $timezone_form = '';
        $timezone_js = '';
        // The list is in the forme "Europe/Paris", "America/Argentina/Buenos_Aires"...
        // We split the list in continents/cities.
        $continents = array();
        $cities = array();
        foreach(timezone_identifiers_list() as $tz)
        {
            if ($tz=='UTC') $tz='UTC/UTC';
            $spos = strpos($tz,'/');
            if ($spos!==false)
            {
                $continent=substr($tz,0,$spos); $city=substr($tz,$spos+1);
                $continents[$continent]=1;
                if (!isset($cities[$continent])) $cities[$continent]='';
                $cities[$continent].='<option value="'.$city.'"'.($pcity==$city?'selected':'').'>'.$city.'</option>';
            }
        }
        $continents_html = '';
        $continents = array_keys($continents);
        foreach($continents as $continent)
            $continents_html.='<option  value="'.$continent.'"'.($pcontinent==$continent?'selected':'').'>'.$continent.'</option>';
        $cities_html = $cities[$pcontinent];
        $timezone_form = "Continent: <select name=\"continent\" id=\"continent\" onChange=\"onChangecontinent();\">${continents_html}</select>";
        $timezone_form .= "&nbsp;&nbsp;&nbsp;&nbsp;City: <select name=\"city\" id=\"city\">${cities[$pcontinent]}</select><br />";
        $timezone_js = "<script language=\"JavaScript\">";
        $timezone_js .= "function onChangecontinent(){document.getElementById(\"city\").innerHTML = citiescontinent[document.getElementById(\"continent\").value];}";
        $timezone_js .= "var citiescontinent = ".json_encode($cities).";" ;
        $timezone_js .= "</script>" ;
        return array($timezone_form,$timezone_js);
    }
    return array('','');
}

// Tells if a timezone is valid or not.
// If not valid, returns false.
// If system does not support timezone list, returns false.
function isTZvalid($continent,$city)
{
    $tz = $continent.'/'.$city;
    if (function_exists('timezone_identifiers_list')) // because of old php version (5.1) which can be found on free.fr
    {
        if (in_array($tz, timezone_identifiers_list())) // it's a valid timezone ?
                    return true;
    }
    return false;
}
if (!function_exists('json_encode')) {
    function json_encode($data) {
        switch ($type = gettype($data)) {
            case 'NULL':
                return 'null';
            case 'boolean':
                return ($data ? 'true' : 'false');
            case 'integer':
            case 'double':
            case 'float':
                return $data;
            case 'string':
                return '"' . addslashes($data) . '"';
            case 'object':
                $data = get_object_vars($data);
            case 'array':
                $output_index_count = 0;
                $output_indexed = array();
                $output_associative = array();
                foreach ($data as $key => $value) {
                    $output_indexed[] = json_encode($value);
                    $output_associative[] = json_encode($key) . ':' . json_encode($value);
                    if ($output_index_count !== NULL && $output_index_count++ !== $key) {
                        $output_index_count = NULL;
                    }
                }
                if ($output_index_count !== NULL) {
                    return '[' . implode(',', $output_indexed) . ']';
                } else {
                    return '{' . implode(',', $output_associative) . '}';
                }
            default:
                return ''; // Not supported
        }
    }
}

// Webservices (for use with jQuery/jQueryUI)
// eg.  index.php?ws=tags&term=minecr
function processWS()
{
    if (empty($_GET['ws']) || empty($_GET['term'])) return;
    $term = $_GET['term'];
    $LINKSDB=new linkdb(isLoggedIn() || $GLOBALS['config']['OPEN_SHAARLI']);  // Read links from database (and filter private links if used it not logged in).
    header('Content-Type: application/json; charset=utf-8');

    // Search in tags (case insentitive, cumulative search)
    if ($_GET['ws']=='tags')
    {
        $tags=explode(' ',str_replace(',',' ',$term)); $last = array_pop($tags); // Get the last term ("a b c d" ==> "a b c", "d")
        $addtags=''; if ($tags) $addtags=implode(' ',$tags).' '; // We will pre-pend previous tags
        $suggested=array();
        /* To speed up things, we store list of tags in session */
        if (empty($_SESSION['tags'])) $_SESSION['tags'] = $LINKSDB->allTags();
        foreach($_SESSION['tags'] as $key=>$value)
        {
            if (startsWith($key,$last,$case=false) && !in_array($key,$tags)) $suggested[$addtags.$key.' ']=0;
        }
        echo json_encode(array_keys($suggested));
        exit;
    }

    // Search a single tag (case sentitive, single tag search)
    if ($_GET['ws']=='singletag')
    {
        /* To speed up things, we store list of tags in session */
        if (empty($_SESSION['tags'])) $_SESSION['tags'] = $LINKSDB->allTags();
        foreach($_SESSION['tags'] as $key=>$value)
        {
            if (startsWith($key,$term,$case=true)) $suggested[$key]=0;
        }
        echo json_encode(array_keys($suggested));
        exit;
    }
}

// Re-write configuration file according to globals.
// Requires some $GLOBALS to be set (login,hash,salt,title).
// If the config file cannot be saved, an error message is dislayed and the user is redirected to "Tools" menu.
// (otherwise, the function simply returns.)
function writeConfig()
{
    if (is_file($GLOBALS['config']['CONFIG_FILE']) && !isLoggedIn()) die('You are not authorized to alter config.'); // Only logged in user can alter config.
    $config='<?php $GLOBALS[\'login\']='.var_export($GLOBALS['login'],true).'; $GLOBALS[\'hash\']='.var_export($GLOBALS['hash'],true).'; $GLOBALS[\'salt\']='.var_export($GLOBALS['salt'],true).'; ';
    $config .='$GLOBALS[\'timezone\']='.var_export($GLOBALS['timezone'],true).'; date_default_timezone_set('.var_export($GLOBALS['timezone'],true).'); $GLOBALS[\'title\']='.var_export($GLOBALS['title'],true).';';
    $config .= '$GLOBALS[\'titleLink\']='.var_export($GLOBALS['titleLink'],true).'; ';
    $config .= '$GLOBALS[\'redirector\']='.var_export($GLOBALS['redirector'],true).'; ';
    $config .= '$GLOBALS[\'disablesessionprotection\']='.var_export($GLOBALS['disablesessionprotection'],true).'; ';
    $config .= '$GLOBALS[\'disablejquery\']='.var_export($GLOBALS['disablejquery'],true).'; ';
    $config .= '$GLOBALS[\'privateLinkByDefault\']='.var_export($GLOBALS['privateLinkByDefault'],true).'; ';
    $config .= ' ?>';
    if (!file_put_contents($GLOBALS['config']['CONFIG_FILE'],$config) || strcmp(file_get_contents($GLOBALS['config']['CONFIG_FILE']),$config)!=0)
    {
        echo '<script language="JavaScript">alert("Shaarli could not create the config file. Please make sure Shaarli has the right to write in the folder is it installed in.");document.location=\'?\';</script>';
        exit;
    }
}

/* Because some f*cking services like Flickr require an extra HTTP request to get the thumbnail URL,
   I have deported the thumbnail URL code generation here, otherwise this would slow down page generation.
   The following function takes the URL a link (eg. a flickr page) and return the proper thumbnail.
   This function is called by passing the url:
   http://mywebsite.com/shaarli/?do=genthumbnail&hmac=[HMAC]&url=[URL]
   [URL] is the URL of the link (eg. a flickr page)
   [HMAC] is the signature for the [URL] (so that these URL cannot be forged).
   The function below will fetch the image from the webservice and store it in the cache.
*/
function genThumbnail()
{
    // Make sure the parameters in the URL were generated by us.
    $sign = hash_hmac('sha256', $_GET['url'], $GLOBALS['salt']);
    if ($sign!=$_GET['hmac']) die('Naughty boy !');

    // Let's see if we don't already have the image for this URL in the cache.
    $thumbname=hash('sha1',$_GET['url']).'.jpg';
    if (is_file($GLOBALS['config']['CACHEDIR'].'/'.$thumbname))
    {   // We have the thumbnail, just serve it:
        header('Content-Type: image/jpeg');
        echo file_get_contents($GLOBALS['config']['CACHEDIR'].'/'.$thumbname);
        return;
    }
    // We may also serve a blank image (if service did not respond)
    $blankname=hash('sha1',$_GET['url']).'.gif';
    if (is_file($GLOBALS['config']['CACHEDIR'].'/'.$blankname))
    {
        header('Content-Type: image/gif');
        echo file_get_contents($GLOBALS['config']['CACHEDIR'].'/'.$blankname);
        return;
    }

    // Otherwise, generate the thumbnail.
    $url = $_GET['url'];
    $domain = parse_url($url,PHP_URL_HOST);

    if ($domain=='flickr.com' || endsWith($domain,'.flickr.com'))
    {
        // Crude replacement to handle new Flickr domain policy (They prefer www. now)
        $url = str_replace('http://flickr.com/','http://www.flickr.com/',$url);

        // Is this a link to an image, or to a flickr page ?
        $imageurl='';
        if (endswith(parse_url($url,PHP_URL_PATH),'.jpg'))
        {  // This is a direct link to an image. eg. http://farm1.staticflickr.com/5/5921913_ac83ed27bd_o.jpg
            preg_match('!(http://farm\d+\.staticflickr\.com/\d+/\d+_\w+_)\w.jpg!',$url,$matches);
            if (!empty($matches[1])) $imageurl=$matches[1].'m.jpg';
        }
        else // this is a flickr page (html)
        {
            list($httpstatus,$headers,$data) = getHTTP($url,20); // Get the flickr html page.
            if (strpos($httpstatus,'200 OK')!==false)
            {
                // Flickr now nicely provides the URL of the thumbnail in each flickr page.
                preg_match('!<link rel=\"image_src\" href=\"(.+?)\"!',$data,$matches);
                if (!empty($matches[1])) $imageurl=$matches[1];

                // In albums (and some other pages), the link rel="image_src" is not provided,
                // but flickr provides:
                // <meta property="og:image" content="http://farm4.staticflickr.com/3398/3239339068_25d13535ff_z.jpg" />
                if ($imageurl=='')
                {
                    preg_match('!<meta property=\"og:image\" content=\"(.+?)\"!',$data,$matches);
                    if (!empty($matches[1])) $imageurl=$matches[1];
                }
            }
        }

        if ($imageurl!='')
        {   // Let's download the image.
            list($httpstatus,$headers,$data) = getHTTP($imageurl,10); // Image is 240x120, so 10 seconds to download should be enough.
            if (strpos($httpstatus,'200 OK')!==false)
            {
                file_put_contents($GLOBALS['config']['CACHEDIR'].'/'.$thumbname,$data); // Save image to cache.
                header('Content-Type: image/jpeg');
                echo $data;
                return;
            }
        }
    }

    elseif ($domain=='vimeo.com' )
    {
        // This is more complex: we have to perform a HTTP request, then parse the result.
        // Maybe we should deport this to javascript ? Example: http://stackoverflow.com/questions/1361149/get-img-thumbnails-from-vimeo/4285098#4285098
        $vid = substr(parse_url($url,PHP_URL_PATH),1);
        list($httpstatus,$headers,$data) = getHTTP('http://vimeo.com/api/v2/video/'.htmlspecialchars($vid).'.php',5);
        if (strpos($httpstatus,'200 OK')!==false)
        {
            $t = unserialize($data);
            $imageurl = $t[0]['thumbnail_medium'];
            // Then we download the image and serve it to our client.
            list($httpstatus,$headers,$data) = getHTTP($imageurl,10);
            if (strpos($httpstatus,'200 OK')!==false)
            {
                file_put_contents($GLOBALS['config']['CACHEDIR'].'/'.$thumbname,$data); // Save image to cache.
                header('Content-Type: image/jpeg');
                echo $data;
                return;
            }
        }
    }

    elseif ($domain=='ted.com' || endsWith($domain,'.ted.com'))
    {
        // The thumbnail for TED talks is located in the <link rel="image_src" [...]> tag on that page
        // http://www.ted.com/talks/mikko_hypponen_fighting_viruses_defending_the_net.html
        // <link rel="image_src" href="http://images.ted.com/images/ted/28bced335898ba54d4441809c5b1112ffaf36781_389x292.jpg" />
        list($httpstatus,$headers,$data) = getHTTP($url,5);
        if (strpos($httpstatus,'200 OK')!==false)
        {
            // Extract the link to the thumbnail
            preg_match('!link rel="image_src" href="(http://images.ted.com/images/ted/.+_\d+x\d+\.jpg)"!',$data,$matches);
            if (!empty($matches[1]))
            {   // Let's download the image.
                $imageurl=$matches[1];
                list($httpstatus,$headers,$data) = getHTTP($imageurl,20); // No control on image size, so wait long enough.
                if (strpos($httpstatus,'200 OK')!==false)
                {
                    $filepath=$GLOBALS['config']['CACHEDIR'].'/'.$thumbname;
                    file_put_contents($filepath,$data); // Save image to cache.
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
        list($httpstatus,$headers,$data) = getHTTP($url,5);
        if (strpos($httpstatus,'200 OK')!==false)
        {
            // Extract the link to the thumbnail
            preg_match('!<img src="(http://imgs.xkcd.com/comics/.*)" title="[^s]!',$data,$matches);
            if (!empty($matches[1]))
            {   // Let's download the image.
                $imageurl=$matches[1];
                list($httpstatus,$headers,$data) = getHTTP($imageurl,20); // No control on image size, so wait long enough.
                if (strpos($httpstatus,'200 OK')!==false)
                {
                    $filepath=$GLOBALS['config']['CACHEDIR'].'/'.$thumbname;
                    file_put_contents($filepath,$data); // Save image to cache.
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
        list($httpstatus,$headers,$data) = getHTTP($url,30);  // We allow 30 seconds max to download (and downloads are limited to 4 Mb)
        if (strpos($httpstatus,'200 OK')!==false)
        {
            $filepath=$GLOBALS['config']['CACHEDIR'].'/'.$thumbname;
            file_put_contents($filepath,$data); // Save image to cache.
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
    file_put_contents($GLOBALS['config']['CACHEDIR'].'/'.$blankname,$blankgif); // Also put something in cache so that this URL is not requested twice.
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

// Invalidate caches when the database is changed or the user logs out.
// (eg. tags cache).
function invalidateCaches()
{
    unset($_SESSION['tags']);  // Purge cache attached to session.
    pageCache::purgeCache();   // Purge page cache shared by sessions.
}

if (isset($_SERVER["QUERY_STRING"]) && startswith($_SERVER["QUERY_STRING"],'do=genthumbnail')) { genThumbnail(); exit; }  // Thumbnail generation/cache does not need the link database.
if (isset($_SERVER["QUERY_STRING"]) && startswith($_SERVER["QUERY_STRING"],'do=rss')) { showRSS(); exit; }
if (isset($_SERVER["QUERY_STRING"]) && startswith($_SERVER["QUERY_STRING"],'do=atom')) { showATOM(); exit; }
if (isset($_SERVER["QUERY_STRING"]) && startswith($_SERVER["QUERY_STRING"],'do=dailyrss')) { showDailyRSS(); exit; }
if (isset($_SERVER["QUERY_STRING"]) && startswith($_SERVER["QUERY_STRING"],'do=daily')) { showDaily(); exit; }
if (isset($_SERVER["QUERY_STRING"]) && startswith($_SERVER["QUERY_STRING"],'ws=')) { processWS(); exit; } // Webservices (for jQuery/jQueryUI)
if (!isset($_SESSION['LINKS_PER_PAGE'])) $_SESSION['LINKS_PER_PAGE']=$GLOBALS['config']['LINKS_PER_PAGE'];
renderPage();
?>
