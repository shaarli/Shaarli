<?php

namespace Shaarli;

use Gettext\GettextTranslator;
use Gettext\Merge;
use Gettext\Translations;
use Gettext\Translator;
use Gettext\TranslatorInterface;
use Shaarli\Config\ConfigManager;

/**
 * Class Languages
 *
 * Load Shaarli translations using 'gettext/gettext'.
 * This class allows to either use PHP gettext extension, or a PHP implementation of gettext,
 * with a fixed language, or dynamically using autoLocale().
 *
 * Translation files PO/MO files follow gettext standard and must be placed under:
 *   <translation path>/<language>/LC_MESSAGES/<domain>.[po|mo]
 *
 * Pros/cons:
 *   - gettext extension is faster
 *   - gettext is very system dependent (PHP extension, the locale must be installed, and web server reloaded)
 *
 * Settings:
 *   - translation.mode:
 *     - auto: use default setting (PHP implementation)
 *     - php: use PHP implementation
 *     - gettext: use gettext wrapper
 *   - translation.language:
 *     - auto: use autoLocale() and the language change according to user HTTP headers
 *     - fixed language: e.g. 'fr'
 *   - translation.extensions:
 *     - domain => translation_path: allow plugins and themes to extend the defaut extension
 *       The domain must be unique, and translation path must be relative, and contains the tree mentioned above.
 *
 * @package Shaarli
 */
class Languages
{
    /**
     * Core translations domain
     */
    const DEFAULT_DOMAIN = 'shaarli';

    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * @var string
     */
    protected $language;

    /**
     * @var ConfigManager
     */
    protected $conf;

    /**
     * Languages constructor.
     *
     * @param string        $language lang determined by autoLocale(), can be overridden.
     * @param ConfigManager $conf     instance.
     */
    public function __construct($language, $conf)
    {
        $this->conf = $conf;
        $confLanguage = $this->conf->get('translation.language', 'auto');
        // Auto mode or invalid parameter, use the detected language.
        // If the detected language is invalid, it doesn't matter, it will use English.
        if ($confLanguage === 'auto' || ! $this->isValidLanguage($confLanguage)) {
            $this->language = substr($language, 0, 5);
        } else {
            $this->language = $confLanguage;
        }

        if (! extension_loaded('gettext')
            || in_array($this->conf->get('translation.mode', 'auto'), ['auto', 'php'])
        ) {
            $this->initPhpTranslator();
        } else {
            $this->initGettextTranslator();
        }

        // Register default functions (e.g. '__()') to use our Translator
        $this->translator->register();
    }

    /**
     * Initialize the translator using php gettext extension (gettext dependency act as a wrapper).
     */
    protected function initGettextTranslator ()
    {
        $this->translator = new GettextTranslator();
        $this->translator->setLanguage($this->language);
        $this->translator->loadDomain(self::DEFAULT_DOMAIN, 'inc/languages');

        // Default extension translation from the current theme
        $themeTransFolder = rtrim($this->conf->get('raintpl_tpl'), '/') .'/'. $this->conf->get('theme') .'/language';
        if (is_dir($themeTransFolder)) {
            $this->translator->loadDomain($this->conf->get('theme'), $themeTransFolder, false);
        }

        foreach ($this->conf->get('translation.extensions', []) as $domain => $translationPath) {
            if ($domain !== self::DEFAULT_DOMAIN) {
                $this->translator->loadDomain($domain, $translationPath, false);
            }
        }
    }

    /**
     * Initialize the translator using a PHP implementation of gettext.
     *
     * Note that if language po file doesn't exist, errors are ignored (e.g. not installed language).
     */
    protected function initPhpTranslator()
    {
        $this->translator = new Translator();
        $translations = new Translations();
        // Core translations
        try {
            $translations = $translations->addFromPoFile('inc/languages/'. $this->language .'/LC_MESSAGES/shaarli.po');
            $translations->setDomain('shaarli');
            $this->translator->loadTranslations($translations);
        } catch (\InvalidArgumentException $e) {}

        // Default extension translation from the current theme
        $theme = $this->conf->get('theme');
        $themeTransFolder = rtrim($this->conf->get('raintpl_tpl'), '/') .'/'. $theme .'/language';
        if (is_dir($themeTransFolder)) {
            try {
                $translations = Translations::fromPoFile(
                    $themeTransFolder .'/'. $this->language .'/LC_MESSAGES/'. $theme .'.po'
                );
                $translations->setDomain($theme);
                $this->translator->loadTranslations($translations);
            } catch (\InvalidArgumentException $e) {}
        }

        // Extension translations (plugins, themes, etc.).
        foreach ($this->conf->get('translation.extensions', []) as $domain => $translationPath) {
            if ($domain === self::DEFAULT_DOMAIN) {
                continue;
            }

            try {
                $extension = Translations::fromPoFile($translationPath . $this->language .'/LC_MESSAGES/'. $domain .'.po');
                $extension->setDomain($domain);
                $this->translator->loadTranslations($extension);
            } catch (\InvalidArgumentException $e) {}
        }
    }

    /**
     * Checks if a language string is valid.
     *
     * @param string $language e.g. 'fr' or 'en_US'
     *
     * @return bool true if valid, false otherwise
     */
    protected function isValidLanguage($language)
    {
        return preg_match('/^[a-z]{2}(_[A-Z]{2})?/', $language) === 1;
    }

    /**
     * Get the list of available languages for Shaarli.
     *
     * @return array List of available languages, with their label.
     */
    public static function getAvailableLanguages()
    {
        return [
            'auto' => t('Automatic'),
            'en' => t('English'),
            'fr' => t('French'),
            'de' => t('German'),
        ];
    }
}
