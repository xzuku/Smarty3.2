<?php

/**
 * Smarty Template
 *
 * This file contains the basic shared methods for precessing content of compiled and cached templates
 *
 *
 * @package Smarty\Template
 * @author Uwe Tews
 */

/**
 * Class Smarty Internal Template
 *
 * For backward compatibility to Smarty 3.1
 */
class Smarty_Internal_Template extends Smarty_Variable_Methods
{
}

/**
 * Class Smarty Template
 *
 *
 * @package Smarty\Template
 */
class  Smarty_Template extends Smarty_Internal_Template
{

    /**
     * flag if class is valid
     * @var boolean
     * @internal
     */
    public $isValid = false;

    /**
     * flag if class was updated
     * @var boolean
     * @internal
     */
    public $isUpdated = false;

    /**
     * Smarty object
     * @var Smarty
     */
    public $smarty = null;

    /**
     * Parent object
     * @var Smarty|Smarty_Data|Smarty_Template
     */
    public $parent = null;

    /**
     * Source object
     * @var Smarty_Resource_Source_File
     */
    public $source = null;

    /**
     * Local variable scope
     * @var Smarty_Variable_Scope
     */
    public $_tpl_vars = null;

    /**
     * Declare the type template variable storage
     *
     * @internal
     * @var Smarty::IS_DATA
     */
    public $_usage = Smarty::IS_TEMPLATE;

    /**
     * flag if class is from cache file
     * @var boolean
     * @internal
     */
    public $is_cache = false;

    /**
     * flag if content does contain nocache code
     * @var boolean
     * @internal
     */
    public $has_nocache_code = false;

    /**
     * saved cache lifetime
     * @var int
     * @internal
     */
    public $cache_lifetime = false;

    /**
     * names of cached subtemplates
     * @var array
     * @internal
     */
    public $cached_subtemplates = array();

    /**
     * required plugins
     * @var array
     * @internal
     */
    public $required_plugins = array();

    /**
     * required plugins of nocache code
     * @var array
     * @internal
     */
    public $required_plugins_nocache = array();

    /**
     * template function properties
     *
     * @var array
     */
    public $tpl_obj_functions = array();

    /**
     * template functions called nocache
     * @var array
     */
    public $called_nocache_template_functions = array();

    /**
     * file dependencies
     *
     * @var array
     */
    public $file_dependency = array();

    /**
     * Smarty version class was compiled with
     * @var string
     * @internal
     */
    public $version = '';

    /**
     * flag if content is inheritance child
     *
     * @var bool
     */
    public $is_inheritance_child = false;

    /**
     * Timestamp
     * @var integer
     */
    public $timestamp = null;

    /**
     * resource filepath
     *
     * @var string
     */
    public $filepath = null;

    /**
     * Template Compile Id (Smarty::$compile_id)
     * @var string
     */
    public $compile_id = null;

    /**
     * Flag if caching enabled
     * @var boolean
     */
    public $caching = false;

    /**
     * internal capture runtime stack
     * @var array
     */
    public $_capture_stack = array(0 => array());

    /**
     * call stack
     * @var array
     */
    public static $call_stack = array();

    /**
     * constructor
     *
     * @param Smarty $smarty Smarty object
     * @param Smarty_Source $source source resource
     * @param $filepath
     * @param $timestamp
     */
    public function __construct($smarty, $source, $filepath, $timestamp)
    {
        $this->smarty = $smarty;
        $this->source = $source;
        $this->filepath = $filepath;
        $this->timestamp = $timestamp;
        if (!$this->isValid) {
            // check if class is still valid
            if ($this->version != Smarty::SMARTY_VERSION) {
                // not valid because new Smarty version
                return;
            }
            if ((!$this->is_cache && $this->smarty->compile_check) || ($this->is_cache && ($this->smarty->compile_check === true || $this->smarty->compile_check === Smarty::COMPILECHECK_ON)) && !empty($this->file_dependency)) {
                foreach ($this->file_dependency as $_file_to_check) {
                    if ($_file_to_check[2] == 'file' || $_file_to_check[2] == 'php') {
                        if ($this->source->filepath == $_file_to_check[0]) {
                            // do not recheck current template
                            continue;
                        } else {
                            // file and php types can be checked without loading the respective resource handlers
                            $mtime = @filemtime($_file_to_check[0]);
                        }
                    } elseif ($_file_to_check[2] == 'string') {
                        continue;
                    } else {
                        $source = $this->smarty->_getSourceObject($_file_to_check[0]);
                        $mtime = $source->timestamp;
                    }
                    if (!$mtime || $mtime > $_file_to_check[1]) {
                        // not valid because newer dependent resource/file
                        return;
                    }
                }
            }
            foreach ($this->required_plugins as $file => $call) {
                if (!is_callable($call)) {
                    include $file;
                }
            }
            $this->isValid = true;
        }
        if (!$this->is_cache) {
            if (!empty($this->template_functions) && isset($smarty->parent) && $smarty->parent->_usage == Smarty::IS_SMARTY_TPL_CLONE) {
                $smarty->parent->template_function_chain = $smarty;
            }
        }
    }

