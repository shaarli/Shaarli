<?php

namespace Shaarli\Plugin;

use Shaarli\Bookmark\Bookmark;
use Shaarli\Config\ConfigManager;
use Shaarli\Plugin\Exception\PluginFileNotFoundException;
use Shaarli\Plugin\Exception\PluginInvalidRouteException;

/**
 * Class PluginManager
 *
 * Use to manage, load and execute plugins.
 */
class PluginManager
{
    /**
     * List of authorized plugins from configuration file.
     *
     * @var array $authorizedPlugins
     */
    private $authorizedPlugins = [];

    /**
     * List of loaded plugins.
     *
     * @var array $loadedPlugins
     */
    private $loadedPlugins = [];

    /** @var array List of registered routes. Contains keys:
     *               - `method`: HTTP method, GET/POST/PUT/PATCH/DELETE
     *               - `route` (path): without prefix, e.g. `/up/{variable}`
     *                 It will be later prefixed by `/plugin/<plugin name>/`.
     *               - `callable` string, function name or FQN class's method, e.g. `demo_plugin_custom_controller`.
     */
    protected $registeredRoutes = [];

    /**
     * @var ConfigManager Configuration Manager instance.
     */
    protected $conf;

    /**
     * @var array List of plugin errors.
     */
    protected $errors;

    /** @var callable[]|null Preloaded list of hook function for filterSearchEntry() */
    protected $filterSearchEntryHooks = null;

    /**
     * Plugins subdirectory.
     *
     * @var string $PLUGINS_PATH
     */
    public static $PLUGINS_PATH = 'plugins';

    /**
     * Plugins meta files extension.
     *
     * @var string $META_EXT
     */
    public static $META_EXT = 'meta';

    /**
     * Constructor.
     *
     * @param ConfigManager $conf Configuration Manager instance.
     */
    public function __construct(&$conf)
    {
        $this->conf = $conf;
        $this->errors = [];
    }

    /**
     * Load plugins listed in $authorizedPlugins.
     *
     * @param array $authorizedPlugins Names of plugin authorized to be loaded.
     *
     * @return void
     */
    public function load($authorizedPlugins)
    {
        $this->authorizedPlugins = $authorizedPlugins;

        $dirs = glob(self::$PLUGINS_PATH . '/*', GLOB_ONLYDIR);
        $dirnames = array_map('basename', $dirs);
        foreach ($this->authorizedPlugins as $plugin) {
            $index = array_search($plugin, $dirnames);

            // plugin authorized, but its folder isn't listed
            if ($index === false) {
                continue;
            }

            try {
                $this->loadPlugin($dirs[$index], $plugin);
            } catch (PluginFileNotFoundException $e) {
                error_log($e->getMessage());
            } catch (\Throwable $e) {
                $error = $plugin . t(' [plugin incompatibility]: ') . $e->getMessage();
                $this->errors = array_unique(array_merge($this->errors, [$error]));
            }
        }
    }

    /**
     * Execute all plugins registered hook.
     *
     * @param string $hook   name of the hook to trigger.
     * @param array  $data   list of data to manipulate passed by reference.
     * @param array  $params additional parameters such as page target.
     *
     * @return void
     */
    public function executeHooks($hook, &$data, $params = [])
    {
        $metadataParameters = [
            'target' => '_PAGE_',
            'loggedin' => '_LOGGEDIN_',
            'basePath' => '_BASE_PATH_',
            'rootPath' => '_ROOT_PATH_',
            'bookmarkService' => '_BOOKMARK_SERVICE_',
        ];

        foreach ($metadataParameters as $parameter => $metaKey) {
            if (array_key_exists($parameter, $params)) {
                $data[$metaKey] = $params[$parameter];
            }
        }

        foreach ($this->loadedPlugins as $plugin) {
            $hookFunction = $this->buildHookName($hook, $plugin);

            if (function_exists($hookFunction)) {
                try {
                    $data = call_user_func($hookFunction, $data, $this->conf);
                } catch (\Throwable $e) {
                    $error = $plugin . t(' [plugin incompatibility]: ') . $e->getMessage();
                    $this->errors = array_unique(array_merge($this->errors, [$error]));
                }
            }
        }

        foreach ($metadataParameters as $metaKey) {
            unset($data[$metaKey]);
        }
    }

    /**
     * Load a single plugin from its files.
     * Call the init function if it exists, and collect errors.
     * Add them in $loadedPlugins if successful.
     *
     * @param string $dir        plugin's directory.
     * @param string $pluginName plugin's name.
     *
     * @return void
     * @throws \Shaarli\Plugin\Exception\PluginFileNotFoundException - plugin files not found.
     */
    private function loadPlugin($dir, $pluginName)
    {
        if (!is_dir($dir)) {
            throw new PluginFileNotFoundException($pluginName);
        }

        $pluginFilePath = $dir . '/' . $pluginName . '.php';
        if (!is_file($pluginFilePath)) {
            throw new PluginFileNotFoundException($pluginName);
        }

        $conf = $this->conf;
        include_once $pluginFilePath;

        $initFunction = $pluginName . '_init';
        if (function_exists($initFunction)) {
            $errors = call_user_func($initFunction, $this->conf);
            if (!empty($errors)) {
                $this->errors = array_merge($this->errors, $errors);
            }
        }

        $registerRouteFunction = $pluginName . '_register_routes';
        $routes = null;
        if (function_exists($registerRouteFunction)) {
            $routes = call_user_func($registerRouteFunction);
        }

        if ($routes !== null) {
            foreach ($routes as $route) {
                if (static::validateRouteRegistration($route)) {
                    $this->registeredRoutes[$pluginName][] = $route;
                } else {
                    throw new PluginInvalidRouteException($pluginName);
                }
            }
        }

        $this->loadedPlugins[] = $pluginName;
    }

