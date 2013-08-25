<?php

/**
 * Smarty Internal Plugin Compile Config Load
 *
 * Compiles the {config load} tag
 *
 *
 * @package Compiler
 * @author Uwe Tews
 */

/**
 * Smarty Internal Plugin Compile Config Load Class
 *
 *
 * @package Compiler
 */
class Smarty_Compiler_Template_Javascript_Tag_ConfigLoad extends Smarty_Compiler_Template_Javascript_Tag
{

    /**
     * Attribute definition: Overwrites base class.
     *
     * @var array
     * @see $tpl_obj
     */
    public $required_attributes = array('file');

    /**
     * Attribute definition: Overwrites base class.
     *
     * @var array
     * @see $tpl_obj
     */
    public $shorttag_order = array('file', 'section');

    /**
     * Attribute definition: Overwrites base class.
     *
     * @var array
     * @see $tpl_obj
     */
    public $optional_attributes = array('section', 'scope');

    /**
     * Compiles code for the {config_load} tag
     *
     * @param  array  $args     array with attributes from parser
     * @param  object $compiler compiler object
     * @return string compiled code
     */
    public function compile($args, $compiler)
    {
        static $_is_legal_scope = array('local' => true, 'parent' => true, 'root' => true, 'global' => true);
        // check and get attributes
        $_attr = $this->getAttributes($compiler, $args);

        if ($_attr['nocache'] === true) {
            $compiler->error('nocache option not allowed', $compiler->lex->taglineno);
        }

        // save possible attributes
        $conf_file = $_attr['file'];
        if (isset($_attr['section'])) {
            $section = $_attr['section'];
        } else {
            $section = 'null';
        }
        $scope = 'local';
        // scope setup
        if (isset($_attr['scope'])) {
            $_attr['scope'] = trim($_attr['scope'], "'\"");
            if (isset($_is_legal_scope[$_attr['scope']])) {
                $scope = $_attr['scope'];
            } else {
                $compiler->error('illegal value for "scope" attribute', $compiler->lex->taglineno);
            }
        }
        // create config object
        $this->iniTagCode($compiler);

        $this->php("\$_smarty_tpl->configLoad($conf_file, $section, '{$scope}');")->newline();

        return $this->returnTagCode($compiler);
    }

}