<?php

$rootPath = __DIR__ . '/../';
require_once  $rootPath . 'vendor/autoload.php';

use Shaarli\Tests\Utils\ReferenceSessionIdHashes;

const SHAARLI_VERSION = 'dev';
$conf = new \Shaarli\Config\ConfigManager('tests/utils/config/configJson');
new \Shaarli\Languages('en', $conf);

// is_iterable is only compatible with PHP 7.1+
if (!function_exists('is_iterable')) {
    function is_iterable($var)
    {
        return is_array($var) || $var instanceof \Traversable;
    }
}

// raw functions
require_once $rootPath . 'application/config/ConfigPlugin.php';
require_once $rootPath . 'application/bookmark/LinkUtils.php';
require_once $rootPath . 'application/http/UrlUtils.php';
require_once $rootPath . 'application/http/HttpUtils.php';
require_once $rootPath . 'application/Utils.php';
require_once $rootPath . 'application/TimeZone.php';
require_once $rootPath . 'tests/utils/CurlUtils.php';
require_once $rootPath . 'tests/utils/RainTPL.php';


// TODO: remove this after fixing UT
require_once $rootPath . 'tests/TestCase.php';
require_once $rootPath . 'tests/api/ApiUtilsTest.php';
require_once $rootPath . 'tests/front/controller/visitor/FrontControllerMockHelper.php';
require_once $rootPath . 'tests/front/controller/admin/FrontAdminControllerMockHelper.php';
require_once $rootPath . 'tests/updater/DummyUpdater.php';
require_once $rootPath . 'tests/utils/FakeApplicationUtils.php';
require_once $rootPath . 'tests/utils/FakeBookmarkService.php';
require_once $rootPath . 'tests/utils/FakeConfigManager.php';
require_once $rootPath . 'tests/utils/ReferenceHistory.php';
require_once $rootPath . 'tests/utils/ReferenceLinkDB.php';
require_once $rootPath . 'tests/utils/ReferenceSessionIdHashes.php';

ReferenceSessionIdHashes::genAllHashes();

if (!defined('SHAARLI_MUTEX_FILE')) {
    define('SHAARLI_MUTEX_FILE', __FILE__);
}
