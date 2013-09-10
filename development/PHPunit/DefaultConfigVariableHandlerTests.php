<?php
/**
 * Smarty PHPunit tests variable variables
 *
 * @package PHPunit
 * @author Rodney Rehm
 */

/**
 * class for variable variables tests
 */
class DefaultConfigVariableHandlerTests extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->smarty = SmartyTests::$smarty;
        SmartyTests::init();
    }

    public static function isRunnable()
    {
        return true;
    }

    public function testVariable()
    {
        $this->smarty->registerDefaultConfigVariableHandler('DefaultConfigVariableHandlerTests_value');
        $tpl = $this->smarty->createTemplate('eval:{#foo#}');
        $this->assertEquals('foo-Template', $tpl->fetch());
        $this->assertEquals('blurp-Template', $tpl->getConfigVars('blurp'));
        $this->assertEquals('baz-Smarty', $this->smarty->getConfigVars('baz'));
    }

    public function testVariableFailIgnore()
    {
        /*
        const UNASSIGNED_IGNORE = 0;
        const UNASSIGNED_NOTICE = 1;
        const UNASSIGNED_EXCEPTION = 2;
        */
        $this->smarty->error_unassigned = Smarty::UNASSIGNED_IGNORE;
        $this->smarty->registerDefaultConfigVariableHandler('DefaultConfigVariableHandlerTests_null');
        $tpl = $this->smarty->createTemplate('eval:{#foo#}');
        $this->assertEquals('', $tpl->fetch());
    }

    protected $_errors = array();

    public function error_handler($errno, $errstr, $errfile, $errline, $errcontext)
    {
        $this->_errors[] = $errstr;
    }

    public function testVariableFailNotice()
    {
        $this->_errors = array();
        set_error_handler(array($this, 'error_handler'));

        $this->smarty->error_unassigned = Smarty::UNASSIGNED_NOTICE;
        $this->smarty->registerDefaultConfigVariableHandler('DefaultConfigVariableHandlerTests_null');
        $tpl = $this->smarty->createTemplate('eval:{#foo#}');
        $this->assertEquals('', $tpl->fetch());

        $this->assertEquals(1, count($this->_errors));
        $this->assertEquals("Unassigned config variable 'foo'", $this->_errors[0]);

        restore_error_handler();
    }

    public function testVariableFailException()
    {
        $this->smarty->error_unassigned = Smarty::UNASSIGNED_EXCEPTION;
        $this->smarty->registerDefaultConfigVariableHandler('DefaultConfigVariableHandlerTests_null');
        $tpl = $this->smarty->createTemplate('eval:{#foo#}');
        try {
            $tpl->fetch();
        } catch (Smarty_Exception $e) {
            $this->assertEquals("Unassigned config variable 'foo'", $e->getMessage());

            return;
        }

        $this->fail('Exception not thrown');
    }
}

function DefaultConfigVariableHandlerTests_value($name, &$value, $context)
{
    $value = $name . '-' . ($context->_usage == Smarty::IS_SMARTY_TPL_CLONE ? 'Template' : 'Smarty');

    return true;
}

function DefaultConfigVariableHandlerTests_null($name, &$value, $context)
{
    return false; // not found
}
