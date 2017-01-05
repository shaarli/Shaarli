<?php

/**
 * Class ConfigJson (ConfigIO implementation)
 *
 * Handle Shaarli's JSON configuration file.
 */
class ConfigJson implements ConfigIO
{
    /**
     * @inheritdoc
     */
    public function read($filepath)
    {
        if (! is_readable($filepath)) {
            return array();
        }
        $data = file_get_contents($filepath);
        $data = str_replace(self::getPhpHeaders(), '', $data);
        $data = str_replace(self::getPhpSuffix(), '', $data);
        $data = json_decode($data, true);
        if ($data === null) {
            $error = json_last_error();
            throw new Exception('An error occurred while parsing JSON file: error code #'. $error);
        }
        return $data;
    }

    /**
     * @inheritdoc
     */
    public function write($filepath, $conf)
    {
        // JSON_PRETTY_PRINT is available from PHP 5.4.
        $print = defined('JSON_PRETTY_PRINT') ? JSON_PRETTY_PRINT : 0;
        $data = self::getPhpHeaders() . json_encode($conf, $print) . self::getPhpSuffix();
        if (!file_put_contents($filepath, $data)) {
            throw new IOException(
                $filepath,
                'Shaarli could not create the config file.
                Please make sure Shaarli has the right to write in the folder is it installed in.'
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function getExtension()
    {
        return '.json.php';
    }

    /**
     * The JSON data is wrapped in a PHP file for security purpose.
     * This way, even if the file is accessible, credentials and configuration won't be exposed.
     *
     * Note: this isn't a static field because concatenation isn't supported in field declaration before PHP 5.6.
     *
     * @return string PHP start tag and comment tag.
     */
    public static function getPhpHeaders()
    {
        return '<?php /*'. PHP_EOL;
    }

    /**
     * Get PHP comment closing tags.
     *
     * Static method for consistency with getPhpHeaders.
     *
     * @return string PHP comment closing.
     */
    public static function getPhpSuffix()
    {
        return PHP_EOL . '*/ ?>';
    }
}
