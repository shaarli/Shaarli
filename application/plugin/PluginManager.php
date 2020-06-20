<?php
namespace Shaarli\Plugin;

use Shaarli\Config\ConfigManager;
use Shaarli\Plugin\Exception\PluginFileNotFoundException;

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
    private $loadedPlugins = array();

    /**
     * @var ConfigManager Configuration Manager instance.
     */
    protected $conf;

    /**
     * @var array List of plugin errors.
     */
    protected $errors;

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
        $this->errors = array();
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
    public function executeHooks($hook, &$data, $params = array())
    {
        if (!empty($params['target'])) {
            $data['_PAGE_'] = $params['target'];
        }

        if (isset($params['loggedin'])) {
            $data['_LOGGEDIN_'] = $params['loggedin'];
        }

        foreach ($this->loadedPlugins as $plugin) {
            $hookFunction = $this->buildHookName($hook, $plugin);

            if (function_exists($hookFunction)) {
                $data = call_user_func($hookFunction, $data, $this->conf);
            }
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
        $metaData = array();
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
                $params = array();
            }
            $metaData[$plugin]['parameters'] = array();
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
     * Return the list of encountered errors.
     *
     * @return array List of errors (empty array if none exists).
     */
    public function getErrors()
    {
        return $this->errors;
    }
}
