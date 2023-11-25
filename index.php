<?php

/**
 * Shaarli - The personal, minimalist, super fast, database-free, bookmarking service.
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
use Shaarli\Api\Controllers as ApiControllers;
use Shaarli\BasePathMiddleware;
use Shaarli\Config\ConfigManager;
use Shaarli\Container\ContainerBuilder;
use Shaarli\Front\Controller;
use Shaarli\Front\ShaarliErrorHandler;
use Shaarli\Languages;
use Shaarli\Plugin\PluginManager;
use Shaarli\Security\BanManager;
use Shaarli\Security\CookieManager;
use Shaarli\Security\LoginManager;
use Shaarli\Security\SessionManager;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;

$conf = new ConfigManager();

// Manually override root URL for complex server configurations
define('SHAARLI_ROOT_URL', $conf->get('general.root_url', null));

$displayErrorDetails = $conf->get('dev.debug', false);

// In dev mode, throw exception on any warning
if ($displayErrorDetails) {
    // See all errors (for debugging only)
    error_reporting(-1);

    set_error_handler(function ($errno, $errstr, $errfile, $errline, array $errcontext = []) {
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

new Languages(get_locale(LC_MESSAGES), $conf);

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
AppFactory::setContainer($container);
$app = AppFactory::create();
$app->add(new BasePathMiddleware($app));

// Main Shaarli routes
$app->group('', function (RouteCollectorProxy $group) {
    $group->get('/install', Controller\Visitor\InstallController::class . ':index')->setName('displayInstall');
    $group->get('/install/session-test', Controller\Visitor\InstallController::class . ':sessionTest');
    $group->post('/install', Controller\Visitor\InstallController::class . ':save')->setName('saveInstall');

    /* -- PUBLIC --*/
    $group->get('/', Controller\Visitor\BookmarkListController::class . ':index');
    $group->get('/shaare/{hash}', Controller\Visitor\BookmarkListController::class . ':permalink');
    $group->get('/login', Controller\Visitor\LoginController::class . ':index')->setName('login');
    $group->post('/login', Controller\Visitor\LoginController::class . ':login')->setName('processLogin');
    $group->get('/picture-wall', Controller\Visitor\PictureWallController::class . ':index');
    $group->get('/tags/cloud', Controller\Visitor\TagCloudController::class . ':cloud');
    $group->get('/tags/list', Controller\Visitor\TagCloudController::class . ':list');
    $group->get('/daily', Controller\Visitor\DailyController::class . ':index');
    $group->get('/daily-rss', Controller\Visitor\DailyController::class . ':rss')->setName('rss');
    $group->get('/feed/atom', Controller\Visitor\FeedController::class . ':atom')->setName('atom');
    $group->get('/feed/rss', Controller\Visitor\FeedController::class . ':rss');
    $group->get('/open-search', Controller\Visitor\OpenSearchController::class . ':index');

    $group->get('/add-tag/{newTag}', Controller\Visitor\TagController::class . ':addTag');
    $group->get('/remove-tag/{tag}', Controller\Visitor\TagController::class . ':removeTag');
    $group->get('/links-per-page', Controller\Visitor\PublicSessionFilterController::class . ':linksPerPage');
    $group->get('/untagged-only', Controller\Visitor\PublicSessionFilterController::class . ':untaggedOnly');
})->add(\Shaarli\Front\ShaarliMiddleware::class);

