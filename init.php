<?php

require_once __DIR__ . '/vendor/autoload.php';

use Shaarli\Helper\ApplicationUtils;
use Shaarli\Security\SessionManager;

// Set 'UTC' as the default timezone if it is not defined in php.ini
// See http://php.net/manual/en/datetime.configuration.php#ini.date.timezone
if (date_default_timezone_get() == '') {
    date_default_timezone_set('UTC');
}

// High execution time in case of problematic imports/exports.
ini_set('max_input_time', '60');

// Try to set max upload file size and read
ini_set('memory_limit', '128M');
ini_set('post_max_size', '16M');
ini_set('upload_max_filesize', '16M');

// See all error except warnings
error_reporting(E_ALL & ~E_WARNING & ~E_DEPRECATED);

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

// Ensure the PHP version is supported
try {
    ApplicationUtils::checkPHPVersion('7.1', PHP_VERSION);
} catch (Exception $exc) {
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
// Use cookies to store session.
ini_set('session.use_cookies', 1);
// Force cookies for session (phpsessionID forbidden in URL).
ini_set('session.use_only_cookies', 1);
// Prevent PHP form using sessionID in URL if cookies are disabled.
ini_set('session.use_trans_sid', false);

define('SHAARLI_VERSION', ApplicationUtils::getVersion(__DIR__ .'/'. ApplicationUtils::$VERSION_FILE));
define('SHAARLI_MUTEX_FILE', __FILE__);

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

// LC_MESSAGES isn't defined without php-intl, in this case use LC_COLLATE locale instead.
if (! defined('LC_MESSAGES')) {
    define('LC_MESSAGES', LC_COLLATE);
}

// Prevent caching on client side or proxy: (yes, it's ugly)
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
