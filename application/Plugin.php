<?php

/**
 * Class PluginManager
 *
 * Use to manage, load and execute plugins.
 *
 * Using Singleton design pattern.
 */
class PluginManager
{
    /**
     * PluginManager singleton instance.
     * @var PluginManager $instance
     */
    private static $instance;

    /**
     * List of authorized plugins from configuration file.
     * @var array $authorizedPlugins
     */
    private $authorizedPlugins;

    /**
     * List of loaded plugins.
     * @var array $loadedPlugins
     */
    private $loadedPlugins = array();

    /**
     * Plugins subdirectory.
     * @var string $PLUGINS_PATH
     */
    public static $PLUGINS_PATH = 'plugins';

    /**
     * Private constructor: new instances not allowed.
     */
    private function __construct()
    {
    }

    /**
     * Cloning isn't allowed either.
     *
     * @return void
     */
    private function __clone()
    {
    }

    /**
     * Return existing instance of PluginManager, or create it.
     *
     * @return PluginManager instance.
     */
    public static function getInstance()
    {
        if (!(self::$instance instanceof self)) {
            self::$instance = new self();
        }

        return self::$instance;
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
            }
            catch (PluginFileNotFoundException $e) {
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
                $data = call_user_func($hookFunction, $data);
            }
        }
    }

    /**
     * Load a single plugin from its files.
     * Add them in $loadedPlugins if successful.
     *
     * @param string $dir        plugin's directory.
     * @param string $pluginName plugin's name.
     *
     * @return void
     * @throws PluginFileNotFoundException - plugin files not found.
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

        include_once $pluginFilePath;

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
}

/**
 * Class PluginFileNotFoundException
 *
 * Raise when plugin files can't be found.
 */
class PluginFileNotFoundException extends Exception
{
    /**
     * Construct exception with plugin name.
     * Generate message.
     *
     * @param string $pluginName name of the plugin not found
     */
    public function __construct($pluginName)
    {
        $this->message = 'Plugin "'. $pluginName .'" files not found.';
    }
}