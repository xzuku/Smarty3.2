<?php
/**
 * Smarty PHPunit tests of filter
 *
 * @package PHPunit
 * @author Rodney Rehm
 */

/**
 * class for filter tests
 */
class MuteExpectedErrorsTests extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->smarty = SmartyTests::$smarty;
        $this->smartyBC = SmartyTests::$smartyBC;
        SmartyTests::init();
    }

    public static function isRunnable()
    {
        return false;
    }

    protected $_errors = array();

    public function error_handler($errno, $errstr, $errfile, $errline, $errcontext)
    {
        $this->_errors[] = $errfile . ' line ' . $errline;
    }

    public function testMuted()
    {
        $this->_errors = array();
        set_error_handler(array($this, 'error_handler'));
        Smarty::muteExpectedErrors();

        $this->smarty->clearCache('default.tpl');
        $this->smarty->clearCompiledTemplate('default.tpl');
        $this->smarty->fetch('default.tpl');

        $this->assertEquals($this->_errors, array());

        @filemtime('ckxladanwijicajscaslyxck');
        $error = array(__FILE__ . ' line ' . (__LINE__ - 1));
        $this->assertEquals($this->_errors, $error);

        Smarty::unmuteExpectedErrors();
        restore_error_handler();
    }

    public function testUnmuted()
    {
        $_IS_WINDOWS = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

        $this->_errors = array();
        set_error_handler(array($this, 'error_handler'));

        $this->smarty->clearCache('default.tpl');
        $this->smarty->clearCompiledTemplate('default.tpl');
        $this->smarty->fetch('default.tpl');
        $this->assertEquals($_IS_WINDOWS ? 2 : 4, count($this->_errors));

        @filemtime('ckxladanwijicajscaslyxck');
        $error = array(__FILE__ . ' line ' . (__LINE__ - 1));
        $this->assertEquals($_IS_WINDOWS ? 3 : 5, count($this->_errors));

        restore_error_handler();
    }

    public function testMutedCaching()
    {
        $this->_errors = array();
        set_error_handler(array($this, 'error_handler'));
        Smarty::muteExpectedErrors();

        $this->smarty->caching = true;
        $this->smarty->clearCache('default.tpl');
        $this->smarty->clearCompiledTemplate('default.tpl');
        $this->smarty->fetch('default.tpl');

        $this->assertEquals($this->_errors, array());

        @filemtime('ckxladanwijicajscaslyxck');
        $error = array(__FILE__ . ' line ' . (__LINE__ - 1));
        $this->assertEquals($error, $this->_errors);

        Smarty::unmuteExpectedErrors();
        restore_error_handler();
    }

    public function testUnmutedCaching()
    {
        $_IS_WINDOWS = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $this->_errors = array();
        set_error_handler(array($this, 'error_handler'));

        $this->smarty->caching = true;
        $this->smarty->clearCache('default.tpl');
        $this->smarty->clearCompiledTemplate('default.tpl');
        $this->smarty->fetch('default.tpl');

        $this->assertEquals($_IS_WINDOWS ? 4 : 5, count($this->_errors));

        @filemtime('ckxladanwijicajscaslyxck');
        $error = array(__FILE__ . ' line ' . (__LINE__ - 1));
        $this->assertEquals($_IS_WINDOWS ? 5 : 6, count($this->_errors));

        restore_error_handler();
    }
}
