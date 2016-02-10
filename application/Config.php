<?php
/**
 * Functions related to configuration management.
 */

/**
 * Re-write configuration file according to given array.
 * Requires mandatory fields listed in $MANDATORY_FIELDS.
 *
 * @param array $config     contains all configuration fields.
 * @param bool  $isLoggedIn true if user is logged in.
 *
 * @return void
 *
 * @throws MissingFieldConfigException: a mandatory field has not been provided in $config.
 * @throws UnauthorizedConfigException: user is not authorize to change configuration.
 * @throws Exception: an error occured while writing the new config file.
 */
function writeConfig($config, $isLoggedIn)
{
    // These fields are required in configuration.
    $MANDATORY_FIELDS = array(
        'login', 'hash', 'salt', 'timezone', 'title', 'titleLink',
        'redirector', 'disablesessionprotection', 'privateLinkByDefault'
    );

    if (!isset($config['config']['CONFIG_FILE'])) {
        throw new MissingFieldConfigException('CONFIG_FILE');
    }

    // Only logged in user can alter config.
    if (is_file($config['config']['CONFIG_FILE']) && !$isLoggedIn) {
        throw new UnauthorizedConfigException();
    }

    // Check that all mandatory fields are provided in $config.
    foreach ($MANDATORY_FIELDS as $field) {
        if (!isset($config[$field])) {
            throw new MissingFieldConfigException($field);
        }
    }

    $configStr = '<?php '. PHP_EOL;
    $configStr .= '$GLOBALS[\'login\'] = '.var_export($config['login'], true).';'. PHP_EOL;
    $configStr .= '$GLOBALS[\'hash\'] = '.var_export($config['hash'], true).';'. PHP_EOL;
    $configStr .= '$GLOBALS[\'salt\'] = '.var_export($config['salt'], true).'; '. PHP_EOL;
    $configStr .= '$GLOBALS[\'timezone\'] = '.var_export($config['timezone'], true).';'. PHP_EOL;
    $configStr .= 'date_default_timezone_set('.var_export($config['timezone'], true).');'. PHP_EOL;
    $configStr .= '$GLOBALS[\'title\'] = '.var_export($config['title'], true).';'. PHP_EOL;
    $configStr .= '$GLOBALS[\'titleLink\'] = '.var_export($config['titleLink'], true).'; '. PHP_EOL;
    $configStr .= '$GLOBALS[\'redirector\'] = '.var_export($config['redirector'], true).'; '. PHP_EOL;
    $configStr .= '$GLOBALS[\'disablesessionprotection\'] = '.var_export($config['disablesessionprotection'], true).'; '. PHP_EOL;
    $configStr .= '$GLOBALS[\'privateLinkByDefault\'] = '.var_export($config['privateLinkByDefault'], true).'; '. PHP_EOL;

    // Store all $config['config']
    foreach ($config['config'] as $key => $value) {
        $configStr .= '$GLOBALS[\'config\'][\''. $key .'\'] = '.var_export($config['config'][$key], true).';'. PHP_EOL;
    }

    if (isset($config['plugins'])) {
        foreach ($config['plugins'] as $key => $value) {
            $configStr .= '$GLOBALS[\'plugins\'][\''. $key .'\'] = '.var_export($config['plugins'][$key], true).';'. PHP_EOL;
        }
    }

    if (!file_put_contents($config['config']['CONFIG_FILE'], $configStr)
        || strcmp(file_get_contents($config['config']['CONFIG_FILE']), $configStr) != 0
    ) {
        throw new Exception(
            'Shaarli could not create the config file.
            Please make sure Shaarli has the right to write in the folder is it installed in.'
        );
    }
}

/**
 * Write plugin settings in its specific config.php file if it exists.
 *
 * @param string $plugin   Name of the plugin.
 * @param array  $settings The settings to save:
 *                              - key: setting name.
 *                              - value: setting value.
 *
 * @return boolean true if the settings are saved, false otherwise.
 *
 * @throws Exception File write failed.
 */
function write_plugin_config($plugin, $settings)
{
    $settingsFile = PluginManager::$PLUGINS_PATH . '/' . $plugin . '/config.php';
    if (! is_file($settingsFile) || ! is_writable($settingsFile)) {
        return false;
    }

    $configStr = '<?php '. PHP_EOL;
    foreach ($settings as $key => $value) {
        $configStr .= '$GLOBALS[\'plugins\'][\''. $key .'\'] = '. var_export($value, true) .';'. PHP_EOL;
    }

    if (!file_put_contents($settingsFile, $configStr)
        || strcmp(file_get_contents($settingsFile), $configStr) != 0
    ) {
        throw new Exception(
            'Shaarli couldn\'t write the plugin settings.
            Please make sure Shaarli has the right to write in the folder is it installed in.'
        );
    }
    return true;
}

