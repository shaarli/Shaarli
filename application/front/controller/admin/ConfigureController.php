<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Shaarli\Languages;
use Shaarli\Render\TemplatePage;
use Shaarli\Render\ThemeUtils;
use Shaarli\Thumbnailer;
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
        $this->assignView('title', $this->container->get('conf')->get('general.title', 'Shaarli'));
        $this->assignView('theme', $this->container->get('conf')->get('resource.theme'));
        $this->assignView(
            'theme_available',
            ThemeUtils::getThemes($this->container->get('conf')->get('resource.raintpl_tpl'))
        );
        $this->assignView('formatter_available', ['default', 'markdown', 'markdownExtra']);
        list($continents, $cities) = generateTimeZoneData(
            timezone_identifiers_list(),
            $this->container->get('conf')->get('general.timezone')
        );
        $this->assignView('continents', $continents);
        $this->assignView('cities', $cities);
        $this->assignView('retrieve_description', $this->container->get('conf')
            ->get('general.retrieve_description', false));
        $this->assignView('private_links_default', $this->container->get('conf')
            ->get('privacy.default_private_links', false));
        $this->assignView(
            'session_protection_disabled',
            $this->container->get('conf')->get('security.session_protection_disabled', false)
        );
        $this->assignView('enable_rss_permalinks', $this->container->get('conf')->get('feed.rss_permalinks', false));
        $this->assignView('enable_update_check', $this->container->get('conf')->get('updates.check_updates', true));
        $this->assignView('hide_public_links', $this->container->get('conf')->get('privacy.hide_public_links', false));
        $this->assignView('api_enabled', $this->container->get('conf')->get('api.enabled', true));
        $this->assignView('api_secret', $this->container->get('conf')->get('api.secret'));
        $this->assignView('languages', Languages::getAvailableLanguages());
        $this->assignView('gd_enabled', extension_loaded('gd'));
        $this->assignView('thumbnails_mode', $this->container->get('conf')
            ->get('thumbnails.mode', Thumbnailer::MODE_NONE));
        $this->assignView(
            'pagetitle',
            t('Configure') . ' - ' . $this->container->get('conf')->get('general.title', 'Shaarli')
        );

        return $this->respondWithTemplate($response, TemplatePage::CONFIGURE);
    }

    /**
     * POST /admin/configure - Update Shaarli's configuration
     */
    public function save(Request $request, Response $response): Response
    {
        $this->checkToken($request);

        $continent = $request->getParsedBody()['continent'] ?? null;
        $city = $request->getParsedBody()['city'] ?? null;
        $tz = 'UTC';
        if (null !== $continent && null !== $city && isTimeZoneValid($continent, $city)) {
            $tz = $continent . '/' . $city;
        }

        $this->container->get('conf')->set('general.timezone', $tz);
        $this->container->get('conf')->set('general.title', escape($request->getParsedBody()['title'] ?? null));
        $this->container->get('conf')
            ->set('general.header_link', escape($request->getParsedBody()['titleLink'] ?? null));
        $this->container->get('conf')->set(
            'general.retrieve_description',
            !empty($request->getParsedBody()['retrieveDescription'] ?? null)
        );
        $this->container->get('conf')->set('resource.theme', escape($request->getParsedBody()['theme'] ?? null));
        $this->container->get('conf')->set(
            'security.session_protection_disabled',
            !empty($request->getParsedBody()['disablesessionprotection'] ?? null)
        );
        $this->container->get('conf')->set(
            'privacy.default_private_links',
            !empty($request->getParsedBody()['privateLinkByDefault'] ?? null)
        );
        $this->container->get('conf')->set(
            'feed.rss_permalinks',
            !empty($request->getParsedBody()['enableRssPermalinks'] ?? null)
        );
        $this->container->get('conf')
            ->set('updates.check_updates', !empty($request->getParsedBody()['updateCheck'] ?? null));
        $this->container->get('conf')->set(
            'privacy.hide_public_links',
            !empty($request->getParsedBody()['hidePublicLinks'] ?? null)
        );
        $this->container->get('conf')->set('api.enabled', !empty($request->getParsedBody()['enableApi'] ?? null));
        $this->container->get('conf')->set('api.secret', escape($request->getParsedBody()['apiSecret'] ?? null));
        $this->container->get('conf')->set('formatter', escape($request->getParsedBody()['formatter'] ?? null));

        if (!empty($request->getParsedBody()['language'] ?? null)) {
            $this->container->get('conf')
                ->set('translation.language', escape($request->getParsedBody()['language'] ?? null));
        }

        $thumbnailsMode = extension_loaded('gd') ?
            $request->getParsedBody()['enableThumbnails'] ?? null : Thumbnailer::MODE_NONE;
        if (
            $thumbnailsMode !== Thumbnailer::MODE_NONE
            && $thumbnailsMode !== $this->container->get('conf')->get('thumbnails.mode', Thumbnailer::MODE_NONE)
        ) {
            $this->saveWarningMessage(
                t('You have enabled or changed thumbnails mode.') .
                '<a href="' . $this->container->get('basePath') . '/admin/thumbnails">' .
                    t('Please synchronize them.') .
                '</a>'
            );
        }
        $this->container->get('conf')->set('thumbnails.mode', $thumbnailsMode);

        try {
            $this->container->get('conf')->write($this->container->get('loginManager')->isLoggedIn());
            $this->container->get('history')->updateSettings();
            $this->container->get('pageCacheManager')->invalidateCaches();
        } catch (Throwable $e) {
            $this->assignView('message', t('Error while writing config file after configuration update.'));

            if ($this->container->get('conf')->get('dev.debug', false)) {
                $this->assignView('stacktrace', $e->getMessage() . PHP_EOL . $e->getTraceAsString());
            }

            return $this->respondWithTemplate($response, TemplatePage::ERROR);
        }

        $this->saveSuccessMessage(t('Configuration was saved.'));

        return $this->redirect($response, '/admin/configure');
    }
}
