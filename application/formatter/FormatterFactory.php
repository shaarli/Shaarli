<?php

namespace Shaarli\Formatter;

use Shaarli\Config\ConfigManager;

/**
 * Class FormatterFactory
 *
 * Helper class used to instantiate the proper BookmarkFormatter.
 *
 * @package Shaarli\Formatter
 */
class FormatterFactory
{
    /** @var ConfigManager instance */
    protected $conf;

    /** @var bool */
    protected $isLoggedIn;

    /**
     * FormatterFactory constructor.
     *
     * @param ConfigManager $conf
     * @param bool          $isLoggedIn
     */
    public function __construct(ConfigManager $conf, bool $isLoggedIn)
    {
        $this->conf = $conf;
        $this->isLoggedIn = $isLoggedIn;
    }

    /**
     * Instanciate a BookmarkFormatter depending on the configuration or provided formatter type.
     *
     * @param string|null $type force a specific type regardless of the configuration
     *
     * @return BookmarkFormatter instance.
     */
    public function getFormatter(?string $type = null): BookmarkFormatter
    {
        $type = $type ? $type : $this->conf->get('formatter', 'default');
        $className = '\\Shaarli\\Formatter\\Bookmark' . ucfirst($type) . 'Formatter';
        if (!class_exists($className)) {
            $className = '\\Shaarli\\Formatter\\BookmarkDefaultFormatter';
        }

        return new $className($this->conf, $this->isLoggedIn);
    }
}
