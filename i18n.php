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
	
	public $requirements = array('core');


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

    /** Controller for rendering generic locales list */
    public function __list()
    {
        $current = SamsonLocale::current();

        // Render all available locales
        $html = '';
        foreach (SamsonLocale::get() as $locale) {
            $html .= $this->view('list/item')
                ->css(self::CSS_PREFIX)
                ->locale($locale == SamsonLocale::DEF ? 'def' : $locale)
                ->active($locale == $current ? self::CSS_PREFIX.'active':'')
                ->url(url_build($locale))
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

        // Попытаемся найти запись в словаре
        if( isset( $dict[ $md5_key ] ) ) return $dict[ $md5_key ];
        // Просто вернем ключ
        else return $key;
    }
}