    /**
     * get rendered template output from compiled template
     *
     * @param Smarty|Smarty_Data|Smarty_Template $parent     parent object
     * @param Smarty_Variable_Scope $scope variable scope
     * @param int $scope_type
     * @param  boolean $no_output_filter true if output filter shall nit run
     * @param bool $display
     * @throws Exception
     * @return string
     */
    public function getRenderedTemplate($parent, Smarty_Variable_Scope $scope, $scope_type = Smarty::SCOPE_LOCAL, $no_output_filter = true, $display = false)
    {
        $this->smarty->cached_subtemplates = array();
        $level = ob_get_level();
        try {
             if ($this->smarty->debugging) {
                Smarty_Debug::start_render($this->source);
            }
            self::$call_stack[] = array($this,$this->_tpl_vars, $this->parent);
            $this->_tpl_vars = $scope;
            $this->parent = $parent;
            array_unshift($this->_capture_stack, array());
            //
            // render compiled template
            //
            $output = $this->_renderTemplate($scope);
            $restore = array_pop(self::$call_stack);
            $this->_tpl_vars = $restore[1];
            $this->parent = $restore[2];

            // any unclosed {capture} tags ?
            if (isset($this->_capture_stack[0][0])) {
                throw new Smarty_Exception_CaptureError();
            }
            array_shift($this->_capture_stack);
        } catch (Exception $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $e;
        }
//        if ($this->source->recompiled && empty($this->file_dependency[$this->source->uid])) {
//            $this->file_dependency[$this->source->uid] = array($this->source->filepath, $this->source->timestamp, $this->source->type);
//        }
        if ($this->caching) {
            Smarty_Resource_Cache::$creator[0]->_mergeFromCompiled($this);
        }
        if (!$no_output_filter && (isset($this->smarty->autoload_filters['output']) || isset($this->smarty->registered_filters['output']))) {
            $output = $this->smarty->runFilter('output', $output);
        }

        if ($this->smarty->debugging) {
            Smarty_Debug::end_render($this->source);
        }

        return $output;
    }


    /**
     * Template runtime function to call a template function
     *
     * @param  string $name    name of template function
     * @param  Smarty $smarty calling template object
     * @param  Smarty_Variable_Scope $_scope
     * @param  array $params  array with calling parameter
     * @param  string $assign  optional template variable for result
     * @throws Smarty_Exception_Runtime
     * @return bool
     */
    public function _callTemplateFunction($name, $smarty, $_scope, $params, $assign)
    {
        if ($this->is_cache && isset($smarty->cached->template_obj->template_functions[$name])) {
            $content_ptr = $smarty->cached->template_obj;
        } else {
            $ptr = $tpl = $smarty;
            while ($ptr != null && !isset($ptr->compiled->template_obj->template_functions[$name])) {
                $ptr = $ptr->template_function_chain;
                if ($ptr == null && $tpl->parent->_usage == Smarty::IS_SMARTY_TPL_CLONE) {
                    $ptr = $tpl = $tpl->parent;
                }
            }
            if (isset($ptr->compiled->template_obj->template_functions[$name])) {
                $content_ptr = $ptr->compiled->template_obj;
            }
        }
        if (isset($content_ptr)) {
            if (!empty($assign)) {
                ob_start();
            }
            $func_name = "_renderTemplateFunction_{$name}";
            $content_ptr->$func_name($smarty, $_scope, $params);
            if (!empty($assign)) {
                $smarty->assign($assign, ob_get_clean());
            }

            return true;
        }
        throw new Smarty_Exception_Runtime("Call to undefined template function '{$name}'", $smarty);
    }

