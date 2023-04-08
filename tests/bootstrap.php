<?php

require_once  __DIR__ . '/../vendor/autoload.php';

use Shaarli\Tests\Utils\ReferenceSessionIdHashes;

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
require_once 'application/config/ConfigPlugin.php';
require_once 'application/bookmark/LinkUtils.php';
require_once 'application/http/UrlUtils.php';
require_once 'application/http/HttpUtils.php';
require_once 'application/Utils.php';
require_once 'application/TimeZone.php';
require_once 'tests/utils/CurlUtils.php';
require_once 'tests/utils/RainTPL.php';

// TODO: remove this after fixing UT
require_once 'tests/TestCase.php';
require_once 'tests/container/ShaarliTestContainer.php';
require_once 'tests/api/ApiUtilsTest.php';
require_once 'tests/front/controller/visitor/FrontControllerMockHelper.php';
require_once 'tests/front/controller/admin/FrontAdminControllerMockHelper.php';
require_once 'tests/updater/DummyUpdater.php';
require_once 'tests/utils/FakeApplicationUtils.php';
require_once 'tests/utils/FakeBookmarkService.php';
require_once 'tests/utils/FakeConfigManager.php';
require_once 'tests/utils/ReferenceHistory.php';
require_once 'tests/utils/ReferenceLinkDB.php';
require_once 'tests/utils/ReferenceSessionIdHashes.php';

ReferenceSessionIdHashes::genAllHashes();

if (!defined('SHAARLI_MUTEX_FILE')) {
    define('SHAARLI_MUTEX_FILE', __FILE__);
}
