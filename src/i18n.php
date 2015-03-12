<?php
namespace samsonphp\i18n;

use samson\core\CompressableService;
use samson\core\SamsonLocale;
use samsonphp\event\Event;

/** Стандартный путь к папке со словарями */
if (!defined('__SAMSON_I18N_PATH')) {
    define('__SAMSON_I18N_PATH', __SAMSON_APP_PATH.'/i18n' );
}

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
    public $dictionary = array( 'en' => array() );

    /** @deprecated Now one single collection is used */
    public $plural = array( 'en' => array() );

    /** @see \samson\core\ModuleConnector::init() */
    public function init(array $params = array())
    {
        parent::init();

        /** @var \samson\core\Module $module Iterate all loaded core modules */
        foreach (self::$instances as $module) {
            // Iterate all module PHP files
            foreach ($module->resourceMap->classes as $path => $className) {
                // Check if file name matches dictionary pattern
                if (preg_match(self::DICTIONARY_PATTERN, $path)) {
                    // Include new file that we think has a dictionary class
                    include_once($path);

                    // Check if we have included IDictionary ancestor
                    if (isset($className{0}) && in_array(__NAMESPACE__.'\IDictionary', class_implements($className))) {
                        // Create dictionary instance
                        $dictionary = new $className();
                        // Iterate dictionary key => value localization data
                        foreach ($dictionary->getDictionary() as $locale => $dict) {
                            // Gather every dictionary in  one collection grouped by locale
                            $this->dictionary[$locale] = array_merge(
                                isset($this->dictionary[$locale]) ? $this->dictionary[$locale] : array(),
                                $dict
                            );
                        }
                    }
                }
            }
        }
    }

    //[PHPCOMPRESSOR(remove,start)]
    /**
     * Automatic i18n dictionary file generation
     */
    public function __generate()
    {
        s()->async(true);
        $sources = array(
            'views',
            'controllers',
            'models'
        );

        foreach (\samson\core\SamsonLocale::$locales as $locale) {
            $keys[$locale] = array();
        }

        foreach (self::$instances as $module) {
            $modulePath = $module->resourceMap->entryPoint;
            $dictionaryPath = $modulePath.'i18n';
            if (!file_exists($dictionaryPath) && is_writable($modulePath)) {
                mkdir($dictionaryPath, 0775, true);
                fopen($dictionaryPath.'/Dictionary.php', "w+");
                trace('Generated:'.$dictionaryPath);
                foreach($sources as $source){
                    foreach ($module->resourceMap->$source as $view) {
                        // Find all t('') function calls in view code
                        $result = $keys;
                        if(preg_match_all('/\s+t\s*\([\'\"](?<key>[^\"\']+)/', file_get_contents($view), $matches)) {

                            foreach($keys as $k=>$v){
                                $result[$k] = array_merge(array_fill_keys(array_map('addslashes',$matches['key']), ''), $result[$k]);
                            }
                        }
                    }
                }
                $g = new \samson\core\Generator($module->id."\\".'i18n');
                $g->defclass('Dictionary', null, array('\samson\i18n\IDictionary'))
                        ->deffunction('getDictionary')
                            ->newline('return ', 2)
                            ->arrayvalue(array_filter($result))->text(';')
                        ->endfunction()
                    ->endclass()
                    ->write($dictionaryPath.'/Dictionary.php');
            }
        }
    }
    //[PHPCOMPRESSOR(remove,end)]

    /** Controller for rendering generic locales list */
    public function __list()
    {
        $current = SamsonLocale::current();

        $default = 'ru';

        if (defined('DEFAULT_LOCALE')){
            $default = DEFAULT_LOCALE;
        }

        $urlText = url()->text;
        $httpHost = $_SERVER['HTTP_HOST'];
        Event::fire('i18n.list.generating', array(& $httpHost, & $urlText));

        // Render all available locales
        $html = '';
        foreach (SamsonLocale::get() as $locale) {
            if ($current != $default) {
                $currentUrlText = substr($urlText,strlen($current)+1);
            } else {
                $currentUrlText = $urlText;
            }

            if ($locale == $default) {
                $url = 'http://'.$httpHost.__SAMSON_BASE__.$currentUrlText;
            } else {
                $url = 'http://'.$httpHost.__SAMSON_BASE__.$locale.'/'.$currentUrlText;
            }
            $localeName = '';
            if ($this->isLocaleLinkText) {
                $localeName = $this->translate($locale, $current);
            }
            $html .= $this->view('list/item')
                ->css(self::CSS_PREFIX)
                ->locale($locale == SamsonLocale::DEF && SamsonLocale::DEF == ''? 'def' : $locale)
                ->active($locale == $current ? self::CSS_PREFIX.'active':'')
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
        $current = SamsonLocale::current();
        // Link tags
        $link = '';
        foreach (SamsonLocale::get() as $locale) {
            if ($locale != $current) {
                if ($locale == '') {
                    $lang = 'ru';
                } else {
                    $lang = ($locale != 'ua') ? $locale : 'uk';
                }
                $link .= '<link rel="alternate" lang="'.$lang.'" href="'.'http://'.$_SERVER['HTTP_HOST'].'/';
                if ($current == 'ru') {
                    $link .= $locale.'/'.url()->text.'">';
                } else {
                    if ($locale != 'ru') {
                        $link .= $locale.'/'.substr(url()->text,strlen($current)+1).'">';
                    } else {
                        $link .= substr(url()->text,strlen($current)+1).'">';
                    }
                }
            }
        }
        // Set links in view
        $this->html($link);
    }

    /**
     * @param $key
     * @param null $locale
     */
    protected function & findDictionary(& $key, $locale = null)
    {
        // If no locale for translation is specified - use current system locale
        $locale = !isset( $locale ) ? locale() : $locale;

        // Remove whitespaces from key
        $key = trim($key);

        // Get pointer to locale dictionary
        return  $this->dictionary[$locale];
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

        $translation = & $dict[$key];
        // If translation value is available
        if (isset($translation) && (sizeof($translation) == 3)) {
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
}