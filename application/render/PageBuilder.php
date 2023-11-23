<?php

namespace Shaarli\Render;

use Exception;
use Psr\Log\LoggerInterface;
use RainTPL;
use Shaarli\Bookmark\BookmarkServiceInterface;
use Shaarli\Config\ConfigManager;
use Shaarli\Helper\ApplicationUtils;
use Shaarli\Security\SessionManager;
use Shaarli\Thumbnailer;

/**
 * This class is in charge of building the final page.
 * (This is basically a wrapper around RainTPL which pre-fills some fields.)
 * $p = new PageBuilder();
 * $p->assign('myfield','myvalue');
 * $p->renderPage('mytemplate');
 */
class PageBuilder
{
    /**
     * @var RainTPL RainTPL instance.
     */
    private $tpl;

    /**
     * @var ConfigManager $conf Configuration Manager instance.
     */
    protected $conf;

    /**
     * @var array $_SESSION
     */
    protected $session;

    /** @var LoggerInterface */
    protected $logger;

    /**
     * @var BookmarkServiceInterface $bookmarkService instance.
     */
    protected $bookmarkService;

    /**
     * @var null|string XSRF token
     */
    protected $token;

    /**
     * @var bool $isLoggedIn Whether the user is logged in
     */
    protected $isLoggedIn = false;

    /**
     * PageBuilder constructor.
     * $tpl is initialized at false for lazy loading.
     *
     * @param ConfigManager $conf Configuration Manager instance (reference).
     * @param array $session $_SESSION array
     * @param LoggerInterface $logger
     * @param null $linkDB instance.
     * @param null $token Session token
     * @param bool $isLoggedIn
     */
    public function __construct(
        ConfigManager &$conf,
        array $session,
        LoggerInterface $logger,
        $linkDB = null,
        $token = null,
        $isLoggedIn = false
    ) {
        $this->tpl = false;
        $this->conf = $conf;
        $this->session = $session;
        $this->logger = $logger;
        $this->bookmarkService = $linkDB;
        $this->token = $token;
        $this->isLoggedIn = $isLoggedIn;
    }

    /**
     * Reset current state of template rendering.
     * Mostly useful for error handling. We remove everything, and display the error template.
     */
    public function reset(): void
    {
        $this->tpl = false;
    }

