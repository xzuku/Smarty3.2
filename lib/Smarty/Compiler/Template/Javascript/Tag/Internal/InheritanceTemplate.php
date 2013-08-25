<?php

/**
 * Smarty Internal Compile Call Of Inheritance Templates
 *
 * Compiles code to load inheritance child and parent template
 *
 *
 * @package Compiler
 * @author Uwe Tews
 */

/**
 * Smarty Internal Compile Call Of Inheritance Templates Class
 *
 *
 * @package Compiler
 */
class Smarty_Compiler_Template_Javascript_Tag_Internal_InheritanceTemplate extends Smarty_Compiler_Template_Javascript_Tag
{

    /**
     * Attribute definition: Overwrites base class.
     *
     * @var array
     * @see $tpl_obj
     */
    public $required_attributes = array('file');
    public $option_flags = array('child');

    /**
     * Compiles code for calling inheritance templates
     *
     * @param  array  $args      array with attributes from parser
     * @param  object $compiler  compiler object
     * @param  array  $parameter array with compilation parameter
     * @param  string $tag       name of block plugin
     * @param  string $function  PHP function name
     * @return string compiled code
     */
    public function compile($args, $compiler, $parameter, $tag, $function)
    {
        // check and get attributes
        $_attr = $this->getAttributes($compiler, $args);
        $_caching = Smarty::CACHING_OFF;
        // set inheritance flags
        $compiler->isInheritance = $compiler->isInheritanceChild = true;
        // parents must not create cache files
        if ($compiler->caching) {
            $_caching = Smarty::CACHING_NOCACHE_CODE;
        }
        $file = realpath(trim($_attr['file'], "'"));

        $this->iniTagCode($compiler);

        if ($_attr['child'] === true) {
            $this->php("\$compiled_obj = \$this->_getInheritanceTemplate ('{$file}', \$_smarty_tpl->cache_id, \$_smarty_tpl->compile_id, {$_caching}, (isset(\$tpl) ? \$tpl : \$_smarty_tpl), true);")->newline();
            $this->php("\$compiled_obj->getRenderedTemplate\$_smarty_tpl, \$_scope);")->newline();
        } else {
            $this->php("\$compiled_obj = \$this->_getInheritanceTemplate ('{$file}', \$_smarty_tpl->cache_id, \$_smarty_tpl->compile_id, {$_caching}, (isset(\$tpl) ? \$tpl : \$_smarty_tpl));")->newline();
            $this->php("echo \$compiled_obj->getRenderedTemplate(\$_smarty_tpl, \$_scope);")->newline();
        }
        $compiler->has_code = true;

        return $this->returnTagCode($compiler);
    }

}