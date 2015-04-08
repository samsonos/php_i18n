<?php
namespace tests;

class MainTest extends \PHPUnit_Framework_TestCase
{
    /** @var \samsonphp\i18n\i18n Pointer to i18n */
    public $i18n;

    /** Tests init */
    public function setUp()
    {
        // Disable default error output
        \samson\core\Error::$OUTPUT = false;

        $this->i18n = new \samsonphp\i18n\i18n();

    }

    public function testInit(){
        $i18n = $this->i18n;
        //$i18n::$instances = array($this->getMockBuilder('\samson\core\System')->getMock());

//        $this->i18n::$instances = $this->getMockBuilder('\samson\core\System')->getMock();
        $modules = $i18n::$instances;

        $modules['i18n']->resourceMap = $this->getMockBuilder('\samson\core\ResourceMap')->getMock();

        $modules['i18n']->resourceMap->classes = array( __DIR__."/i18n/Dictionary.php" =>  'i18n\i18n\Dictionary');

        $this->i18n->init();
        $testArray = array(
            "en"	=>array(
                "Read More"	=>	"",
                "Share"	=>	"",
            ),
            "fr"	=>array(
                "Read More"	=>	"",
                "Share"	=>	"",
            ),
            "ru"	=>array(
                "Read More"	=>	"",
                "Share"	=>	"",
            ),
            "ua"	=>array(
                "Read More"	=>	"",
                "Share"	=>	"",
            ));

        assertEquals($testArray, $this->i18n->dictionary);

    }

    public function test__generate(){

    }

    public function testCreateDictionary(){

    }

    public function testGetModuleResources(){

    }

    public function test__list(){

    }

    public function test__meta(){

    }

    public function testFindDictionary(){

    }

    public function testTranslate(){

    }
    public function testPlural(){

    }
}

