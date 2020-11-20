<?php

namespace Shaarli\Legacy;

use Exception;
use RainTPL;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Shaarli\Bookmark\Bookmark;
use Shaarli\Bookmark\BookmarkArray;
use Shaarli\Bookmark\BookmarkFilter;
use Shaarli\Bookmark\BookmarkIO;
use Shaarli\Bookmark\LinkDB;
use Shaarli\Config\ConfigJson;
use Shaarli\Config\ConfigManager;
use Shaarli\Config\ConfigPhp;
use Shaarli\Exceptions\IOException;
use Shaarli\Helper\ApplicationUtils;
use Shaarli\Thumbnailer;
use Shaarli\Updater\Exception\UpdaterException;

/**
 * Class updater.
 * Used to update stuff when a new Shaarli's version is reached.
 * Update methods are ran only once, and the stored in a JSON file.
 *
 * @deprecated
 */
class LegacyUpdater
{
    /**
     * @var array Updates which are already done.
     */
    protected $doneUpdates;

    /**
     * @var LegacyLinkDB instance.
     */
    protected $linkDB;

    /**
     * @var ConfigManager $conf Configuration Manager instance.
     */
    protected $conf;

    /**
     * @var bool True if the user is logged in, false otherwise.
     */
    protected $isLoggedIn;

    /**
     * @var array $_SESSION
     */
    protected $session;

    /**
     * @var ReflectionMethod[] List of current class methods.
     */
    protected $methods;

    /**
     * Object constructor.
     *
     * @param array         $doneUpdates Updates which are already done.
     * @param LegacyLinkDB  $linkDB      LinkDB instance.
     * @param ConfigManager $conf        Configuration Manager instance.
     * @param boolean       $isLoggedIn  True if the user is logged in.
     * @param array         $session     $_SESSION (by reference)
     *
     * @throws ReflectionException
     */
    public function __construct($doneUpdates, $linkDB, $conf, $isLoggedIn, &$session = [])
    {
        $this->doneUpdates = $doneUpdates;
        $this->linkDB = $linkDB;
        $this->conf = $conf;
        $this->isLoggedIn = $isLoggedIn;
        $this->session = &$session;

        // Retrieve all update methods.
        $class = new ReflectionClass($this);
        $this->methods = $class->getMethods();
    }

    /**
     * Run all new updates.
     * Update methods have to start with 'updateMethod' and return true (on success).
     *
     * @return array An array containing ran updates.
     *
     * @throws UpdaterException If something went wrong.
     */
    public function update()
    {
        $updatesRan = [];

        // If the user isn't logged in, exit without updating.
        if ($this->isLoggedIn !== true) {
            return $updatesRan;
        }

        if ($this->methods === null) {
            throw new UpdaterException(t('Couldn\'t retrieve updater class methods.'));
        }

        foreach ($this->methods as $method) {
            // Not an update method or already done, pass.
            if (
                !startsWith($method->getName(), 'updateMethod')
                || in_array($method->getName(), $this->doneUpdates)
            ) {
                continue;
            }

            try {
                $method->setAccessible(true);
                $res = $method->invoke($this);
                // Update method must return true to be considered processed.
                if ($res === true) {
                    $updatesRan[] = $method->getName();
                }
            } catch (Exception $e) {
                throw new UpdaterException($method, $e);
            }
        }

        $this->doneUpdates = array_merge($this->doneUpdates, $updatesRan);

        return $updatesRan;
    }

    /**
     * @return array Updates methods already processed.
     */
    public function getDoneUpdates()
    {
        return $this->doneUpdates;
    }

    /**
     * Move deprecated options.php to config.php.
     *
     * Milestone 0.9 (old versioning) - shaarli/Shaarli#41:
     *    options.php is not supported anymore.
     */
    public function updateMethodMergeDeprecatedConfigFile()
    {
        if (is_file($this->conf->get('resource.data_dir') . '/options.php')) {
            include $this->conf->get('resource.data_dir') . '/options.php';

            // Load GLOBALS into config
            $allowedKeys = array_merge(ConfigPhp::$ROOT_KEYS);
            $allowedKeys[] = 'config';
            foreach ($GLOBALS as $key => $value) {
                if (in_array($key, $allowedKeys)) {
                    $this->conf->set($key, $value);
                }
            }
            $this->conf->write($this->isLoggedIn);
            unlink($this->conf->get('resource.data_dir') . '/options.php');
        }

        return true;
    }

