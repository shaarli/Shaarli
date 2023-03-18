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

require_once 'inc/rain.tpl.class.php';
require_once __DIR__ . '/vendor/autoload.php';

// Shaarli library
require_once 'application/bookmark/LinkUtils.php';
require_once 'application/config/ConfigPlugin.php';
require_once 'application/http/HttpUtils.php';
require_once 'application/http/UrlUtils.php';
require_once 'application/TimeZone.php';
require_once 'application/Utils.php';

require_once __DIR__ . '/init.php';

use Katzgrau\KLogger\Logger;
use Psr\Log\LogLevel;
use Shaarli\Config\ConfigManager;
use Shaarli\Container\ContainerBuilder;
use Shaarli\Languages;
use Shaarli\Plugin\PluginManager;
use Shaarli\Security\BanManager;
use Shaarli\Security\CookieManager;
use Shaarli\Security\LoginManager;
use Shaarli\Security\SessionManager;
use Slim\App;

$conf = new ConfigManager();

// Manually override root URL for complex server configurations
define('SHAARLI_ROOT_URL', $conf->get('general.root_url', null));

// In dev mode, throw exception on any warning
if ($conf->get('dev.debug', false)) {
    // See all errors (for debugging only)
    error_reporting(-1);

    set_error_handler(function ($errno, $errstr, $errfile, $errline, array $errcontext = []) {
        // Skip PHP 8 deprecation warning with Pimple.
        if (strpos($errfile, 'src/Pimple/Container.php') !== -1 && strpos($errstr, 'ArrayAccess::') !== -1) {
            return error_log($errstr);
        }

        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    });
}

$logger = new Logger(
    is_writable($conf->get('resource.log')) ? dirname($conf->get('resource.log')) : 'php://temp',
    !$conf->get('dev.debug') ? LogLevel::INFO : LogLevel::DEBUG,
    ['filename' => basename($conf->get('resource.log'))]
);
$sessionManager = new SessionManager($_SESSION, $conf, session_save_path());
$sessionManager->initialize();
$cookieManager = new CookieManager($_COOKIE);
$banManager = new BanManager(
    $conf->get('security.trusted_proxies', []),
    $conf->get('security.ban_after'),
    $conf->get('security.ban_duration'),
    $conf->get('resource.ban_file', 'data/ipbans.php'),
    $logger
);
$loginManager = new LoginManager($conf, $sessionManager, $cookieManager, $banManager, $logger);
$loginManager->generateStaySignedInToken($_SERVER['REMOTE_ADDR']);

// Sniff browser language and set date format accordingly.
if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
    autoLocale($_SERVER['HTTP_ACCEPT_LANGUAGE']);
}

new Languages(setlocale(LC_MESSAGES, 0), $conf);

$conf->setEmpty('general.timezone', date_default_timezone_get());
$conf->setEmpty('general.title', t('Shared bookmarks on ') . escape(index_url($_SERVER)));

RainTPL::$tpl_dir = $conf->get('resource.raintpl_tpl') . '/' . $conf->get('resource.theme') . '/'; // template directory
RainTPL::$cache_dir = $conf->get('resource.raintpl_tmp'); // cache directory

date_default_timezone_set($conf->get('general.timezone', 'UTC'));

$loginManager->checkLoginState(client_ip_id($_SERVER));

$pluginManager = new PluginManager($conf);
$pluginManager->load($conf->get('general.enabled_plugins', []));

$containerBuilder = new ContainerBuilder(
    $conf,
    $sessionManager,
    $cookieManager,
    $loginManager,
    $pluginManager,
    $logger
);
$container = $containerBuilder->build();
$app = new App($container);

// Main Shaarli routes
$app->group('', function () {
    $this->get('/install', '\Shaarli\Front\Controller\Visitor\InstallController:index')->setName('displayInstall');
    $this->get('/install/session-test', '\Shaarli\Front\Controller\Visitor\InstallController:sessionTest');
    $this->post('/install', '\Shaarli\Front\Controller\Visitor\InstallController:save')->setName('saveInstall');

    /* -- PUBLIC --*/
    $this->get('/', '\Shaarli\Front\Controller\Visitor\BookmarkListController:index');
    $this->get('/shaare/{hash}', '\Shaarli\Front\Controller\Visitor\BookmarkListController:permalink');
    $this->get('/login', '\Shaarli\Front\Controller\Visitor\LoginController:index')->setName('login');
    $this->post('/login', '\Shaarli\Front\Controller\Visitor\LoginController:login')->setName('processLogin');
    $this->get('/picture-wall', '\Shaarli\Front\Controller\Visitor\PictureWallController:index');
    $this->get('/tags/cloud', '\Shaarli\Front\Controller\Visitor\TagCloudController:cloud');
    $this->get('/tags/list', '\Shaarli\Front\Controller\Visitor\TagCloudController:list');
    $this->get('/daily', '\Shaarli\Front\Controller\Visitor\DailyController:index');
    $this->get('/daily-rss', '\Shaarli\Front\Controller\Visitor\DailyController:rss')->setName('rss');
    $this->get('/feed/atom', '\Shaarli\Front\Controller\Visitor\FeedController:atom')->setName('atom');
    $this->get('/feed/rss', '\Shaarli\Front\Controller\Visitor\FeedController:rss');
    $this->get('/open-search', '\Shaarli\Front\Controller\Visitor\OpenSearchController:index');

    $this->get('/add-tag/{newTag}', '\Shaarli\Front\Controller\Visitor\TagController:addTag');
    $this->get('/remove-tag/{tag}', '\Shaarli\Front\Controller\Visitor\TagController:removeTag');
    $this->get('/links-per-page', '\Shaarli\Front\Controller\Visitor\PublicSessionFilterController:linksPerPage');
    $this->get('/untagged-only', '\Shaarli\Front\Controller\Visitor\PublicSessionFilterController:untaggedOnly');
})->add('\Shaarli\Front\ShaarliMiddleware');

