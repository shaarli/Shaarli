<?php

/**
 * Class WallabagInstance.
 */
class WallabagInstance
{
    /**
     * @var array Static reference to differrent WB API versions.
     *          - key: version ID, must match plugin settings.
     *          - value: version name.
     */
    private static $wallabagVersions = array(
        1 => '1.x',
        2 => '2.x',
    );

    /**
     * @var array Static reference to WB endpoint according to the API version.
     *          - key: version name.
     *          - value: endpoint.
     */
    private static $wallabagEndpoints = array(
        '1.x' => '?plainurl=',
        '2.x' => 'bookmarklet?url=',
    );

    /**
     * @var string Wallabag user instance URL.
     */
    private $instanceUrl;

    /**
     * @var string Wallabag user instance API version.
     */
    private $apiVersion;

    public function __construct($instance, $version)
    {
        if ($this->isVersionAllowed($version)) {
            $this->apiVersion = self::$wallabagVersions[$version];
        } else {
            // Default API version: 1.x.
            $this->apiVersion = self::$wallabagVersions[1];
        }

        $this->instanceUrl = add_trailing_slash($instance);
    }

    /**
     * Build the Wallabag URL to reach from instance URL and API version endpoint.
     *
     * @return string wallabag url.
     */
    public function getWallabagUrl()
    {
        return $this->instanceUrl . self::$wallabagEndpoints[$this->apiVersion];
    }

    /**
     * Checks version configuration.
     *
     * @param mixed $version given version ID.
     *
     * @return bool true if it's valid, false otherwise.
     */
    private function isVersionAllowed($version)
    {
        return isset(self::$wallabagVersions[$version]);
    }
}
