<?php
namespace samsonphp\i18n;

use samson\core\CompressableService;
use samson\core\SamsonLocale;
use samsonos\compressor\Module;
use samsonphp\event\Event;

/**
 * Localization \ Internalization service
 *
 * @author Vitaly Iegorov <egorov@samsonos.com>
 * @author Alexandr Storchovyy <storchovyy@samsonos.com>
 * @version 1.0
 */
class i18n extends CompressableService
{
    /** Generic CSS prefix*/
    const CSS_PREFIX = 'i18n-';

    /** Regex dictionary file name pattern */
    const DICTIONARY_PATTERN = '/Dictionary\.php$/i';

    /** Идентификатор модуля */
    public $id = 'i18n';

    /** Текущая локаль */
    public $locale = 'en';

    /** Выводить текст ссылки в список */
    public $isLocaleLinkText = false;

    /** Коллекция данных для перевода */
    public $dictionary = array('en' => array());

    /** Regex pattern for search all matches */
    protected $patternSearch = '/\s+t\s*\(\s*[\'\"](?<key>[^\"\']+)[\'\"](,\s*(false|true)\s*,\s*(?<plural>\d+))?/';

    /** @var array Collection of translations grouped by modules */
    protected $moduleDictionary;

    /** @deprecated Now one single collection is used */
    public $plural = array('en' => array());

    //[PHPCOMPRESSOR(remove,start)]
    public function prepareDictionary()
    {
        // Collection of found and loaded dictionaries
        $loaded = array();

        /** @var \samson\core\Module $module Iterate all loaded core modules */
        foreach (self::$instances as $module) {
            // Iterate all module PHP files
            foreach ($module->resourceMap->classes as $path => $className) {
                // Check if file name matches dictionary pattern
                if (preg_match(self::DICTIONARY_PATTERN, $path)) {
                    // Create dictionary hashed name
                    $loaded[$module->id()] = array($className, $path);
                }
            }
        }

        // Create a cached hashed full dictionary name
        $cachedDictionary = md5(serialize($loaded)) . '.php';

        // If cached dictionary does not exists
        if ($this->cache_refresh($cachedDictionary)) {
            foreach ($loaded as $id => $data) {
                $className = $data[0];
                $path = $data[1];
                // Include new file that we think has a dictionary class
                include_once($path);

                // Check if we have included IDictionary ancestor
                if (isset($className{0}) && in_array(__NAMESPACE__ . '\IDictionary', class_implements($className))) {
                    // Create dictionary instance
                    $dictionary = new $className();
                    // Iterate dictionary key => value localization data
                    foreach ($dictionary->getDictionary() as $locale => $dict) {
                        // Store module translation collection
                        $this->moduleDictionary[$id][$locale] = $dict;

                        // Gather every dictionary in  one collection grouped by locale
                        $this->dictionary[$locale] = array_merge(
                            isset($this->dictionary[$locale]) ? $this->dictionary[$locale] : array(),
                            $dict
                        );
                    }
                }
            }

            // Store dictionary to cache
            file_put_contents(
                $cachedDictionary,
                '<?php function php_i18n_dictionary() { return ' . var_export($this->dictionary, true) . ';}'
            );
        } else {
            // Load generated dictionary
            require $cachedDictionary;

            // Call generated dictionary function
            if (function_exists('php_i18n_dictionary')) {
                $this->dictionary = php_i18n_dictionary();
            }
        }
    }

