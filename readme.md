# Internalization(i18n) module for [SamsonPHP](http://samsonphp.com) framework

> Module gives uses special global functions to give localization features for a web-application

Module default locale is empty string ```' '```, its value is stored at ```samsonos\php\core\SamsonLocale::DEF``` constant.
This gives ability to use any locale as default without specifying it.

## Setting supported locales
For adding localization support to your web-application special global function must be called:

``` setlocales($locale1, $locale2, ...)```

Where parameters are:
 * ```$locale1 = $locale2 = 'ru'|'en'|'ko'```, and other supported locales by ```samsonos\php\core\SamsonLocale```

Usually this function is called in web-application starter script ```index.php``` before ```s()```,
for example ```index.php```:

```php
// SamsonPHP Configuration
require('config.php');

// Add russian and french locales
setlocales('ru', 'fr');

// Run
s()->composer()->start();
```

## Switching locales
To switch system to another **supported** locale(defined by ```setlocales()```) you should add locale prefix to url,
for example if we have domain ```http://example.com``` we must redirect user to ```http://example.com/ru``` for switching
system to ```russian``` localized version. This url suffix ```/ru``` is ignored by all SamsonPHP components and actually
is invisible in URL dispatcher ```samsonos\php\core\URL``` and in all other components. The only module who handles this
suffix is ```samsonos\php\i18n```(this module).

> If you specify not supported locale suffix (you did not specified it in ```setlocales()``` before SamsonPHP is started),
  URL dispatcher ```samsonos\php\core\URL``` will handle it as regular module controller request.

## Using localized views
When you switch to other supported locale then default locale, automatic system view rendering mechanism is changed. First
of all system searches necessary view in *localized view path*, using default settings, system view path is ```app/view/```.
*localized view path* in this case is ```app/view/$locale/```, where ```$locale = 'ru'|'en'|'ko'```.

So if you want to have separate view files for different locales you can create two view files with the same names, for example
we have english view file located at ```app/view/test.php```:

```php
<h1>This is ENGLISH view file</h1>
```

And russian view file located at ```app/view/ru/test.php```:

```php
<h1>This is RUSSIAN view file</h1>
```

Also we have regular simple controller file located at ```app/view/controller/test.php```:

```php
function test()
{
    m()->view('test')->title('Localization test');
}
```

And default view template file located at ```app/view/index.php``` who renders current module:

```php
<html>
    <body>
        <?php m()->render()?>
    </body>
</html>
```

And if you try to visit URL ```htttp://example.com/ru/test``` you will get ```app/view/ru/test.php``` rendered instead of ```app/view/test.php```.

> The logic is: If system does not find localized view it will use regular view.

## Dictionary localization
If we don't want to create separate views for every supported locale, as they match each other in HTML markup and only differs in
some words you can use **dictionary** localization logic.

For using global **dictionary** for your web-application , using default settings, you have to create special dictionary file
located in ```app/view/i18n/dictionary.php``` with generic special function defined:

```php
function dictionary()
{
    return array(
        $locale => array(
            $key => $value,
            ...
        ),
        'ru' => array(
            'Translate me' => 'Переведи меня',
        )
    );
}
```

Where parameters are:
 * ```$locale = 'ru'|'en'|'ko'``` and other supported locales by samsonos\core\SamsonLocale
 * ```$key = 'Translate me'``` any string value to outputted and translated. This key must match in all locales for translation
 * ```$value = 'Переведи меня'```(translation to russian) string 'Translate me' in current locale array

We use function to avoid bugs when compressing web-application to a snapshot via  [samsonos/php_compressor](http://github.com/samsonos/php_compressor)

### Using dictionary in views
To use dictionary created above in a view view file you should use special global function ```t([key], [return])```

Where parameters are:
 * ```$key = 'Translate me'``` - Any string value to outputted and translated. This key must match in all locales for translation
 * ```$return = true|false```  - If true - the translation will be returned otherwise echoed

So if we have view file(or template view file) ```app/view/test.php``` localized string output will be:

```php
<h1><?php t('Translate me')?></h1>
```

And we don't need to create separate view files for localization.

## Automatic dictionary file generation
You can use special controller ```/i18n/generate``` to perform automatic creation of dictionary file. By default
this file will be located at ```app/i18n/dictionary.php```.

When you enter URL ```/i18n/generate``` system automatically scans all your views/controllers/models for pattern matching ```t([key])```
function and build key value lists for every locale. So you do not have to create this dictionary manually, you add translation function
to your views/controllers/modules and when finished call ```/i18n/generate``` which will create dictionary file for you, what
is left to do is fill in the translation for generated keys.

## Generic rendering of supported locales list
We have added generic controller action ```list``` for this module to simplify of rendering locale switcher in your web-application.
In your template view file (or simple view file), for example ```app/view/index.php``` you can use:

```php
<html>
    <body>
        <?php m('i18n')->render('list')?>
        <?php m()->render()?>
    </body>
</html>
```

This controller action ```list``` will render unordered list of supported locales and special CSS class for styling:

```php
<html>
    <body>
        <ul class="i18n-list">
            <li class="i18n-locale-def i18n-active">
            <a href="/" class=""></a>
        </li><li class="i18n-locale-ru ">
            <a href="/ru"></a>
        </li><li class="i18n-locale-fr ">
            <a href="/fr"></a>
        </li></ul>
    </body>
</html>
```

This is default LESS example to style this locale switcher file:

```less
.i18n-list {/* Locale block parent */

  li {/* Locale block container */

    a { /* Locale link for clicking */
      display: inline-block;
      width:25px;
      height:20px;
      background-repeat: no-repeat;
      background-position: 50% 50%;
      font-size:13px;

        /* Locale inner text block */
      &:before { display:inline-block;  }
    }

    /** Current active locale class */
    &.i18n-active {  }

    /** Default locale inner text block class */
    &.i18n-locale-def a:before { content:"EN"; }
    /** Supported locale inner text block classes */
    &.i18n-locale-ru a:before { content:"RU"; }
    &.i18n-locale-fr a:before {  content:"FR"; }
  }
}
```

## Creating multilingual alternate links
For creating alternate links, that shows, which languages you can get in your web-site, you can use following code:

```php
<html>
    <body>
        <head>
            <?php m('i18n')->render('meta')?>
        </head>
    </body>
</html>
```

Developed by [SamsonOS](http://samsonos.com/)