    /**
     * Initialize all default tpl tags.
     */
    private function initialize()
    {
        $this->tpl = new RainTPL();

        try {
            $version = ApplicationUtils::checkUpdate(
                SHAARLI_VERSION,
                $this->conf->get('resource.update_check'),
                $this->conf->get('updates.check_updates_interval'),
                $this->conf->get('updates.check_updates'),
                $this->isLoggedIn
            );
            $this->tpl->assign('newVersion', escape($version));
            $this->tpl->assign('versionError', '');
        } catch (Exception $exc) {
            $this->logger->error(format_log('Error: ' . $exc->getMessage(), client_ip_id($_SERVER)));
            $this->tpl->assign('newVersion', '');
            $this->tpl->assign('versionError', escape($exc->getMessage()));
        }

        $this->tpl->assign('is_logged_in', $this->isLoggedIn);
        $this->tpl->assign('feedurl', escape(index_url($_SERVER)));
        $searchcrits = ''; // Search criteria
        if (!empty($_GET['searchtags'])) {
            $searchcrits .= '&searchtags=' . urlencode($_GET['searchtags']);
        }
        if (!empty($_GET['searchterm'])) {
            $searchcrits .= '&searchterm=' . urlencode($_GET['searchterm']);
        }
        $this->tpl->assign('searchcrits', $searchcrits);
        $this->tpl->assign('source', index_url($_SERVER));
        $this->tpl->assign('version', SHAARLI_VERSION);
        $this->tpl->assign(
            'version_hash',
            ApplicationUtils::getVersionHash(SHAARLI_VERSION, $this->conf->get('credentials.salt'))
        );
        $this->tpl->assign('index_url', index_url($_SERVER));
        $visibility = !empty($_SESSION['visibility']) ? $_SESSION['visibility'] : '';
        $this->tpl->assign('visibility', $visibility);
        $this->tpl->assign('untaggedonly', !empty($_SESSION['untaggedonly']));
        $this->tpl->assign('pagetitle', $this->conf->get('general.title', 'Shaarli'));
        if ($this->conf->exists('general.header_link')) {
            $this->tpl->assign('titleLink', $this->conf->get('general.header_link'));
        }
        $this->tpl->assign('shaarlititle', $this->conf->get('general.title', 'Shaarli'));
        $this->tpl->assign('openshaarli', $this->conf->get('security.open_shaarli', false));
        $this->tpl->assign('showatom', $this->conf->get('feed.show_atom', true));
        $this->tpl->assign('feed_type', $this->conf->get('feed.show_atom', true) !== false ? 'atom' : 'rss');
        $this->tpl->assign('hide_timestamps', $this->conf->get('privacy.hide_timestamps', false));
        $this->tpl->assign('token', $this->token);

        $this->tpl->assign('language', $this->conf->get('translation.language'));

        if ($this->bookmarkService !== null) {
            $this->tpl->assign('tags', escape($this->bookmarkService->bookmarksCountPerTag()));
        }

        $this->tpl->assign(
            'thumbnails_enabled',
            $this->conf->get('thumbnails.mode', Thumbnailer::MODE_NONE) !== Thumbnailer::MODE_NONE
        );
        $this->tpl->assign('thumbnails_width', $this->conf->get('thumbnails.width'));
        $this->tpl->assign('thumbnails_height', $this->conf->get('thumbnails.height'));

        $this->tpl->assign('formatter', $this->conf->get('formatter', 'default'));

        $this->tpl->assign('links_per_page', $this->session['LINKS_PER_PAGE'] ?? 20);
        $this->tpl->assign('tags_separator', $this->conf->get('general.tags_separator', ' '));

        // To be removed with a proper theme configuration.
        $this->tpl->assign('conf', $this->conf);
    }

    /**
     * Affect variable after controller processing.
     * Used for alert messages.
     */
    protected function finalize(string $basePath): void
    {
        // TODO: use the SessionManager
        $messageKeys = [
            SessionManager::KEY_SUCCESS_MESSAGES,
            SessionManager::KEY_WARNING_MESSAGES,
            SessionManager::KEY_ERROR_MESSAGES
        ];
        foreach ($messageKeys as $messageKey) {
            if (!empty($_SESSION[$messageKey])) {
                $this->tpl->assign('global_' . $messageKey, $_SESSION[$messageKey]);
                unset($_SESSION[$messageKey]);
            }
        }

        $rootPath = preg_replace('#/index\.php$#', '', $basePath);
        $this->assign('base_path', $basePath);
        $this->assign('root_path', $rootPath);
        $this->assign(
            'asset_path',
            $rootPath . '/' .
            rtrim($this->conf->get('resource.raintpl_tpl', 'tpl'), '/') . '/' .
            $this->conf->get('resource.theme', 'default')
        );
    }

    /**
     * The following assign() method is basically the same as RainTPL (except lazy loading)
     *
     * @param string $placeholder Template placeholder.
     * @param mixed  $value       Value to assign.
     */
    public function assign($placeholder, $value)
    {
        if ($this->tpl === false) {
            $this->initialize();
        }
        $this->tpl->assign($placeholder, $value);
    }

    /**
     * Assign an array of data to the template builder.
     *
     * @param array $data Data to assign.
     *
     * @return false if invalid data.
     */
    public function assignAll($data)
    {
        if ($this->tpl === false) {
            $this->initialize();
        }

        if (empty($data) || !is_array($data)) {
            return false;
        }

        foreach ($data as $key => $value) {
            $this->assign($key, $value);
        }
        return true;
    }

    /**
     * Render a specific page as string (using a template file).
     * e.g. $pb->render('picwall');
     *
     * @param string $page Template filename (without extension).
     *
     * @return string Processed template content
     */
    public function render(string $page, string $basePath): string
    {
        if ($this->tpl === false) {
            $this->initialize();
        }

        $this->finalize($basePath);

        return $this->tpl->draw($page, true);
    }
}