    /**
     * Automatic i18n dictionary file generation
     */
    public function __generate()
    {
        s()->async(true);

        // Get all active locales
        foreach (SamsonLocale::$locales as $locale) {
            $keys[$locale] = array();
        }
        // Iterate all module PHP files
        foreach (self::$instances as $module) {
            // Path current module
            $modulePath = $module->resourceMap->entryPoint;
            // Only check the internal modules
            if (strpos($modulePath, '/vendor/') === false) {
                // Source files for search t('')
                $sources = $this->getModuleResources($module);
                // All matches in source
                $sourceMatches = array();
                // All matches in module
                $result = array();
                // Iterate sources files
                foreach ($sources as $source) {
                    // Find all t('') function calls in view code
                    if (preg_match_all($this->patternSearch, file_get_contents($source), $matches)) {
                        // Combine key and plural array
                        $matchesArray = array_combine($matches['key'], $matches['plural']);
                        // If plural not empty, created 3 field for translated
                        foreach ($matchesArray as $key => $value) {
                            if (!empty($value)) {
                                $matchesArray[$key] = array('', '', '');
                            }
                        }
                        // Created dictionary for each locale
                        foreach ($keys as $locale => $value) {
                            $sourceMatches[$locale] = $matchesArray;
                        }
                    }
                    // Merge dictionary for each source file
                    $result = array_replace_recursive($sourceMatches, $result);
                }
                // Merge new matches with old dictionary
                if (isset($this->moduleDictionary[$module->id]) && is_array($this->moduleDictionary[$module->id])) {
                    $final = array_replace_recursive($result, $this->moduleDictionary[$module->id]);
                } else {
                    $final = $result;
                }

                trace('Generated dictionary for module: ' . $module->id);
                // Created dictionary file
                $path = $modulePath . $this->id;
                $this->createDictionary($module->id, $final, $path);
            }
        }

    }

    /**
     * @param string $moduleId
     * @param array $result
     * @param string $dictionaryPath
     */
    protected function createDictionary($moduleId, $result, $dictionaryPath)
    {
        if (file_exists($dictionaryPath . '/Dictionary.php')) {
            unlink($dictionaryPath . '/Dictionary.php');
            rmdir($dictionaryPath);
        }
        mkdir($dictionaryPath, 0775);
        fopen($dictionaryPath . '/Dictionary.php', "w+");

        $generator = new \samson\core\Generator($moduleId . "\\" . 'i18n');
        $generator->defclass('Dictionary', null, array('\samsonphp\i18n\IDictionary'))
            ->deffunction('getDictionary')
            ->newline('return ', 2)
            ->arrayvalue(array_filter($result))->text(';')
            ->endfunction()
            ->endclass()
            ->write($dictionaryPath . '/Dictionary.php');
    }
    //[PHPCOMPRESSOR(remove,end)]

    /** @inheritdoc */
    public function init(array $params = array())
    {
        //[PHPCOMPRESSOR(remove,start)]
        $this->prepareDictionary();
        //[PHPCOMPRESSOR(remove,end)]

        // Subcribe for
        Event::subscribe('core.rendered', array($this, 'templateRenderer'));
    }

    /**
     * @param \samson\core\Module $module
     * @return array Patches sources files
     */
    public function getModuleResources($module)
    {
        $sources = array(
            'views',
            'controllers',
            'models',
            'php',
            'module'
        );

        $resources = array();
        foreach ($sources as $source) {
            foreach ($module->resourceMap->$source as $resource) {
                if ($source == 'module') {
                    $controller = $module->resourceMap->$source;
                    $resources[] = $controller[1];
                    break;
                }
                $resources[] = $resource;
            }
        }

        return $resources;
    }

    /** Controller for rendering generic locales list */
    public function __list()
    {
        $current = SamsonLocale::current();

        $default = 'ru';

        if (defined('DEFAULT_LOCALE')) {
            $default = DEFAULT_LOCALE;
        }

        $urlText = url()->text;
        $httpHost = $_SERVER['HTTP_HOST'];
        Event::fire('i18n.list.generating', array(& $httpHost, & $urlText));

        // Render all available locales
        $html = '';
        foreach (SamsonLocale::get() as $locale) {
            if ($current != $default) {
                $currentUrlText = substr($urlText, strlen($current) + 1);
            } else {
                $currentUrlText = $urlText;
            }

            if ($locale == $default) {
                $url = 'http://' . $httpHost . __SAMSON_BASE__ . $currentUrlText;
            } else {
                $url = 'http://' . $httpHost . __SAMSON_BASE__ . $locale . '/' . $currentUrlText;
            }
            $localeName = '';
            if ($this->isLocaleLinkText) {
                $localeName = $this->translate($locale, $current);
            }
            $html .= $this->view('list/item')
                ->css(self::CSS_PREFIX)
                ->locale($locale == SamsonLocale::DEF && SamsonLocale::DEF == '' ? 'def' : $locale)
                ->active($locale == $current ? self::CSS_PREFIX . 'active' : '')
                ->url($url)
                ->name($localeName)
                ->output();
        }

        // Set locale list view
        $this->view('list/index')->locale($current)->css(self::CSS_PREFIX)->items($html);
    }


