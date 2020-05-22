<?php

require_once 'vendor/autoload.php';

$conf = new \Shaarli\Config\ConfigManager('tests/utils/config/configJson');
new \Shaarli\Languages('en', $conf);

// is_iterable is only compatible with PHP 7.1+
if (!function_exists('is_iterable')) {
    function is_iterable($var)
    {
        return is_array($var) || $var instanceof \Traversable;
    }
}

// TODO: remove this after fixing UT
require_once 'application/bookmark/LinkUtils.php';
require_once 'application/Utils.php';
require_once 'application/http/UrlUtils.php';
require_once 'application/http/HttpUtils.php';
require_once 'tests/utils/ReferenceLinkDB.php';
require_once 'tests/utils/ReferenceHistory.php';
require_once 'tests/utils/FakeBookmarkService.php';
require_once 'tests/container/ShaarliTestContainer.php';
require_once 'tests/front/controller/visitor/FrontControllerMockHelper.php';
require_once 'tests/front/controller/admin/FrontAdminControllerMockHelper.php';
