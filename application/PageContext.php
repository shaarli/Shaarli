<?php
/**
 * Holds the data (context) that is passed to the template engine
 */
class PageContext
{
    protected $data = array();

    /**
     * Initializes a context containing information about the Shaarli instance
     *
     * @param array  $globals        the $_GLOBALS array
     * @param array  $get            the $_GET array
     * @param array  $server         the $_SERVER array
     * @param array  $session        the $_SESSION array
     * @param string $currentVersion the current Shaarli version code
     */
    public function __construct($globals, $get, $server, $session, $currentVersion)
    {
        $this->setServerInfo($server);
        $this->setSearchInfo($get);
        $this->setTitleInfo($globals);
        $this->setVersionInfo($globals['config'], $currentVersion);

        $this->data['privateonly'] = !empty($_SESSION['privateonly']);

        if (!empty($globals['plugin_errors'])) {
            $this->data['plugin_errors'] = $globals['plugin_errors'];
        }
    }

    /**
     * Sets a context entry
     *
     * @param string $key   a key to reference the entry
     * @param mixed  $value a value
     */
    public function set($key, $value)
    {
        $this->data[$key] = $value;
    }

    /**
     * Merges the content of an array with the current context's
     */
    public function merge($dataArray)
    {
        $this->data = array_merge($this->data, $dataArray);
    }

    /**
     * Returns the context array
     *
     * @return array the context entries
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Sets search criteria from GET parameters
     *
     * @param array $get the $_GET array
     */
    private function setSearchInfo($get)
    {
        $this->data['searchcrits'] = '';

        if (!empty($get['searchtags'])) {
            $this->data['searchcrits'] .= '&searchtags=' . urlencode($get['searchtags']);
        } else if (!empty($get['searchterm'])) {
            $this->data['searchcrits'] .= '&searchterm=' . urlencode($get['searchterm']);
        }
    }

    /**
     * Sets server information
     *
     * @param $server the $_SERVER array
     */
    private function setServerInfo($server)
    {
        // FIXME: thrice the same variable
        $this->data['feedurl'] = escape(index_url($server));
        $this->data['scripturl'] = index_url($server);
        $this->data['source'] = index_url($server);
    }

    /**
     * Sets title information
     *
     * @param array $globals the $GLOBALS array
     */
    private function setTitleInfo($globals)
    {
        $this->data['pagetitle'] = 'Shaarli';

        if (!empty($globals['title'])) {
            $this->data['pagetitle'] = $globals['title'];
        }

        if (!empty($globals['pagetitle'])) {
            $this->data['pagetitle'] = $globals['pagetitle'];
        }

        $this->data['shaarlititle'] = empty($globals['title']) ? 'Shaarli': $globals['title'];

        if (!empty($globals['titleLink'])) {
            $this->data['titleLink'] = $globals['titleLink'];
        }
    }

    /**
     * Sets version information
     *
     * A notification should be displayed in the following situations:
     * - a new version is available on the selected branch;
     * - an error happened when retrieving version information.
     *
     * @param array  $globalConfig   the $GLOBALS['config'] array
     * @param string $currentVersion the current Shaarli version code
     */
    private function setVersionInfo($globalConfig, $currentVersion)
    {
        $this->data['newVersion'] = '';
        $this->data['versionError'] = '';

        try {
            $version = ApplicationUtils::checkUpdate(
                $currentVersion,
                $globalConfig['UPDATECHECK_FILENAME'],
                $globalConfig['UPDATECHECK_INTERVAL'],
                $globalConfig['ENABLE_UPDATECHECK'],
                isLoggedIn(),
                $globalConfig['UPDATECHECK_BRANCH']
            );
            $this->data['newVersion'] = escape($version);

        } catch (Exception $exc) {
            logm($exc->getMessage());
            $this->data['versionError'] = escape($exc->getMessage());
        }
    }
}