$app->group('/admin', function (RouteCollectorProxy $group) {
    $group->get('/logout', Controller\Admin\LogoutController::class . ':index');
    $group->get('/tools', Controller\Admin\ToolsController::class . ':index');
    $group->get('/password', Controller\Admin\PasswordController::class . ':index');
    $group->post('/password', Controller\Admin\PasswordController::class . ':change');
    $group->get('/configure', Controller\Admin\ConfigureController::class . ':index');
    $group->post('/configure', Controller\Admin\ConfigureController::class . ':save');
    $group->get('/tags', Controller\Admin\ManageTagController::class . ':index');
    $group->post('/tags', Controller\Admin\ManageTagController::class . ':save');
    $group->post('/tags/change-separator', Controller\Admin\ManageTagController::class . ':changeSeparator');
    $group->get('/add-shaare', Controller\Admin\ShaareAddController::class . ':addShaare');
    $group->get('/shaare', Controller\Admin\ShaarePublishController::class . ':displayCreateForm');
    $group->get('/shaare/{id:[0-9]+}', Controller\Admin\ShaarePublishController::class . ':displayEditForm');
    $group->get('/shaare/private/{hash}', Controller\Admin\ShaareManageController::class . ':sharePrivate');
    $group->post('/shaare-batch', Controller\Admin\ShaarePublishController::class . ':displayCreateBatchForms');
    $group->post('/shaare', Controller\Admin\ShaarePublishController::class . ':save');
    $group->get('/shaare/delete', Controller\Admin\ShaareManageController::class . ':deleteBookmark');
    $group->get('/shaare/visibility', Controller\Admin\ShaareManageController::class . ':changeVisibility');
    $group->post('/shaare/update-tags', Controller\Admin\ShaareManageController::class . ':addOrDeleteTags');
    $group->get('/shaare/{id:[0-9]+}/pin', Controller\Admin\ShaareManageController::class . ':pinBookmark');
    $group->patch(
        '/shaare/{id:[0-9]+}/update-thumbnail',
        Controller\Admin\ThumbnailsController::class . ':ajaxUpdate'
    );
    $group->get('/export', Controller\Admin\ExportController::class . ':index');
    $group->post('/export', Controller\Admin\ExportController::class . ':export');
    $group->get('/import', Controller\Admin\ImportController::class . ':index');
    $group->post('/import', Controller\Admin\ImportController::class . ':import');
    $group->get('/plugins', Controller\Admin\PluginsController::class . ':index');
    $group->post('/plugins', Controller\Admin\PluginsController::class . ':save');
    $group->get('/token', Controller\Admin\TokenController::class . ':getToken');
    $group->get('/server', Controller\Admin\ServerController::class . ':index');
    $group->get('/clear-cache', Controller\Admin\ServerController::class . ':clearCache');
    $group->get('/thumbnails', Controller\Admin\ThumbnailsController::class . ':index');
    $group->get('/metadata', Controller\Admin\MetadataController::class . ':ajaxRetrieveTitle');
    $group->get('/visibility/{visibility}', Controller\Admin\SessionFilterController::class . ':visibility');
})->add(\Shaarli\Front\ShaarliAdminMiddleware::class);

$app->group('/plugin', function (RouteCollectorProxy $group) use ($pluginManager) {
    foreach ($pluginManager->getRegisteredRoutes() as $pluginName => $routes) {
        $group->group('/' . $pluginName, function (RouteCollectorProxy $subgroup) use ($routes) {
            foreach ($routes as $route) {
                $subgroup->{strtolower($route['method'])}('/' . ltrim($route['route'], '/'), $route['callable']);
            }
        });
    }
})->add(\Shaarli\Front\ShaarliMiddleware::class);

// REST API routes
$app->group('/api/v1', function (RouteCollectorProxy $group) {
    $group->get('/info', ApiControllers\Info::class . ':getInfo')->setName('getInfo');
    $group->get('/links', ApiControllers\Links::class . ':getLinks')->setName('getLinks');
    $group->get('/links/{id:[\d]+}', ApiControllers\Links::class . ':getLink')->setName('getLink');
    $group->post('/links', ApiControllers\Links::class . ':postLink')->setName('postLink');
    $group->put('/links/{id:[\d]+}', ApiControllers\Links::class . ':putLink')->setName('putLink');
    $group->delete('/links/{id:[\d]+}', ApiControllers\Links::class . ':deleteLink')->setName('deleteLink');

    $group->get('/tags', ApiControllers\Tags::class . ':getTags')->setName('getTags');
    $group->get('/tags/{tagName:[\w]+}', ApiControllers\Tags::class . ':getTag')->setName('getTag');
    $group->put('/tags/{tagName:[\w]+}', ApiControllers\Tags::class . ':putTag')->setName('putTag');
    $group->delete('/tags/{tagName:[\w]+}', ApiControllers\Tags::class . ':deleteTag')->setName('deleteTag');

    $group->get('/history', ApiControllers\HistoryController::class . ':getHistory')->setName('getHistory');
})->add(\Shaarli\Api\ApiMiddleware::class);

$errorMiddleware = $app->addErrorMiddleware($displayErrorDetails, true, true);
$errorMiddleware->setDefaultErrorHandler(
    new ShaarliErrorHandler($app, $logger, $container)
);


try {
    $app->run();
} catch (Throwable $e) {
    die(nl2br(
        'An unexpected error happened, and the error template could not be displayed.' . PHP_EOL . PHP_EOL .
        exception2text($e)
    ));
}
