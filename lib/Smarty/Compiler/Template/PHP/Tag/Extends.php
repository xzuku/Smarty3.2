<?php

/**
 * Smarty Internal Plugin Compile extend
 *
 * Compiles the {extends} tag
 *
 *
 * @package Compiler
 * @author Uwe Tews
 */

/**
 * Smarty Internal Plugin Compile extend Class
 *
 *
 * @package Compiler
 */
class Smarty_Compiler_Template_Php_Tag_Extends extends Smarty_Compiler_Template_Php_Tag
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
    public $shorttag_order = array('file');

    /**
     * Compiles code for the {extends} tag
     *
     * @param  array $args     array with attributes from parser
     * @param  object $compiler compiler object
     * @return string compiled code
     */
    public function compile($args, $compiler)
    {
        // do not compile tag if template is recompiled to create nocache {block} code
        if ($compiler->nocache) {
            $compiler->has_code = false;

            return true;
        }
        // set inheritance flags
        $compiler->isInheritance = $compiler->isInheritanceChild = true;
        // check and get attributes
        $_attr = $this->getAttributes($compiler, $args);
        if ($_attr['nocache'] === true) {
            $compiler->error('nocache option not allowed', $compiler->lex->taglineno);
        }
        $_caching = Smarty::CACHING_OFF;
        // parents must not create cache files
        if ($compiler->caching) {
            $_caching = Smarty::CACHING_NOCACHE_CODE;
        }

        $this->iniTagCode($compiler);

        $this->php("ob_get_clean();")->newline();
        $this->php("\$compiled_obj = \$this->_getInheritanceTemplate ({$_attr['file']}, \$_smarty_tpl->cache_id, \$_smarty_tpl->compile_id, {$_caching}, \$_smarty_tpl);")->newline();
        $this->php("echo \$compiled_obj->getRenderedTemplate(\$_smarty_tpl, \$_scope);")->newline();

        $compiler->compiled_footer_code[] = $this->buffer;
        $this->buffer = '';

        // code for grabbing all output of child template which must be dropped
        $this->php("ob_start();")->newline();
//      TODO remove
//        $this->php("\$this->is_child = true;")->newline();
        $compiler->has_code = true;

        return $this->returnTagCode($compiler);
    }

}