    /**
     * [util function] counts an array, arrayaccess/traversable or PDOStatement object
     *
     * @param  mixed $value
     * @return int   the count for arrays and objects that implement countable, 1 for other objects that don't, and 0 for empty elements
     */
    public function _count($value)
    {
        if (is_array($value) === true || $value instanceof Countable) {
            return count($value);
        } elseif ($value instanceof IteratorAggregate) {
            // Note: getIterator() returns a Traversable, not an Iterator
            // thus rewind() and valid() methods may not be present
            return iterator_count($value->getIterator());
        } elseif ($value instanceof Iterator) {
            return iterator_count($value);
        } elseif ($value instanceof PDOStatement) {
            return $value->rowCount();
        } elseif ($value instanceof Traversable) {
            return iterator_count($value);
        } elseif ($value instanceof ArrayAccess) {
            if ($value->offsetExists(0)) {
                return 1;
            }
        } elseif (is_object($value)) {
            return count($value);
        }

        return 0;
    }

    /**
     * Template code runtime function to create a local Smarty variable for array assignments
     *
     * @param string $tpl_var template variable name
     * @param Smarty_Variable_Scope $_scope  variable scope
     * @param bool $nocache cache mode of variable
     */
    public function _createLocalArrayVariable($tpl_var, $_scope, $nocache = false)
    {
        if (isset($_scope->{$tpl_var})) {
            $_scope->{$tpl_var} = clone $_scope->{$tpl_var};
// TODO
//        } elseif ($result = $_scope->___attributes->tpl_ptr->getVariable($tpl_var, $_scope->___attributes->tpl_ptr->parent, true, false)) {
//            $_scope->{$tpl_var} = clone $result;
        } else {
            $_scope->{$tpl_var} = new Smarty_Variable(array(), $nocache);

            return;
        }
        $_scope->{$tpl_var}->nocache = $nocache;
        if (!(is_array($_scope->{$tpl_var}->value) || $_scope->{$tpl_var}->value instanceof ArrayAccess)) {
            settype($_scope->{$tpl_var}->value, 'array');
        }
    }


    /**
     * Template code runtime function to get subtemplate content
     *
     * @param  string $template_resource the resource handle of the template file
     * @param  mixed $cache_id         cache id to be used with this template
     * @param  mixed $compile_id       compile id to be used with this template
     * @param  integer $caching          cache mode
     * @param  integer $cache_lifetime   life time of cache data
     * @param  array $data             array with parameter template variables
     * @param  int $scope_type       scope in which {include} should execute
     * @param  Smarty_Variable_Scope $_scope
     * @param  string $content_class    optional name of inline content class
     * @return string                template content
     */
    public function _getSubTemplate($template_resource, $cache_id, $compile_id, $caching, $cache_lifetime, $data, $scope_type, $_scope, $content_class = null)
    {
        if (isset($content_class)) {
            // clone new template object
            $tpl_obj = clone $parent_tpl_obj;
            $tpl_obj->template_resource = $template_resource;
            $tpl_obj->cache_id = $cache_id;
            $tpl_obj->compile_id = $compile_id;
            $tpl_obj->parent = $parent_tpl_obj;

            // instance content class
            $tpl_obj->compiled = new stdclass;
            $tpl_obj->compiled->template_obj = new $content_class($tpl);
            $result = $tpl_obj->compiled->getRenderedTemplate($tpl_obj, $_scope, $scope_type, $data, $no_output_filter);
//            $result = $tpl->compiled->template_obj->_renderTemplate($tpl);
            unset($tpl_obj->_tpl_vars, $tpl_obj);

            return $result;
        } else {
            if ($this->smarty->caching && $caching && $caching != Smarty::CACHING_NOCACHE_CODE) {
                $this->smarty->cached_subtemplates[$template_resource] = array($template_resource, $cache_id, $compile_id, $caching, $cache_lifetime);
            }

            return $this->smarty->fetch($template_resource, $cache_id, $compile_id, $this, false, true, $data, $scope_type, $caching, $cache_lifetime);
        }

    }

