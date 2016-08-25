<?php
use samsonphp\i18n\i18n;

/**
 * Translate(Перевести) фразу
 *
 * @param string    $key 		Key to search for localization
 * @param bool      $return 	If true - output will be return, otherwise echoed
 * @param int       $count 	    Locale to use for translation
 * @param string    $locale 	Locale to use for translation
 * @return string Localized $key or itself if translation not found
 * @deprecated Should be used as $this->system->getContainer()->geti18n()->translate()|plural()
 */
function t($key, $return = false, $count = -1, $locale = null)
{
    /** @var \samsonphp\i18n\i18n $pointer  Pointer to cached i18n module */
    static $pointer;

    // Store pointer
    $pointer = !isset($pointer) ? m('i18n') : $pointer;

    // Perform translation
    $translation = ($count === -1)
        ? $pointer->translate($key, $locale)
        : $pointer->plural($key, $count, $locale);

    // Echo or return dependently on params
    return (!$return) ? print($translation) : $translation;
}