$app->group('/admin', function () {
    $this->get('/logout', '\Shaarli\Front\Controller\Admin\LogoutController:index');
    $this->get('/tools', '\Shaarli\Front\Controller\Admin\ToolsController:index');
    $this->get('/password', '\Shaarli\Front\Controller\Admin\PasswordController:index');
    $this->post('/password', '\Shaarli\Front\Controller\Admin\PasswordController:change');
    $this->get('/configure', '\Shaarli\Front\Controller\Admin\ConfigureController:index');
    $this->post('/configure', '\Shaarli\Front\Controller\Admin\ConfigureController:save');
    $this->get('/tags', '\Shaarli\Front\Controller\Admin\ManageTagController:index');
    $this->post('/tags', '\Shaarli\Front\Controller\Admin\ManageTagController:save');
    $this->post('/tags/change-separator', '\Shaarli\Front\Controller\Admin\ManageTagController:changeSeparator');
    $this->get('/add-shaare', '\Shaarli\Front\Controller\Admin\ShaareAddController:addShaare');
    $this->get('/shaare', '\Shaarli\Front\Controller\Admin\ShaarePublishController:displayCreateForm');
    $this->get('/shaare/{id:[0-9]+}', '\Shaarli\Front\Controller\Admin\ShaarePublishController:displayEditForm');
    $this->get('/shaare/private/{hash}', '\Shaarli\Front\Controller\Admin\ShaareManageController:sharePrivate');
    $this->post('/shaare-batch', '\Shaarli\Front\Controller\Admin\ShaarePublishController:displayCreateBatchForms');
    $this->post('/shaare', '\Shaarli\Front\Controller\Admin\ShaarePublishController:save');
    $this->get('/shaare/delete', '\Shaarli\Front\Controller\Admin\ShaareManageController:deleteBookmark');
    $this->get('/shaare/visibility', '\Shaarli\Front\Controller\Admin\ShaareManageController:changeVisibility');
    $this->post('/shaare/update-tags', '\Shaarli\Front\Controller\Admin\ShaareManageController:addOrDeleteTags');
    $this->get('/shaare/{id:[0-9]+}/pin', '\Shaarli\Front\Controller\Admin\ShaareManageController:pinBookmark');
    $this->patch(
        '/shaare/{id:[0-9]+}/update-thumbnail',
        '\Shaarli\Front\Controller\Admin\ThumbnailsController:ajaxUpdate'
    );
    $this->get('/export', '\Shaarli\Front\Controller\Admin\ExportController:index');
    $this->post('/export', '\Shaarli\Front\Controller\Admin\ExportController:export');
    $this->get('/import', '\Shaarli\Front\Controller\Admin\ImportController:index');
    $this->post('/import', '\Shaarli\Front\Controller\Admin\ImportController:import');
    $this->get('/plugins', '\Shaarli\Front\Controller\Admin\PluginsController:index');
    $this->post('/plugins', '\Shaarli\Front\Controller\Admin\PluginsController:save');
    $this->get('/token', '\Shaarli\Front\Controller\Admin\TokenController:getToken');
    $this->get('/server', '\Shaarli\Front\Controller\Admin\ServerController:index');
    $this->get('/clear-cache', '\Shaarli\Front\Controller\Admin\ServerController:clearCache');
    $this->get('/thumbnails', '\Shaarli\Front\Controller\Admin\ThumbnailsController:index');
    $this->get('/metadata', '\Shaarli\Front\Controller\Admin\MetadataController:ajaxRetrieveTitle');
    $this->get('/visibility/{visibility}', '\Shaarli\Front\Controller\Admin\SessionFilterController:visibility');
})->add('\Shaarli\Front\ShaarliAdminMiddleware');

$app->group('/plugin', function () use ($pluginManager) {
    foreach ($pluginManager->getRegisteredRoutes() as $pluginName => $routes) {
        $this->group('/' . $pluginName, function () use ($routes) {
            foreach ($routes as $route) {
                $this->{strtolower($route['method'])}('/' . ltrim($route['route'], '/'), $route['callable']);
            }
        });
    }
})->add('\Shaarli\Front\ShaarliMiddleware');

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

try {
    $response = $app->run(true);
    $app->respond($response);
} catch (Throwable $e) {
    die(nl2br(
        'An unexpected error happened, and the error template could not be displayed.' . PHP_EOL . PHP_EOL .
        exception2text($e)
    ));
}