/**
 * Process plugin administration form data and save it in an array.
 *
 * @param array $formData Data sent by the plugin admin form.
 *
 * @return array New list of enabled plugin, ordered.
 *
 * @throws PluginConfigOrderException Plugins can't be sorted because their order is invalid.
 */
function save_plugin_config($formData)
{
    // Make sure there are no duplicates in orders.
    if (!validate_plugin_order($formData)) {
        throw new PluginConfigOrderException();
    }

    $plugins = array();
    $newEnabledPlugins = array();
    foreach ($formData as $key => $data) {
        if (startsWith($key, 'order')) {
            continue;
        }

        // If there is no order, it means a disabled plugin has been enabled.
        if (isset($formData['order_' . $key])) {
            $plugins[(int) $formData['order_' . $key]] = $key;
        }
        else {
            $newEnabledPlugins[] = $key;
        }
    }

    // New enabled plugins will be added at the end of order.
    $plugins = array_merge($plugins, $newEnabledPlugins);

    // Sort plugins by order.
    if (!ksort($plugins)) {
        throw new PluginConfigOrderException();
    }

    $finalPlugins = array();
    // Make plugins order continuous.
    foreach ($plugins as $plugin) {
        $finalPlugins[] = $plugin;
    }

    return $finalPlugins;
}

/**
 * Validate plugin array submitted.
 * Will fail if there is duplicate orders value.
 *
 * @param array $formData Data from submitted form.
 *
 * @return bool true if ok, false otherwise.
 */
function validate_plugin_order($formData)
{
    $orders = array();
    foreach ($formData as $key => $value) {
        // No duplicate order allowed.
        if (in_array($value, $orders)) {
            return false;
        }

        if (startsWith($key, 'order')) {
            $orders[] = $value;
        }
    }

    return true;
}

/**
 * Affect plugin parameters values into plugins array.
 *
 * @param mixed $plugins Plugins array ($plugins[<plugin_name>]['parameters']['param_name'] = <value>.
 * @param mixed $config  Plugins configuration.
 *
 * @return mixed Updated $plugins array.
 */
function load_plugin_parameter_values($plugins, $config)
{
    $out = $plugins;
    foreach ($plugins as $name => $plugin) {
        if (empty($plugin['parameters'])) {
            continue;
        }

        foreach ($plugin['parameters'] as $key => $param) {
            if (!empty($config[$key])) {
                $out[$name]['parameters'][$key] = $config[$key];
            }
        }
    }

    return $out;
}

/**
 * Milestone 0.9 - shaarli/Shaarli#41: options.php is not supported anymore.
 * ==> if user is loggedIn, merge its content with config.php, then delete options.php.
 *
 * @param array $config     contains all configuration fields.
 * @param bool  $isLoggedIn true if user is logged in.
 *
 * @return void
 */
function mergeDeprecatedConfig($config, $isLoggedIn)
{
    $config_file = $config['config']['CONFIG_FILE'];

    if (is_file($config['config']['DATADIR'].'/options.php') && $isLoggedIn) {
        include $config['config']['DATADIR'].'/options.php';

        // Load GLOBALS into config
        foreach ($GLOBALS as $key => $value) {
            $config[$key] = $value;
        }
        $config['config']['CONFIG_FILE'] = $config_file;
        writeConfig($config, $isLoggedIn);

        unlink($config['config']['DATADIR'].'/options.php');
    }
}

/**
 * Exception used if a mandatory field is missing in given configuration.
 */
class MissingFieldConfigException extends Exception
{
    public $field;

    /**
     * Construct exception.
     *
     * @param string $field field name missing.
     */
    public function __construct($field)
    {
        $this->field = $field;
        $this->message = 'Configuration value is required for '. $this->field;
    }
}

/**
 * Exception used if an unauthorized attempt to edit configuration has been made.
 */
class UnauthorizedConfigException extends Exception
{
    /**
     * Construct exception.
     */
    public function __construct()
    {
        $this->message = 'You are not authorized to alter config.';
    }
}

/**
 * Exception used if an error occur while saving plugin configuration.
 */
class PluginConfigOrderException extends Exception
{
    /**
     * Construct exception.
     */
    public function __construct()
    {
        $this->message = 'An error occurred while trying to save plugins loading order.';
    }
}