    /**
     * Create alternate multilingual meta tags
     */
    public function __meta()
    {
        // Set links in view
        $this->html($this->renderMetaTags(SamsonLocale::current(), SamsonLocale::$defaultLocale, url()->text));
    }

    /**
     * @param $key
     * @param null $locale
     */
    protected function & findDictionary(& $key, $locale = null)
    {
        // If no locale for translation is specified - use current system locale
        $locale = !isset($locale) ? locale() : $locale;

        // Remove whitespaces from key
        $key = trim($key);

        // Get pointer to locale dictionary
        return $this->dictionary[$locale];
    }

    /**
     * Translate(Перевести) фразу
     *
     * @param string $key Key for pluralization and translation
     * @param string $locale Locale to use for translation
     * @return string Translated key
     */
    public function translate($key, $locale = null)
    {
        // Retrieve dictionary by locale and modify key
        $dict = $this->findDictionary($key, $locale);

        // If translation value or just a key
        return isset($dict[$key]{0}) ? $dict[$key] : $key;
    }

    /**
     * Perform pluralization and translation of key
     * @param string $key Key for pluralization and translation
     * @param int $count Key quantity for pluralization
     * @param string $locale Locale to use for translation
     * @return string Pluralized and translated key
     */
    public function plural($key, $count, $locale = null)
    {
        // Retrieve dictionary by locale and modify key
        $dict = $this->findDictionary($key, $locale);

        $translation = &$dict[$key];
        // If translation value is available
        if (isset($translation) && (sizeof($translation) == 3) && !empty($translation[0])) {
            switch ($count % 20) {
                case 1:
                    return $translation[0];
                case 2:
                case 3:
                case 4:
                    return $translation[1];
                default:
                    return $translation[2];
            }
        }
        // No plural form is found - just return key

        return $key;
    }

    /**
     * Render html <link rel="alternate"...> for localized resources for SEO
     * @param string $href Current url path
     * @return string Generated <link...> meta-tags
     */
    protected function renderMetaTags($current, $default, $href)
    {
        // Link tags
        $metaHTML = '';
        foreach (SamsonLocale::get() as $locale) {
            // Render meta tags for other locales, not current
            if ($locale != $current) {
                // Define language(lang) meta tag parameter, Fix for Ukrainian locale name for language
                $language = ($locale == '') ? $default : (($locale != 'ua') ? $locale : 'uk');

                // Remove current locale from href and remove all slashes
                $href = __SAMSON_PROTOCOL.$_SERVER['HTTP_HOST'].'/'.$locale.'/'.str_ireplace($current.'/', '', $href);

                // Build meta-tag
                $metaHTML .= '<link rel="alternate" lang="' . $language . '" href="' . $href .'">';
            }
        }

        return $metaHTML;
    }

    /**
     * Handle core main template rendered event to
     * add SEO needed localization metatags to HTML markup
     * @param string $html Rendered HTML template from core
     * @param array $parameters Collection of data passed to current view
     * @param Module $module Pointer to active core module
     */
    public function templateRenderer(&$html, $parameters, $module)
    {
        $html = str_ireplace('</head>', $this->renderMetaTags(SamsonLocale::current(), SamsonLocale::$defaultLocale, url()->text).'</head>', $html);
        $html = str_ireplace('<html>', '<html lang="'.SamsonLocale::current().'">', $html);
    }
}
