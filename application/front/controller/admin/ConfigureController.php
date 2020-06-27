<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin;

use Shaarli\Languages;
use Shaarli\Render\ThemeUtils;
use Shaarli\Thumbnailer;
use Slim\Http\Request;
use Slim\Http\Response;
use Throwable;

/**
 * Class ConfigureController
 *
 * Slim controller used to handle Shaarli configuration page (display + save new config).
 */
class ConfigureController extends ShaarliAdminController
{
    /**
     * GET /admin/configure - Displays the configuration page
     */
    public function index(Request $request, Response $response): Response
    {
        $this->assignView('title', $this->container->conf->get('general.title', 'Shaarli'));
        $this->assignView('theme', $this->container->conf->get('resource.theme'));
        $this->assignView(
            'theme_available',
            ThemeUtils::getThemes($this->container->conf->get('resource.raintpl_tpl'))
        );
        $this->assignView('formatter_available', ['default', 'markdown']);
        list($continents, $cities) = generateTimeZoneData(
            timezone_identifiers_list(),
            $this->container->conf->get('general.timezone')
        );
        $this->assignView('continents', $continents);
        $this->assignView('cities', $cities);
        $this->assignView('retrieve_description', $this->container->conf->get('general.retrieve_description', false));
        $this->assignView('private_links_default', $this->container->conf->get('privacy.default_private_links', false));
        $this->assignView(
            'session_protection_disabled',
            $this->container->conf->get('security.session_protection_disabled', false)
        );
        $this->assignView('enable_rss_permalinks', $this->container->conf->get('feed.rss_permalinks', false));
        $this->assignView('enable_update_check', $this->container->conf->get('updates.check_updates', true));
        $this->assignView('hide_public_links', $this->container->conf->get('privacy.hide_public_links', false));
        $this->assignView('api_enabled', $this->container->conf->get('api.enabled', true));
        $this->assignView('api_secret', $this->container->conf->get('api.secret'));
        $this->assignView('languages', Languages::getAvailableLanguages());
        $this->assignView('gd_enabled', extension_loaded('gd'));
        $this->assignView('thumbnails_mode', $this->container->conf->get('thumbnails.mode', Thumbnailer::MODE_NONE));
        $this->assignView('pagetitle', t('Configure') .' - '. $this->container->conf->get('general.title', 'Shaarli'));

        return $response->write($this->render('configure'));
    }

    /**
     * POST /admin/configure - Update Shaarli's configuration
     */
    public function save(Request $request, Response $response): Response
    {
        $this->checkToken($request);

        $continent = $request->getParam('continent');
        $city = $request->getParam('city');
        $tz = 'UTC';
        if (null !== $continent && null !== $city && isTimeZoneValid($continent, $city)) {
            $tz = $continent . '/' . $city;
        }

        $this->container->conf->set('general.timezone', $tz);
        $this->container->conf->set('general.title', escape($request->getParam('title')));
        $this->container->conf->set('general.header_link', escape($request->getParam('titleLink')));
        $this->container->conf->set('general.retrieve_description', !empty($request->getParam('retrieveDescription')));
        $this->container->conf->set('resource.theme', escape($request->getParam('theme')));
        $this->container->conf->set(
            'security.session_protection_disabled',
            !empty($request->getParam('disablesessionprotection'))
        );
        $this->container->conf->set(
            'privacy.default_private_links',
            !empty($request->getParam('privateLinkByDefault'))
        );
        $this->container->conf->set('feed.rss_permalinks', !empty($request->getParam('enableRssPermalinks')));
        $this->container->conf->set('updates.check_updates', !empty($request->getParam('updateCheck')));
        $this->container->conf->set('privacy.hide_public_links', !empty($request->getParam('hidePublicLinks')));
        $this->container->conf->set('api.enabled', !empty($request->getParam('enableApi')));
        $this->container->conf->set('api.secret', escape($request->getParam('apiSecret')));
        $this->container->conf->set('formatter', escape($request->getParam('formatter')));

        if (!empty($request->getParam('language'))) {
            $this->container->conf->set('translation.language', escape($request->getParam('language')));
        }

        $thumbnailsMode = extension_loaded('gd') ? $request->getParam('enableThumbnails') : Thumbnailer::MODE_NONE;
        if ($thumbnailsMode !== Thumbnailer::MODE_NONE
            && $thumbnailsMode !== $this->container->conf->get('thumbnails.mode', Thumbnailer::MODE_NONE)
        ) {
            $this->saveWarningMessage(t(
                'You have enabled or changed thumbnails mode. '
                .'<a href="'. $this->container->basePath .'/admin/thumbnails">Please synchronize them</a>.'
            ));
        }
        $this->container->conf->set('thumbnails.mode', $thumbnailsMode);

        try {
            $this->container->conf->write($this->container->loginManager->isLoggedIn());
            $this->container->history->updateSettings();
            $this->container->pageCacheManager->invalidateCaches();
        } catch (Throwable $e) {
            // TODO: translation + stacktrace
            $this->saveErrorMessage('ERROR while writing config file after configuration update.');
        }

        $this->saveSuccessMessage(t('Configuration was saved.'));

        return $this->redirect($response, '/admin/configure');
    }
}
