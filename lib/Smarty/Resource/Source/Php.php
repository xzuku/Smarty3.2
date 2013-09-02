<?php

/**
 * Smarty Resource Source PHP File Plugin
 *
 *
 * @package TemplateResources
 * @author Uwe Tews
 * @author Rodney Rehm
 */

/**
 * Smarty Resource Source PHP File Plugin
 *
 * Implements the file system as resource for PHP templates
 *
 *
 * @package TemplateResources
 * @author Uwe Tews
 * @author Rodney Rehm
 */
class Smarty_Resource_Source_Php extends Smarty_Resource_Source_File
{

    /**
     * container for short_open_tag directive's value before executing PHP templates
     * @var string
     */
    protected $short_open_tag;

    /**
     * Create a new PHP Resource
     *
     */
    public function __construct()
    {
        $this->uncompiled = true;
        $this->short_open_tag = ini_get('short_open_tag');
    }

    /**
     * populate Source Object with meta data from Resource
     *
     * @param  Smarty $tpl_obj template object
     * @return void
     */
    public function populate(Smarty $tpl_obj = null)
    {
        $this->filepath = $this->buildFilepath($tpl_obj);

        if ($this->filepath !== false) {
            if (is_object($tpl_obj->security_policy)) {
                $tpl_obj->security_policy->isTrustedResourceDir($this->filepath);
            }

            $this->uid = sha1($this->filepath);
            if ($tpl_obj->compile_check) {
                $this->timestamp = @filemtime($this->filepath);
                $this->exists = !!$this->timestamp;
            }
        }
    }

    /**
     * populate Source Object with timestamp and exists from Resource
     *
     * @return void
     */
    public function populateTimestamp()
    {
        $this->timestamp = @filemtime($this->filepath);
        $this->exists = !!$this->timestamp;
    }

    /**
     * Load template's source from file into current template object
     *
     * @return string           template source
     * @throws Smarty_Exception if source cannot be loaded
     */
    public function getContent()
    {
        if ($this->timestamp) {
            return '';
        }
        throw new Smarty_Exception("Unable to read template {$this->type} '{$this->name}'");
    }

    /**
     * Render and output the template (without using the compiler)
     *
     * @param  Smarty $tpl_obj template object
     * @return void
     * @throws Smarty_Exception if template cannot be loaded or allow_php_templates is disabled
     */
    public function renderUncompiled(Smarty $tpl_obj)
    {
        $_smarty_tpl = $tpl_obj;

        if (!$tpl_obj->allow_php_templates) {
            throw new Smarty_Exception("PHP templates are disabled");
        }
        if (!$this->exists) {
            if ($tpl_obj->parent instanceof Smarty) {
                $parent_resource = " in '{$tpl_obj->parent->template_resource}'";
            } else {
                $parent_resource = '';
            }
            throw new Smarty_Exception("Unable to load template {$this->type} '{$this->name}'{$parent_resource}");
        }

        // prepare variables
        extract($tpl_obj->getTemplateVars());

        // include PHP template with short open tags enabled
        ini_set('short_open_tag', '1');
        include($this->filepath);
        ini_set('short_open_tag', $this->short_open_tag);
    }

}