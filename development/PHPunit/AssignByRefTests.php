<?php
/**
 * Smarty PHPunit tests assignByRef methode
 *
 * @package PHPunit
 * @author Uwe Tews
 */

/**
 * class for assignByRef tests
 */
class AssignByRefTests extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        // assignByRef is now deprcated and only supported by SmartyBC31
        $this->smarty = SmartyTests::$smartyBC31;
        $this->smartyBC = SmartyTests::$smartyBC;
        SmartyTests::init();
    }

    public static function isRunnable()
    {
        return true;
    }

    /**
     * test simple assignByRef
     */
    public function testSimpleAssignByRef()
    {
        $bar = 'bar';
        $this->smarty->assignByRef('foo', $bar);
        $bar = 'newbar';
        $this->assertEquals('newbar', $this->smarty->fetch('eval:{$foo}'));
    }

    /**
     * test Smarty2 assign_By_Ref
     */
    public function testSmarty2AssignByRef()
    {
        $bar = 'bar';
        $this->smartyBC->assign_by_ref('foo', $bar);
        $bar = 'newbar';
        $this->assertEquals('newbar', $this->smartyBC->fetch('eval:{$foo}'));
    }

    /**
     * test Smarty2's behaviour of assign_By_Ref (Issue 88)
     */
    // TODO   das geht so nicht mehr
    public function testSmarty2AssignByRef2()
    {
        $bar = 'bar';
        $this->smartyBC->assign_by_ref('foo', $bar);
        $this->smartyBC->fetch('eval:{$foo = "newbar"}');
        $this->assertEquals('newbar', $bar);
    }
}
