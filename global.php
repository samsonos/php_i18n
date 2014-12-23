<?php
use Samson\i18n\i18n;
/**
 * Translate(Перевести) фразу
 *
 * @param string $key 		Ключ для поиска перевода фразы
 * @param string $locale 	Локаль в которую необходимо перевести
 * @return string Переведенная строка или просто значение ключа
 */
function t( $key, $return = false, $locale = NULL )
{
	// т.к. эта функция вызывается очень часто - создадим статическую переменную
	static $_v;

	// Если переменная не определена - получим единственный экземпляр ядра
	if( !isset($_v)) $_v = & m('i18n');	

	// Вернем указатель на ядро системы
	if (!$return)echo $_v->translate( $key, $locale );
	else return $_v->translate( $key, $locale );
}

/**
 * Generate corrert plural word form from dictionary
 * @param string $key 	Dictionary key
 * @param string $count Amount for plural form
 * @return string Word in correct plural form
 */
function plural( $key, $count )
{
	// т.к. эта функция вызывается очень часто - создадим статическую переменную
	static $_v;

	// Если переменная не определена - получим единственный экземпляр ядра
	if( !isset($_v)) $_v = & m('i18n');
	return $_v->plural( $key, $count );
}