    /**
     * Construct normalize hook name for a specific plugin.
     *
     * Format:
     *      hook_<plugin_name>_<hook_name>
     *
     * @param string $hook       hook name.
     * @param string $pluginName plugin name.
     *
     * @return string - plugin's hook name.
     */
    public function buildHookName($hook, $pluginName)
    {
        return 'hook_' . $pluginName . '_' . $hook;
    }

    /**
     * Retrieve plugins metadata from *.meta (INI) files into an array.
     * Metadata contains:
     *   - plugin description [description]
     *   - parameters split with ';' [parameters]
     *
     * Respects plugins order from settings.
     *
     * @return array plugins metadata.
     */
    public function getPluginsMeta()
    {
        $metaData = [];
        $dirs = glob(self::$PLUGINS_PATH . '/*', GLOB_ONLYDIR | GLOB_MARK);

        // Browse all plugin directories.
        foreach ($dirs as $pluginDir) {
            $plugin = basename($pluginDir);
            $metaFile = $pluginDir . $plugin . '.' . self::$META_EXT;
            if (!is_file($metaFile) || !is_readable($metaFile)) {
                continue;
            }

            $metaData[$plugin] = parse_ini_file($metaFile);
            $metaData[$plugin]['order'] = array_search($plugin, $this->authorizedPlugins);

            if (isset($metaData[$plugin]['description'])) {
                $metaData[$plugin]['description'] = t($metaData[$plugin]['description']);
            }
            // Read parameters and format them into an array.
            if (isset($metaData[$plugin]['parameters'])) {
                $params = explode(';', $metaData[$plugin]['parameters']);
            } else {
                $params = [];
            }
            $metaData[$plugin]['parameters'] = [];
            foreach ($params as $param) {
                if (empty($param)) {
                    continue;
                }

                $metaData[$plugin]['parameters'][$param]['value'] = '';
                // Optional parameter description in parameter.PARAM_NAME=
                if (isset($metaData[$plugin]['parameter.' . $param])) {
                    $metaData[$plugin]['parameters'][$param]['desc'] = t($metaData[$plugin]['parameter.' . $param]);
                }
            }
        }

        return $metaData;
    }

    /**
     * @return array List of registered custom routes by plugins.
     */
    public function getRegisteredRoutes(): array
    {
        return $this->registeredRoutes;
    }

    /**
     * @return array List of registered filter_search_entry hooks
     */
    public function getFilterSearchEntryHooks(): ?array
    {
        return $this->filterSearchEntryHooks;
    }

    /**
     * Return the list of encountered errors.
     *
     * @return array List of errors (empty array if none exists).
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Apply additional filter on every search result of BookmarkFilter calling plugins hooks.
     *
     * @param Bookmark $bookmark To check.
     * @param array    $context  Additional info about search context, depends on the search source.
     *
     * @return bool True if the result must be kept in search results, false otherwise.
     */
    public function filterSearchEntry(Bookmark $bookmark, array $context): bool
    {
        if ($this->filterSearchEntryHooks === null) {
            $this->loadFilterSearchEntryHooks();
        }

        if ($this->filterSearchEntryHooks === []) {
            return true;
        }

        foreach ($this->filterSearchEntryHooks as $filterSearchEntryHook) {
            if ($filterSearchEntryHook($bookmark, $context) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * filterSearchEntry() method will be called for every search result,
     * so for performances we preload existing functions to invoke them directly.
     */
    protected function loadFilterSearchEntryHooks(): void
    {
        $this->filterSearchEntryHooks = [];

        foreach ($this->loadedPlugins as $plugin) {
            $hookFunction = $this->buildHookName('filter_search_entry', $plugin);

            if (function_exists($hookFunction)) {
                $this->filterSearchEntryHooks[] = $hookFunction;
            }
        }
    }

    /**
     * Checks whether provided input is valid to register a new route.
     * It must contain keys `method`, `route`, `callable` (all strings).
     *
     * We do not check the format because Slim routes support regexes.
     *
     * @param string[] $input
     *
     * @return bool
     */
    protected static function validateRouteRegistration(array $input): bool
    {
        if (
            !array_key_exists('method', $input)
            || !in_array(strtoupper($input['method']), ['GET', 'PUT', 'PATCH', 'POST', 'DELETE'])
        ) {
            return false;
        }

        if (!array_key_exists('callable', $input)) {
            return false;
        }

        return true;
    }
}
