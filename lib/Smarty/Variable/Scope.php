<?php
/**
 * Smarty Variable Scope
 *
 * This file contains the Class for a variable scope
 *
 *
 * @package Template
 * @author Uwe Tews
 */

/**
 * class for a variable scope
 *
 * This class holds all assigned variables
 * The special property ___attributes is used to store control information
 *
 */
class Smarty_Variable_Scope
{

    /**
     * constructor
     */
    public function __construct()
    {
    }

    /**
     * Set a variable in a variable without checking clone status
     *
     * @param  string $varname name of variable
     * @param  Smarty_Variable $data variable object
     */
    public function setVariable($varname, $data) {
        $this->$varname = $data;
    }

    /**
     * magic __get function called at access of unknown variable
     *
     * @param  string $varname name of variable
     * @return mixed  Smarty_Variable object | null
     */
    public function __get($varname)
    {
        return $this->$varname = Smarty_Template::$call_stack[0]->tpl_obj->getVariable($varname, Smarty_Template::$call_stack[0]->parent);
    }

     /**
    public function __destruct()
    {
    }
     */

}