    /**
     * Move old configuration in PHP to the new config system in JSON format.
     *
     * Will rename 'config.php' into 'config.save.php' and create 'config.json.php'.
     * It will also convert legacy setting keys to the new ones.
     */
    public function updateMethodConfigToJson()
    {
        // JSON config already exists, nothing to do.
        if ($this->conf->getConfigIO() instanceof ConfigJson) {
            return true;
        }

        $configPhp = new ConfigPhp();
        $configJson = new ConfigJson();
        $oldConfig = $configPhp->read($this->conf->getConfigFile() . '.php');
        rename($this->conf->getConfigFileExt(), $this->conf->getConfigFile() . '.save.php');
        $this->conf->setConfigIO($configJson);
        $this->conf->reload();

        $legacyMap = array_flip(ConfigPhp::$LEGACY_KEYS_MAPPING);
        foreach (ConfigPhp::$ROOT_KEYS as $key) {
            $this->conf->set($legacyMap[$key], $oldConfig[$key]);
        }

        // Set sub config keys (config and plugins)
        $subConfig = ['config', 'plugins'];
        foreach ($subConfig as $sub) {
            foreach ($oldConfig[$sub] as $key => $value) {
                if (isset($legacyMap[$sub . '.' . $key])) {
                    $configKey = $legacyMap[$sub . '.' . $key];
                } else {
                    $configKey = $sub . '.' . $key;
                }
                $this->conf->set($configKey, $value);
            }
        }

        try {
            $this->conf->write($this->isLoggedIn);
            return true;
        } catch (IOException $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    /**
     * Escape settings which have been manually escaped in every request in previous versions:
     *   - general.title
     *   - general.header_link
     *   - redirector.url
     *
     * @return bool true if the update is successful, false otherwise.
     */
    public function updateMethodEscapeUnescapedConfig()
    {
        try {
            $this->conf->set('general.title', escape($this->conf->get('general.title')));
            $this->conf->set('general.header_link', escape($this->conf->get('general.header_link')));
            $this->conf->write($this->isLoggedIn);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * Update the database to use the new ID system, which replaces linkdate primary keys.
     * Also, creation and update dates are now DateTime objects (done by LinkDB).
     *
     * Since this update is very sensitve (changing the whole database), the datastore will be
     * automatically backed up into the file datastore.<datetime>.php.
     *
     * LinkDB also adds the field 'shorturl' with the precedent format (linkdate smallhash),
     * which will be saved by this method.
     *
     * @return bool true if the update is successful, false otherwise.
     */
    public function updateMethodDatastoreIds()
    {
        $first = 'update';
        foreach ($this->linkDB as $key => $link) {
            $first = $key;
            break;
        }

        // up to date database
        if (is_int($first)) {
            return true;
        }

        $save = $this->conf->get('resource.data_dir') . '/datastore.' . date('YmdHis') . '.php';
        copy($this->conf->get('resource.datastore'), $save);

        $links = [];
        foreach ($this->linkDB as $offset => $value) {
            $links[] = $value;
            unset($this->linkDB[$offset]);
        }
        $links = array_reverse($links);
        $cpt = 0;
        foreach ($links as $l) {
            unset($l['linkdate']);
            $l['id'] = $cpt;
            $this->linkDB[$cpt++] = $l;
        }

        $this->linkDB->save($this->conf->get('resource.page_cache'));
        $this->linkDB->reorder();

        return true;
    }

    /**
     * Rename tags starting with a '-' to work with tag exclusion search.
     */
    public function updateMethodRenameDashTags()
    {
        $linklist = $this->linkDB->filterSearch();
        foreach ($linklist as $key => $link) {
            $link['tags'] = preg_replace('/(^| )\-/', '$1', $link['tags']);
            $link['tags'] = implode(' ', array_unique(BookmarkFilter::tagsStrToArray($link['tags'], true)));
            $this->linkDB[$key] = $link;
        }
        $this->linkDB->save($this->conf->get('resource.page_cache'));
        return true;
    }

    /**
     * Initialize API settings:
     *   - api.enabled: true
     *   - api.secret: generated secret
     */
    public function updateMethodApiSettings()
    {
        if ($this->conf->exists('api.secret')) {
            return true;
        }

        $this->conf->set('api.enabled', true);
        $this->conf->set(
            'api.secret',
            generate_api_secret(
                $this->conf->get('credentials.login'),
                $this->conf->get('credentials.salt')
            )
        );
        $this->conf->write($this->isLoggedIn);
        return true;
    }

    /**
     * New setting: theme name. If the default theme is used, nothing to do.
     *
     * If the user uses a custom theme, raintpl_tpl dir is updated to the parent directory,
     * and the current theme is set as default in the theme setting.
     *
     * @return bool true if the update is successful, false otherwise.
     */
    public function updateMethodDefaultTheme()
    {
        // raintpl_tpl isn't the root template directory anymore.
        // We run the update only if this folder still contains the template files.
        $tplDir = $this->conf->get('resource.raintpl_tpl');
        $tplFile = $tplDir . '/linklist.html';
        if (!file_exists($tplFile)) {
            return true;
        }

        $parent = dirname($tplDir);
        $this->conf->set('resource.raintpl_tpl', $parent);
        $this->conf->set('resource.theme', trim(str_replace($parent, '', $tplDir), '/'));
        $this->conf->write($this->isLoggedIn);

        // Dependency injection gore
        RainTPL::$tpl_dir = $tplDir;

        return true;
    }

    /**
     * Move the file to inc/user.css to data/user.css.
     *
     * Note: Due to hardcoded paths, it's not unit testable. But one line of code should be fine.
     *
     * @return bool true if the update is successful, false otherwise.
     */
    public function updateMethodMoveUserCss()
    {
        if (!is_file('inc/user.css')) {
            return true;
        }

        return rename('inc/user.css', 'data/user.css');
    }

    /**
     * * `markdown_escape` is a new setting, set to true as default.
     *
     * If the markdown plugin was already enabled, escaping is disabled to avoid
     * breaking existing entries.
     */
    public function updateMethodEscapeMarkdown()
    {
        if ($this->conf->exists('security.markdown_escape')) {
            return true;
        }

        if (in_array('markdown', $this->conf->get('general.enabled_plugins'))) {
            $this->conf->set('security.markdown_escape', false);
        } else {
            $this->conf->set('security.markdown_escape', true);
        }
        $this->conf->write($this->isLoggedIn);

        return true;
    }

    /**
     * Add 'http://' to Piwik URL the setting is set.
     *
     * @return bool true if the update is successful, false otherwise.
     */
    public function updateMethodPiwikUrl()
    {
        if (!$this->conf->exists('plugins.PIWIK_URL') || startsWith($this->conf->get('plugins.PIWIK_URL'), 'http')) {
            return true;
        }

        $this->conf->set('plugins.PIWIK_URL', 'http://' . $this->conf->get('plugins.PIWIK_URL'));
        $this->conf->write($this->isLoggedIn);

        return true;
    }

    /**
     * Use ATOM feed as default.
     */
    public function updateMethodAtomDefault()
    {
        if (!$this->conf->exists('feed.show_atom') || $this->conf->get('feed.show_atom') === true) {
            return true;
        }

        $this->conf->set('feed.show_atom', true);
        $this->conf->write($this->isLoggedIn);

        return true;
    }

    /**
     * Update updates.check_updates_branch setting.
     *
     * If the current major version digit matches the latest branch
     * major version digit, we set the branch to `latest`,
     * otherwise we'll check updates on the `stable` branch.
     *
     * No update required for the dev version.
     *
     * Note: due to hardcoded URL and lack of dependency injection, this is not unit testable.
     *
     * FIXME! This needs to be removed when we switch to first digit major version
     *        instead of the second one since the versionning process will change.
     */
    public function updateMethodCheckUpdateRemoteBranch()
    {
        if (SHAARLI_VERSION === 'dev' || $this->conf->get('updates.check_updates_branch') === 'latest') {
            return true;
        }

        // Get latest branch major version digit
        $latestVersion = ApplicationUtils::getLatestGitVersionCode(
            'https://raw.githubusercontent.com/shaarli/Shaarli/latest/shaarli_version.php',
            5
        );
        if (preg_match('/(\d+)\.\d+$/', $latestVersion, $matches) === false) {
            return false;
        }
        $latestMajor = $matches[1];

        // Get current major version digit
        preg_match('/(\d+)\.\d+$/', SHAARLI_VERSION, $matches);
        $currentMajor = $matches[1];

        if ($currentMajor === $latestMajor) {
            $branch = 'latest';
        } else {
            $branch = 'stable';
        }
        $this->conf->set('updates.check_updates_branch', $branch);
        $this->conf->write($this->isLoggedIn);
        return true;
    }

    /**
     * Reset history store file due to date format change.
     */
    public function updateMethodResetHistoryFile()
    {
        if (is_file($this->conf->get('resource.history'))) {
            unlink($this->conf->get('resource.history'));
        }
        return true;
    }

    /**
     * Save the datastore -> the link order is now applied when bookmarks are saved.
     */
    public function updateMethodReorderDatastore()
    {
        $this->linkDB->save($this->conf->get('resource.page_cache'));
        return true;
    }

    /**
     * Change privateonly session key to visibility.
     */
    public function updateMethodVisibilitySession()
    {
        if (isset($_SESSION['privateonly'])) {
            unset($_SESSION['privateonly']);
            $_SESSION['visibility'] = 'private';
        }
        return true;
    }

    /**
     * Add download size and timeout to the configuration file
     *
     * @return bool true if the update is successful, false otherwise.
     */
    public function updateMethodDownloadSizeAndTimeoutConf()
    {
        if (
            $this->conf->exists('general.download_max_size')
            && $this->conf->exists('general.download_timeout')
        ) {
            return true;
        }

        if (!$this->conf->exists('general.download_max_size')) {
            $this->conf->set('general.download_max_size', 1024 * 1024 * 4);
        }

        if (!$this->conf->exists('general.download_timeout')) {
            $this->conf->set('general.download_timeout', 30);
        }

        $this->conf->write($this->isLoggedIn);
        return true;
    }

    /**
     * * Move thumbnails management to WebThumbnailer, coming with new settings.
     */
    public function updateMethodWebThumbnailer()
    {
        if ($this->conf->exists('thumbnails.mode')) {
            return true;
        }

        $thumbnailsEnabled = extension_loaded('gd') && $this->conf->get('thumbnail.enable_thumbnails', true);
        $this->conf->set('thumbnails.mode', $thumbnailsEnabled ? Thumbnailer::MODE_ALL : Thumbnailer::MODE_NONE);
        $this->conf->set('thumbnails.width', 125);
        $this->conf->set('thumbnails.height', 90);
        $this->conf->remove('thumbnail');
        $this->conf->write(true);

        if ($thumbnailsEnabled) {
            $this->session['warnings'][] = t(
                t('You have enabled or changed thumbnails mode.') .
                '<a href="./admin/thumbnails">' . t('Please synchronize them.') . '</a>'
            );
        }

        return true;
    }

    /**
     * Set sticky = false on all bookmarks
     *
     * @return bool true if the update is successful, false otherwise.
     */
    public function updateMethodSetSticky()
    {
        foreach ($this->linkDB as $key => $link) {
            if (isset($link['sticky'])) {
                return true;
            }
            $link['sticky'] = false;
            $this->linkDB[$key] = $link;
        }

        $this->linkDB->save($this->conf->get('resource.page_cache'));

        return true;
    }

    /**
     * Remove redirector settings.
     */
    public function updateMethodRemoveRedirector()
    {
        $this->conf->remove('redirector');
        $this->conf->write(true);
        return true;
    }

    /**
     * Migrate the legacy arrays to Bookmark objects.
     * Also make a backup of the datastore.
     */
    public function updateMethodMigrateDatabase()
    {
        $save = $this->conf->get('resource.data_dir') . '/datastore.' . date('YmdHis') . '_1.php';
        if (! copy($this->conf->get('resource.datastore'), $save)) {
            die('Could not backup the datastore.');
        }

        $linksArray = new BookmarkArray();
        foreach ($this->linkDB as $key => $link) {
            $linksArray[$key] = (new Bookmark())->fromArray($link, $this->conf->get('general.tags_separator', ' '));
        }
        $linksIo = new BookmarkIO($this->conf);
        $linksIo->write($linksArray);

        return true;
    }

    /**
     * Write the `formatter` setting in config file.
     * Use markdown if the markdown plugin is enabled, the default one otherwise.
     * Also remove markdown plugin setting as it is now integrated to the core.
     */
    public function updateMethodFormatterSetting()
    {
        if (!$this->conf->exists('formatter') || $this->conf->get('formatter') === 'default') {
            $enabledPlugins = $this->conf->get('general.enabled_plugins');
            if (($pos = array_search('markdown', $enabledPlugins)) !== false) {
                $formatter = 'markdown';
                unset($enabledPlugins[$pos]);
                $this->conf->set('general.enabled_plugins', array_values($enabledPlugins));
            } else {
                $formatter = 'default';
            }
            $this->conf->set('formatter', $formatter);
            $this->conf->write(true);
        }

        return true;
    }
}
