<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Shaarli\Helper\ApplicationUtils;
use Shaarli\Helper\FileUtils;
use Shaarli\Render\TemplatePage;

/**
 * Slim controller used to handle Server administration page, and actions.
 */
class ServerController extends ShaarliAdminController
{
    /** @var string Cache type - main - by default pagecache/ and tmp/ */
    protected const CACHE_MAIN = 'main';

    /** @var string Cache type - thumbnails - by default cache/ */
    protected const CACHE_THUMB = 'thumbnails';

    /**
     * GET /admin/server - Display page Server administration
     */
    public function index(Request $request, Response $response): Response
    {
        $releaseUrl = ApplicationUtils::$GITHUB_URL . '/releases/';
        if ($this->container->get('conf')->get('updates.check_updates', true)) {
            $latestVersion = 'v' . ApplicationUtils::getVersion(
                ApplicationUtils::$GIT_RAW_URL . '/release/' . ApplicationUtils::$VERSION_FILE
            );
            $releaseUrl .= 'tag/' . $latestVersion;
        } else {
            $latestVersion = t('Check disabled');
        }

        $currentVersion = ApplicationUtils::getVersion('./shaarli_version.php');
        $currentVersion = ApplicationUtils::isDevVersion($currentVersion) ? $currentVersion : 'v' . $currentVersion;
        $phpEol = new \DateTimeImmutable(ApplicationUtils::getPhpEol(PHP_VERSION));

        $permissions = array_merge(
            ApplicationUtils::checkResourcePermissions($this->container->get('conf')),
            ApplicationUtils::checkDatastoreMutex()
        );

        $this->assignView('php_version', PHP_VERSION);
        $this->assignView('php_eol', format_date($phpEol, false));
        $this->assignView('php_has_reached_eol', $phpEol < new \DateTimeImmutable());
        $this->assignView('php_extensions', ApplicationUtils::getPhpExtensionsRequirement());
        $this->assignView('permissions', $permissions);
        $this->assignView('release_url', $releaseUrl);
        $this->assignView('latest_version', $latestVersion);
        $this->assignView('current_version', $currentVersion);
        $this->assignView('thumbnails_mode', $this->container->get('conf')->get('thumbnails.mode'));
        $this->assignView('index_url', index_url($request->getServerParams()));
        $this->assignView('client_ip', client_ip_id($request->getServerParams()));
        $this->assignView('trusted_proxies', $this->container->get('conf')->get('security.trusted_proxies', []));

        $this->assignView(
            'pagetitle',
            t('Server administration') . ' - ' . $this->container->get('conf')->get('general.title', 'Shaarli')
        );

        return $this->respondWithTemplate($response, TemplatePage::SERVER);
    }

    /**
     * GET /admin/clear-cache?type={$type} - Action to trigger cache folder clearing (either main or thumbnails).
     */
    public function clearCache(Request $request, Response $response): Response
    {
        $exclude = ['.htaccess'];

        if (($request->getQueryParams()['type'] ?? null) === static::CACHE_THUMB) {
            $folders = [$this->container->get('conf')->get('resource.thumbnails_cache')];

            $this->saveWarningMessage(
                t('Thumbnails cache has been cleared.') . ' ' .
                '<a href="' . $this->container->get('basePath') . '/admin/thumbnails">' .
                    t('Please synchronize them.') .
                '</a>'
            );
        } else {
            $folders = [
                $this->container->get('conf')->get('resource.page_cache'),
                $this->container->get('conf')->get('resource.raintpl_tmp'),
            ];

            $this->saveSuccessMessage(t('Shaarli\'s cache folder has been cleared!'));
        }

        // Make sure that we don't delete root cache folder
        $folders = array_map('realpath', array_values(array_filter(array_map('trim', $folders))));
        foreach ($folders as $folder) {
            FileUtils::clearFolder($folder, false, $exclude);
        }

        return $this->redirect($response, '/admin/server');
    }
}
