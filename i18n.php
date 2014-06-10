<?php
namespace samson\i18n;

use samson\core\CompressableExternalModule;
use samson\core\SamsonLocale;

/** Стандартный путь к папке со словарями */
if(!defined('__SAMSON_I18N_PATH'))  define('__SAMSON_I18N_PATH', __SAMSON_APP_PATH.'/i18n' );

/** Стандартный путь главному словарю сайта */
if(!defined('__SAMSON_I18N_DICT'))  define('__SAMSON_I18N_DICT', __SAMSON_I18N_PATH.'/dictionary.php' );

/**
 * Интернализация / Локализация
 *
 * @author Vitaly Iegorov <vitalyiegorov@gmail.com>
 * @version 1.0
 */
class i18n extends CompressableExternalModule
{
    /** Generic CSS prefix*/
    const CSS_PREFIX = 'i18n-';

    /** Идентификатор модуля */
    public $id = 'i18n';

    /** Автор модуля */
    public $author = 'Vitaly Iegorov';

    /** Текущая локаль */
    public $locale = 'en';

    /** Путь к файлу словаря */
    public $path;

    /** Коллекция данных для перевода */
    public $dictionary = array( 'ru' => array() );

    /** @see \samson\core\ModuleConnector::prepare() */
    public function prepare()
    {

    }

    /** @see \samson\core\ModuleConnector::init() */
    public function init(array $params = array())
    {
        parent::init();

        // Include file with dictionary
        if (file_exists(s()->path().__SAMSON_I18N_DICT)) {
            include(s()->path().__SAMSON_I18N_DICT);
        }

        // If function dictionary exists
        if (function_exists('\dictionary')) {

            // Пробежимся по локалям в словаре
            foreach (\dictionary() as $locale => $dict ) {
                // Создадим словарь для локали
                $this->data[ $locale ] = array();

                // Преобразуем ключи
                foreach ( $dict as $k => $v ) $this->data[ $locale ][ (trim($k)) ] = $v;
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

        // If dictionary path does not exists - create it
        $path = s()->path().__SAMSON_I18N_PATH;
        if (!file_exists($path)) {
            mkdir($path, 0775, true);
        }

        // If dictionary already exists - get it
        if (function_exists('\dictionary')) {
            $keys = \dictionary();

        } else { // Create new dictionary array
            $keys = array();
        }

        // Collection of keys for translation
        foreach (\samson\core\SamsonLocale::$locales as $locale) {
            // If dictionary does not have this locale key
            if (!isset($keys[$locale])) {
                // Add it
                $keys[$locale] = array();
            }
        }

        // File sources for scanning
        $sources = array(
            'views',
            'controllers',
            'models'
        );

        // Iterate all loaded modules
        foreach (s()->load_stack as $ns => & $data) {
            trace($ns);
            // Iterate module supported sources
            foreach ($sources as $source) {
                // Iterate source files
                foreach ($data[$source] as $view) {
                    // Find all t('') function calls in view code
                    if(preg_match_all('/\s+t\s*\([\'\"](?<key>[^\"\']+)/', file_get_contents($view), $matches)) {
                        foreach (\samson\core\SamsonLocale::$locales as $locale) {
                            trace('Merging array for locale '.$locale);
                            $keys[$locale] = array_merge(array_fill_keys(array_map('addslashes',$matches['key']), ''), $keys[$locale]);
                        }
                    }
                }
            }
        }

        // Write dictionary file
        $g = new \samson\core\Generator();
        $g->deffunction('dictionary')
            ->newline('return ',2)
            ->arrayvalue(array_filter($keys))->text(';')
            ->endfunction()
        ->write(s()->path().__SAMSON_I18N_DICT);

    }
    //[PHPCOMPRESSOR(remove,end)]

    /** Controller for rendering generic locales list */
    public function __list()
    {
        $current = SamsonLocale::current();

        // Render all available locales
        $html = '';
        foreach (SamsonLocale::get() as $locale) {
            $html .= $this->view('list/item')
                ->css(self::CSS_PREFIX)
                ->locale($locale == SamsonLocale::DEF && SamsonLocale::DEF == ''? 'def' : $locale)
                ->active($locale == $current ? self::CSS_PREFIX.'active':'')
                ->url($locale == SamsonLocale::DEF ? url()->base() : url()->base().$locale)
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
                    $lang = $locale;
                }
                $link .= '<link rel="alternate" lang="'.$lang.'" href="'.'http://'.$_SERVER['HTTP_HOST'].'/';
                if ($current == '') {
                    $link .= $locale.'/'.url()->text.'">';
                } else {
                    if ($locale != '') {
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
     * Translate(Перевести) фразу
     *
     * @param string $key 		Ключ для поиска перевода фразы
     * @param string $locale 	Локаль в которую необходимо перевести
     * @return string Переведенная строка или просто значение ключа
     */
    public function translate($key, $locale = null)
    {
        // Если требуемая локаль не передана - получим текущую локаль
        if( !isset( $locale ) ) $locale = locale();

        // Получим словарь для нужной локали
        $dict = & $this->data[ $locale ];

        // Получим хеш строки
        $md5_key = (trim( $key ));

        // If translation value is available
        if (isset($dict[$md5_key]) && strlen($dict[$md5_key])) {
            return $dict[ $md5_key ];
        } else { // Just return key itself
            return $key;
        }
    }
}