    /**
     * [util function] to use either var_export or unserialize/serialize to generate code for the
     * cachevalue optionflag of {assign} tag
     *
     * @param  mixed $var Smarty variable value
     * @throws Smarty_Exception
     * @return string           PHP inline code
     */
    public function _exportCacheValue($var)
    {
        if (is_int($var) || is_float($var) || is_bool($var) || is_string($var) || (is_array($var) && !is_object($var) && !array_reduce($var, array($this, '_checkAarrayCallback')))) {
            return var_export($var, true);
        }
        if (is_resource($var)) {
            throw new Smarty_Exception('Cannot serialize resource');
        }

        return 'unserialize(\'' . serialize($var) . '\')';
    }

    /**
     * callback used by _export_cache_value to check arrays recursively
     *
     * @param  bool $flag    status of previous elements
     * @param  mixed $element array element to check
     * @throws Smarty_Exception
     * @return bool             status
     */
    private function _checkAarrayCallback($flag, $element)
    {
        if (is_resource($element)) {
            throw new Smarty_Exception('Cannot serialize resource');
        }
        $flag = $flag || is_object($element) || (!is_int($element) && !is_float($element) && !is_bool($element) && !is_string($element) && (is_array($element) && array_reduce($element, array($this, '_checkAarrayCallback'))));

        return $flag;
    }

    /**
     * Template code runtime function to load config varibales
     *
     * @param object $tpl_obj calling template object
     */
    public function _loadConfigVars($tpl_obj)
    {
        $ptr = $tpl_obj->parent;
        $this->_loadConfigValuesInScope($tpl_obj, $ptr->_tpl_vars);
        $ptr = $ptr->parent;
        if ($tpl_obj->_tpl_vars->___config_scope == 'parent' && $ptr != null) {
            $this->_loadConfigValuesInScope($tpl_obj, $ptr->_tpl_vars);
        }
        if ($tpl_obj->_tpl_vars->___config_scope == 'root' || $tpl_obj->_tpl_vars->___config_scope == 'global') {
            while ($ptr != null && $ptr->_usage == Smarty::IS_SMARTY_TPL_CLONE) {
                $this->_loadConfigValuesInScope($tpl_obj, $ptr->_tpl_vars);
                $ptr = $ptr->parent;
            }
        }
        if ($tpl_obj->_tpl_vars->___config_scope == 'root') {
            while ($ptr != null) {
                $this->_loadConfigValuesInScope($tpl_obj, $ptr->_tpl_vars);
                $ptr = $ptr->parent;
            }
        }
        if ($tpl_obj->_tpl_vars->___config_scope == 'global') {
            $this->_loadConfigValuesInScope($tpl_obj, Smarty::$_global_tpl_vars);
        }
    }

    /**
     * Template code runtime function to load config varibales into a single scope
     *
     * @param object $tpl_obj  calling template object
     * @param object $tpl_vars variable container of scope
     */
    public function _loadConfigValuesInScope($tpl_obj, $tpl_vars)
    {
        foreach ($this->config_data['vars'] as $var => $value) {
            if ($tpl_obj->config_overwrite || !isset($tpl_vars->$var)) {
                $tpl_vars->$var = $value;
            } else {
                $tpl_vars->$var = array_merge((array)$tpl_vars->{$var}, (array)$value);
            }
        }
        if (isset($this->config_data['sections'][$tpl_obj->_tpl_vars->___config_sections])) {
            foreach ($this->config_data['sections'][$tpl_obj->_tpl_vars->___config_sections]['vars'] as $var => $value) {
                if ($tpl_obj->config_overwrite || !isset($tpl_vars->$var)) {
                    $tpl_vars->$var = $value;
                } else {
                    $tpl_vars->$var = array_merge((array)$tpl_vars->{$var}, (array)$value);
                }
            }
        }
    }

}
