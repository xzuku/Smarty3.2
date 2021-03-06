<?php

/**
 * Smarty Internal Plugin Smarty Template Compiler Base
 * This file contains the basic classes and methods for compiling Smarty templates with lexer/parser
 *
 * @package Smarty\Compiler\PHP
 * @author  Uwe Tews
 */

/**
 * Main abstract compiler class
 *
 * @package Smarty\Compiler\PHP
 */
class Smarty_Compiler_Template_Php_Compiler extends Smarty_Exception_Magic
{

    /**
     * Context object
     *
     * @var Smarty_Context
     */
    public $context = null;

    /**
     * Template Scope object
     *
     * @var Smarty_Template_scope
     */
    public $template_scope = null;

    /**
     * Lexer class name
     *
     * @var string
     */
    public $lexer_class = '';

    /**
     * Parser class name
     *
     * @var string
     */
    public $parser_class = '';

    /**
     * Lexer object
     *
     * @var object
     */
    public $lex = null;

    /**
     * Parser object
     *
     * @var object
     */
    public $parser = null;

    /**
     * line offset to start of template source
     *
     * @var int
     */
    public $line_offset = 0;

    /**
     * inline template code templates
     *
     * @var array
     */
    public static $merged_inline_content_classes = array();

    /**
     * flag for strip mode
     *
     * @var bool
     */
    public $strip = false;

    /**
     * flag for nocache section
     *
     * @var bool
     */
    public $nocache = false;

    /**
     * flag for nocache tag
     *
     * @var bool
     */
    public $tag_nocache = false;

    /**
     * flag for nocache code not setting $has_nocache_flag
     *
     * @var bool
     */
    public $nocache_nolog = false;

    /**
     * suppress generation of nocache code
     *
     * @var bool
     */
    public $suppressNocacheProcessing = false;

    /**
     * flag when compiling inheritance
     *
     * @var bool
     */
    public $isInheritance = false;

    /**
     * flag when compiling inheritance
     *
     * @var bool
     */
    public $isInheritanceChild = false;

    /**
     * force compilation of complete template as nocache
     * 0 = off
     * 1 = observe nocache flags on template type recompiled
     * 2 = force all code to be nocache
     *
     * @var integer
     */
    public $forceNocache = 0;

    /**
     * compile tag objects
     *
     * @var array
     */
    public static $_tag_objects = array();

    /**
     * tag stack
     *
     * @var array
     */
    public $_tag_stack = array();

    /**
     * file dependencies
     *
     * @var array
     */
    public $file_dependency = array();

    /**
     * template function properties
     *
     * @var array
     */
    public $template_functions = array();

    /**
     * template function compiled code
     *
     * @var array
     */
    public $template_functions_code = array();

    /**
     * block function properties
     *
     * @var array
     */
    public $inheritance_blocks = array();

    /**
     * block function compiled code
     *
     * @var array
     */
    public $inheritance_blocks_code = array();

    /**
     * block name index
     *
     * @var integer
     */
    public $block_name_index = 0;

    /**
     * inheritance block nesting level
     *
     * @var integer
     */
    public $block_nesting_level = 0;

    /**
     * block nesting info
     *
     * @var array
     */
    public $block_nesting_info = array();

    /**
     * compiled footer code
     *
     * @var array
     */
    public $compiled_footer_code = null;

    /**
     * /**
     * plugins loaded by default plugin handler
     *
     * @var array
     */
    public $default_handler_plugins = array();

    /**
     * saved preprocessed modifier list
     *
     * @var mixed
     */
    public $default_modifier_list = null;

    /**
     * suppress template property header code in compiled template
     *
     * @var bool
     */
    public $suppressTemplatePropertyHeader = false;

    /**
     * suppress processing of post filter
     *
     * @var bool
     */
    public $suppressPostFilter = false;

    /**
     * flag if compiled template file shall we written
     *
     * @var bool
     */
    public $write_compiled_code = true;

    /**
     * flag if template does contain nocache code sections
     *
     * @var boolean
     */
    public $has_nocache_code = false;

    /**
     * flag if currently a template function is compiled
     *
     * @var bool
     */
    public $compiles_template_function = false;

    /**
     * called subfuntions from template function
     *
     * @var array
     */
    public $called_template_functions = array();

    /**
     * template functions called nocache
     *
     * @var array
     */
    public $called_nocache_template_functions = array();

    /**
     * content class name
     *
     * @var string
     */
    public $content_class = '';

    /**
     * required plugins
     *
     * @var array
     * @internal
     */
    public $required_plugins = array('compiled' => array(), 'nocache' => array());

    /**
     * flags for used modifier plugins
     *
     * @var array
     */
    public $modifier_plugins = array();

    /**
     * type of already compiled modifier
     *
     * @var array
     */
    public $known_modifier_type = array();

    /**
     * Code object for generated template code
     *
     * @var Smarty_Compiler_Code
     */
    public $template_code = null;

    /**
     * Text buffer holds plain text of template source
     *
     * @var string
     */
    public $text_buffer = '';

    /**
     * Compiled filepath
     */
    public $compiled_filepath = '';

    /**
     * Timestamp when we started compilation
     *
     * @var int
     */
    private $timestamp = 0;

    // TODO check this solution
    public $prefix_code = array();
    public $postfix_code = array();
    public $has_code = false;
    public $has_output = false;

    /**
     * Initialize compiler
     *
     * @param string         $lexer_class  class name
     * @param string         $parser_class class name
     * @param Smarty_Context $context
     * @param string         $compiled_filepath
     */
    public function __construct($lexer_class, $parser_class, Smarty_Context $context, $compiled_filepath)
    {
        $this->compiled_filepath = $compiled_filepath;
        $this->timestamp = time();
        $this->context = $context;
        $this->template_scope = new Smarty_Template_Scope($context);
        // get required plugins
        $this->lexer_class = $lexer_class;
        $this->parser_class = $parser_class;
        // init code buffer
        $this->template_code = new Smarty_Compiler_Code(3);
        $this->template_code->addSourceLineNo(1);

    }


    /**
     * Compiles the template source
     * If the template is not evaluated the compiled template is saved on disk
     *
     * @throws Smarty_Exception in case of compilation errors
     * @throws Exception
     */
    public function compileTemplateSource()
    {
        //TODO
//        $this->isInheritance = $this->isInheritanceChild = $this->context->smarty->is_inheritance_child;
        if (! $this->context->handler->recompiled) {
            if ($this->context->components) {
                // uses real resource for file dependency
                $source = end($this->context->components);
            } else {
                $source = $this->context;
            }
            $this->file_dependency[$this->context->uid] = array($this->context->filepath, $this->context->timestamp, $source->type);
        }
        if ($this->context->smarty->debugging) {
            Smarty_Debug::start_compile($this->context);
        }
        // compile locking
        if ($this->context->smarty->compile_locking && ! $this->context->handler->recompiled) {
            if (is_file($this->compiled_filepath)) {
                $saved_timestamp = filemtime($this->compiled_filepath);
                // touch old compiled template if file did exists
                touch($this->compiled_filepath);
            } else {
                $saved_timestamp = false;
            }
        }
        // call compiler
        try {
            $code = $this->compileTemplate();
        }
        catch (Smarty_Exception $e) {
            // restore old timestamp in case of error
            if ($this->context->smarty->compile_locking && ! $this->context->handler->recompiled && $saved_timestamp) {
                touch($this->compiled_filepath, $saved_timestamp);
            }
            throw $e;
        }
        catch (Exception $e) {
            throw new Smarty_Exception_Runtime(sprintf('An exception has been thrown during the compilation of a template ("%s").', $e->getMessage()), - 1, $this->context->name, $e);
        }
        // compiling succeded
        if (! $this->context->handler->recompiled && $this->write_compiled_code) {
            // write compiled template
            $_filepath = $this->compiled_filepath;
            if ($_filepath === false)
                throw new Smarty_Exception('Invalid filepath for compiled template');
            $this->context->smarty->_writeFile($_filepath, $code);
        }
        if ($this->context->smarty->debugging) {
            Smarty_Debug::end_compile($this->context);
        }
    }

    /**
     * Method to compile a Smarty template
     *
     * @return bool true if compiling succeeded, false if it failed
     */
    public function compileTemplate()
    {
        if ($this->context->smarty->enable_trace && isset(Smarty::$_trace_callbacks['compiler:time:start'])) {
            $this->context->smarty->_triggerTraceCallback('compiler:time:start', array($this));
        }
        // flag for nochache sections
        //        $this->nocache = false;
        $this->tag_nocache = false;
        // reset has nocache code flag
        $this->has_nocache_code = false;
        // check if content class name already predefine
        if (empty($this->content_class)) {
            $this->content_class = '_SmartyTemplate_' . str_replace('.', '_', uniqid('', true));
        }
        $this->context->smarty->_current_file = $saved_filepath = $this->context->filepath;

        // make sure that we don't run into backtrack limit errors
        ini_set('pcre.backtrack_limit', - 1);
        // init the lexer/parser to compile the template
        $this->lex = new $this->lexer_class(null, $this);
        $this->parser = new $this->parser_class($this->lex, $this);


        // get source and run prefilter if required and pass iit to lexer
        if (isset($this->context->smarty->autoload_filters['pre']) || isset($this->context->smarty->_registered['filter']['pre'])) {
            $this->lex->data = $this->context->smarty->runFilter('pre', $this->context->getContent(), $this);
        } else {
            $this->lex->data = $this->context->getContent();
        }
        // call compiler
        $this->doCompile();

        $this->context->filepath = $saved_filepath;
        // free memory
        $this->parser->compiler = null;
        $this->parser = null;
        $this->lex->compiler = null;
        $this->lex = null;
        self::$_tag_objects = array();
        // return compiled code to template object
        // run postfilter if required on compiled template code
        if (! $this->suppressPostFilter && (isset($this->context->smarty->autoload_filters['post']) || isset($this->context->smarty->_registered['filter']['post']))) {
            $this->template_code->buffer = $this->context->smarty->runFilter('post', $this->template_code->buffer, $this->context->smarty, $this);
        }
        if (! $this->suppressTemplatePropertyHeader) {
            $this->content_class = '_SmartyTemplate_' . str_replace('.', '_', uniqid('', true));
            $this->template_code = $this->_createSmartyContentClass($this->context->smarty);
        }
        if ($this->context->smarty->enable_trace && isset(Smarty::$_trace_callbacks['compiler:time:end'])) {
            $this->context->smarty->_triggerTraceCallback('compiler:time:end', array($this));
        }
        return $this->template_code->buffer;
    }

    /**
     * Method to compile a Smarty template
     *
     * @param  mixed $_content template source
     *
     * @return bool  true if compiling succeeded, false if it failed
     */

    /**
     * Method to compile a content block
     *
     * @return bool  true if compiling succeeded, false if it failed
     */
    protected function doCompile()
    {
        /* here is where the compiling takes place. Smarty
          tags in the templates are replaces with PHP code,
          then written to compiled files. */

        if (Smarty_Compiler::$parserdebug) {
            $this->parser->PrintTrace();
            $this->lex->PrintTrace();
        }
        // get tokens from lexer and parse them
        while ($this->lex->yylex()) {
            if (Smarty_Compiler::$parserdebug) {
                echo "<pre>Line {$this->lex->line} Parsing  {$this->parser->yyTokenName[$this->lex->token]} Token " .
                    htmlentities($this->lex->value) . "</pre>";
            }
            $this->parser->doParse($this->lex->token, $this->lex->value);
        }

        // finish parsing process
        $this->parser->doParse(0, 0);
        // check for unclosed tags
        if (count($this->_tag_stack) > 0) {
            // get stacked info
            list($openTag, $_data) = array_pop($this->_tag_stack);
            $this->error("unclosed " . $this->context->smarty->left_delimiter . $openTag . $this->context->smarty->right_delimiter . " tag");
        }
        // return compiled code
        // return str_replace(array("? >\n<?php","? ><?php"), array('',''), $this->parser->retvalue);
        return $this->parser->retvalue;
    }

    /**
     * Compile Tag
     * This is a call back from the lexer/parser
     * It executes the required compile plugin for the Smarty tag
     *
     * @param  string $tag       tag name
     * @param  array  $args      array with tag attributes
     * @param  array  $parameter array with compilation parameter
     *
     * @return string compiled code
     */
    public function compileTag($tag, $args, $parameter = array())
    {
        // $args contains the attributes parsed and compiled by the lexer/parser
        // assume that tag does compile into code, but creates no HTML output
        $this->has_code = true;
        $this->has_output = false;
        // log tag/attributes
        //TODO mit trace back
        if (isset($this->context->smarty->get_used_tags) && $this->context->smarty->get_used_tags) {
            $this->context->smarty->used_tags[] = array($tag, $args);
        }
        // check nocache option flag
        if (in_array("'nocache'", $args) || in_array(array('nocache' => 'true'), $args)
            || in_array(array('nocache' => '"true"'), $args) || in_array(array('nocache' => "'true'"), $args)
        ) {
            $this->tag_nocache = true;
        }
        // tags with _ like load_config need processing
        if (strpos($tag, '_') === false || strpos($tag, 'Internal_') === 0) {
            $_tag = $tag;
        } else {
            $_tag = '';
            $parts = explode('_', $tag);
            foreach ($parts as $part) {
                $_tag .= ucfirst($part);
            }
        }
        // compile the smarty tag (required compile classes to compile the tag are autoloaded)
        if (($_output = $this->compileCoreTag($_tag, $args, $parameter)) === false) {
            if (isset($this->template_functions[$tag])) {
                // template defined by {template} tag
                $args['_attr']['name'] = "'" . $tag . "'";
                $_output = $this->compileCoreTag('Call', $args, $parameter);
            }
        }
        if ($_output !== false) {
            if ($_output !== true) {
                // did we get compiled code
                if ($this->has_code) {
                    // return compiled code
                    return $_output;
                }
            }
            // tag did not produce compiled code
            return '';
        } else {
            // map_named attributes
            if (isset($args['_attr'])) {
                foreach ($args['_attr'] as $attribute) {
                    if (is_array($attribute)) {
                        $args = array_merge($args, $attribute);
                    }
                }
            }
            // not an internal compiler tag
            if (strlen($tag) < 6 || substr($tag, - 5) != 'close') {
                // check if tag is a registered object
                if (isset($this->context->smarty->_registered['object'][$tag]) && isset($parameter['object_method'])) {
                    $method = $parameter['object_method'];
                    if (! in_array($method, $this->context->smarty->_registered['object'][$tag][3]) &&
                        (empty($this->context->smarty->_registered['object'][$tag][1]) || in_array($method, $this->context->smarty->_registered['object'][$tag][1]))
                    ) {
                        return $this->compileCoreTag('Internal_ObjectFunction', $args, $parameter, $tag, $method);
                    } elseif (in_array($method, $this->context->smarty->_registered['object'][$tag][3])) {
                        return $this->compileCoreTag('Internal_ObjectBlockFunction', $args, $parameter, $tag, $method);
                    } else {
                        $this->error('unallowed method "' . $method . '" in registered object "' . $tag . '"', $this->lex->taglineno);
                    }
                }
                // check if tag is registered
                foreach (array(Smarty::PLUGIN_COMPILER, Smarty::PLUGIN_FUNCTION, Smarty::PLUGIN_BLOCK) as $plugin_type) {
                    if (isset($this->context->smarty->_registered['plugin'][$plugin_type][$tag])) {
                        // if compiler function plugin call it now
                        if ($plugin_type == Smarty::PLUGIN_COMPILER) {
                            return $this->compileCoreTag('Internal_PluginCompiler', $args, $parameter, $tag);
                        }
                        // compile registered function or block function
                        if ($plugin_type == Smarty::PLUGIN_FUNCTION || $plugin_type == Smarty::PLUGIN_BLOCK) {
                            return $this->compileCoreTag('Internal_Registered' . ucfirst($plugin_type), $args, $parameter, $tag);
                        }
                    }
                }
                // check plugins from plugins folder
                foreach (Smarty_Compiler::$plugin_search_order as $plugin_type) {
                    if ($plugin_type == Smarty::PLUGIN_COMPILER && $this->context->smarty->_loadPlugin('smarty_compiler_' . $tag) && (! isset($this->context->smarty->security_policy) || $this->context->smarty->security_policy->isTrustedTag($tag, $this))) {
                        $plugin = 'smarty_compiler_' . $tag;
                        if (is_callable($plugin) || class_exists($plugin, false)) {
                            return $this->compileCoreTag('Internal_PluginCompiler', $args, $parameter, $tag);
                        }
                        $this->error("Plugin '{{$tag}...}' not callable", $this->lex->taglineno);
                    } else {
                        if ($function = $this->getPlugin($tag, $plugin_type)) {
                            if (! isset($this->context->smarty->security_policy) || $this->context->smarty->security_policy->isTrustedTag($tag, $this)) {
                                return $this->compileCoreTag('Internal_Plugin' . ucfirst($plugin_type), $args, $parameter, $tag, $function);
                            }
                        }
                    }
                }
                if (is_callable($this->context->smarty->default_plugin_handler_func)) {
                    $found = false;
                    // look for already resolved tags
                    foreach (Smarty_Compiler::$plugin_search_order as $plugin_type) {
                        if (isset($this->default_handler_plugins[$plugin_type][$tag])) {
                            $found = true;
                            break;
                        }
                    }
                    if (! $found) {
                        // call default handler
                        foreach (Smarty_Compiler::$plugin_search_order as $plugin_type) {
                            if ($this->getPluginFromDefaultHandler($tag, $plugin_type)) {
                                $found = true;
                                break;
                            }
                        }
                    }
                    if ($found) {
                        // if compiler function plugin call it now
                        if ($plugin_type == Smarty::PLUGIN_COMPILER) {
                            return $this->compileCoreTag('Internal_PluginCompiler', $args, $parameter, $tag);
                        } else {
                            return $this->compileCoreTag('Internal_Registered' . ucfirst($plugin_type), $args, $parameter, $tag);
                        }
                    }
                }
            } else {
                // compile closing tag of block function
                $base_tag = substr($tag, 0, - 5);
                // check if closing tag is a registered object
                if (isset($this->context->smarty->_registered['object'][$base_tag]) && isset($parameter['object_method'])) {
                    $method = $parameter['object_method'];
                    if (in_array($method, $this->context->smarty->_registered['object'][$base_tag][3])) {
                        return $this->compileCoreTag('Internal_ObjectBlockFunction', $args, $parameter, $tag, $method);
                    } else {
                        $this->error('unallowed closing tag method "' . $method . '" in registered object "' . $base_tag . '"', $this->lex->taglineno);
                    }
                }
                // registered compiler plugin ?
                if (isset($this->context->smarty->_registered['plugin'][Smarty::PLUGIN_COMPILER][$tag])) {
                    return $this->compileCoreTag('Internal_PluginCompilerclose', $args, $parameter, $tag);
                }
                // registered block tag ?
                if (isset($this->context->smarty->_registered['plugin'][Smarty::PLUGIN_BLOCK][$base_tag]) || isset($this->default_handler_plugins[Smarty::PLUGIN_BLOCK][$base_tag])) {
                    return $this->compileCoreTag('Internal_RegisteredBlock', $args, $parameter, $tag);
                }
                // block plugin?
                if ($function = $this->getPlugin($base_tag, Smarty::PLUGIN_BLOCK)) {
                    return $this->compileCoreTag('Internal_PluginBlock', $args, $parameter, $tag, $function);
                }
                if ($this->context->smarty->_loadPlugin('smarty_compiler_' . $tag)) {
                    return $this->compileCoreTag('Internal_PluginCompilerclose', $args, $parameter, $tag);
                }
                $this->error("Plugin '{{$tag}...}' not callable", $this->lex->taglineno);
            }
            $this->error("unknown tag '{{$tag}...}'", $this->lex->taglineno);
        }
    }

    /**
     * lazy loads internal compile plugin for tag and calls the compile method
     * compile objects cached for reuse.
     * class name format:  Smarty_Compiler_Template_Php_Tag_TagName
     *
     * @param  string $tag    tag name
     * @param  array  $args   list of tag attributes
     * @param  mixed  $param1 optional parameter
     * @param  mixed  $param2 optional parameter
     * @param  mixed  $param3 optional parameter
     *
     * @return string compiled code
     */
    public function compileCoreTag($tag, $args, $param1 = null, $param2 = null, $param3 = null)
    {
        // re-use object if already exists
        if (isset(self::$_tag_objects[$tag])) {
            // compile this tag
            return self::$_tag_objects[$tag]->compile($args, $this, $param1, $param2, $param3);
        }
        // check if tag allowed by security
        if (! isset($this->context->smarty->security_policy) || $this->context->smarty->security_policy->isTrustedTag($tag, $this)) {
            $class = 'Smarty_Compiler_Template_Php_Tag_' . $tag;
            if (! class_exists($class, true)) {
                if (substr($tag, - 5) == 'close') {
                    $base_class = substr($tag, 0, - 5);
                    if (! class_exists($base_class, true)) {
                        return false;
                    }
                } else {
                    return false;
                }
            }
            self::$_tag_objects[$tag] = new $class;
            // compile this tag
            return self::$_tag_objects[$tag]->compile($args, $this, $param1, $param2, $param3);
        }
        // no internal compile plugin for this tag
        return false;
    }

    /**
     * Compile code for template variable
     *
     * @param  string $variable name of variable
     *
     * @return string code
     */
    public function compileVariable($variable)
    {
        if (strpos($variable, '(') === false) {
            // not a variable variable
            $var = trim($variable, '\'"');
            if (null != $_var = $this->context->smarty->_getVariable($var, $this->template_scope, true, false)) {
                $this->tag_nocache = $this->tag_nocache | $_var->nocache;
            }
        } else {
            $var = '{' . $variable . '}';
        }

        return '$_scope->_tpl_vars->' . $var . '->value';
    }

    /**
     * Compile code for text output
     *
     * @return bool if text buffer not empty
     */
    public function compileFlushText()
    {
        if (! empty($this->text_buffer)) {
            $this->template_code->addSourceLineNo($this->lex->taglineno);
            if ($this->strip) {
                $this->template_code->php('echo ')->string(preg_replace('![\t ]*[\r\n]+[\t ]*!', '', $this->text_buffer))->raw(";\n");
            } else {
                $this->template_code->php('echo ')->string($this->text_buffer)->raw(";\n");
            }
            $this->text_buffer = '';
        } else {
            return false;
        }
    }

    /**
     * Check for plugins and return function name
     *
     * @param  string $plugin_name name of plugin or function
     * @param  string $plugin_type type of plugin
     *
     * @return string call name of function
     */
    public function getPlugin($plugin_name, $plugin_type)
    {
        $function = null;
        if ($this->context->caching && ($this->nocache || $this->tag_nocache)) {
            if (isset($this->required_plugins['nocache'][$plugin_name][$plugin_type])) {
                $function = $this->required_plugins['nocache'][$plugin_name][$plugin_type]['function'];
            } elseif (isset($this->required_plugins['compiled'][$plugin_name][$plugin_type])) {
                $this->required_plugins['nocache'][$plugin_name][$plugin_type] = $this->required_plugins['compiled'][$plugin_name][$plugin_type];
                $function = $this->required_plugins['nocache'][$plugin_name][$plugin_type]['function'];
            }
        } else {
            if (isset($this->required_plugins['compiled'][$plugin_name][$plugin_type])) {
                $function = $this->required_plugins['compiled'][$plugin_name][$plugin_type]['function'];
            } elseif (isset($this->required_plugins['nocache'][$plugin_name][$plugin_type])) {
                $this->required_plugins['compiled'][$plugin_name][$plugin_type] = $this->required_plugins['nocache'][$plugin_name][$plugin_type];
                $function = $this->required_plugins['compiled'][$plugin_name][$plugin_type]['function'];
            }
        }
        if (isset($function)) {
            if ($plugin_type == 'modifier') {
                $this->modifier_plugins[$plugin_name] = true;
            }

            return $function;
        }
        // loop through plugin dirs and find the plugin
        $function = 'smarty_' . $plugin_type . '_' . $plugin_name;
        $file = $this->context->smarty->_loadPlugin($function, false);

        if (is_string($file)) {
            if ($this->context->caching && ($this->nocache || $this->tag_nocache)) {
                $this->required_plugins['nocache'][$plugin_name][$plugin_type]['file'] = $file;
                $this->required_plugins['nocache'][$plugin_name][$plugin_type]['function'] = $function;
            } else {
                $this->required_plugins['compiled'][$plugin_name][$plugin_type]['file'] = $file;
                $this->required_plugins['compiled'][$plugin_name][$plugin_type]['function'] = $function;
            }
            if ($plugin_type == 'modifier') {
                $this->modifier_plugins[$plugin_name] = true;
            }

            return $function;
        }
        if (is_callable($function)) {
            // plugin function is defined in the script
            return $function;
        }

        return false;
    }

    /**
     * Check for plugins by default plugin handler
     *
     * @param  string $tag         name of tag
     * @param  string $plugin_type type of plugin
     *
     * @return boolean true if found
     */
    public function getPluginFromDefaultHandler($tag, $plugin_type)
    {
        $callback = null;
        $script = null;
        $cacheable = true;
        $result = call_user_func_array(
            $this->context->smarty->default_plugin_handler_func, array($tag, $plugin_type, $this->context->smarty, &$callback, &$script, &$cacheable)
        );
        if ($result) {
            $this->tag_nocache = $this->tag_nocache || ! $cacheable;
            if ($script !== null) {
                if (is_file($script)) {
                    if ($this->context->caching && ($this->nocache || $this->tag_nocache)) {
                        $this->required_plugins['nocache'][$tag][$plugin_type]['file'] = $script;
                        $this->required_plugins['nocache'][$tag][$plugin_type]['function'] = $callback;
                    } else {
                        $this->required_plugins['compiled'][$tag][$plugin_type]['file'] = $script;
                        $this->required_plugins['compiled'][$tag][$plugin_type]['function'] = $callback;
                    }
                    include_once $script;
                } else {
                    $this->error("Default plugin handler: Returned script file \"{$script}\" for \"{$tag}\" not found");
                }
            }
            if (! is_string($callback) && ! (is_array($callback) && is_string($callback[0]) && is_string($callback[1]))) {
                $this->error("Default plugin handler: Returned callback for \"{$tag}\" must be a static function name or array of class and function name");
            }
            if (is_callable($callback)) {
                $this->default_handler_plugins[$plugin_type][$tag] = array($callback, true, array());

                return true;
            } else {
                $this->error("Default plugin handler: Returned callback for \"{$tag}\" not callable");
            }
        }

        return false;
    }

    /**
     * Inject inline code for nocache template sections
     * This method gets the content of each template element from the parser.
     * If the content is compiled code and it should be not cached the code is injected
     * into the rendered output.
     *
     * @param  string  $tagCode code of template element
     * @param  boolean $is_code true if content is compiled code
     *
     * @return string  content
     */
    public function nocacheCode($tagCode, $is_code)
    {
        // If the template is not evaluated and we have a nocache section and or a nocache tag
        if ($is_code && (! empty($this->prefix_code) || ! empty($this->postfix_code) || ! empty($tagCode->buffer))) {

            // generate replacement code
            $make_nocache_code = $this->nocache || $this->tag_nocache || $this->forceNocache == 2;
            if ((! ($this->context->handler->recompiled) || $this->forceNocache) && $this->context->caching && ! $this->suppressNocacheProcessing &&
                ($make_nocache_code || $this->nocache_nolog)
            ) {
                if ($make_nocache_code) {
                    $this->has_nocache_code = true;
                }
                $code = new Smarty_Compiler_Code();
                $code->iniTagCode($this);

                foreach ($this->prefix_code as $prefix_code) {
                    $code->mergeCode($prefix_code);
                }
                $code->mergeCode($tagCode);
                $this->template_code->php("echo \"/*%%SmartyNocache%%*/" . str_replace(array("^#^", "^##^"), array('"', '$'), addcslashes($code->buffer, "\0\t\"\$\\")) . "/*/%%SmartyNocache%%*/\";\n");
                foreach ($this->postfix_code as $postfix_code) {
                    $this->template_code->mergeCode($postfix_code);
                }
                // make sure we include modifier plugins for nocache code
                foreach ($this->modifier_plugins as $plugin_name => $dummy) {
                    if (isset($this->required_plugins['compiled'][$plugin_name]['modifier'])) {
                        $this->required_plugins['nocache'][$plugin_name]['modifier'] = $this->required_plugins['compiled'][$plugin_name]['modifier'];
                    }
                }
            } else {
                foreach ($this->prefix_code as $prefix_code) {
                    $this->template_code->mergeCode($prefix_code);
                }

                $this->template_code->mergeCode($tagCode);

                foreach ($this->postfix_code as $postfix_code) {
                    $this->template_code->mergeCode($postfix_code);
                }
            }
        } else {
            $this->template_code->mergeCode($tagCode);
        }
        $this->prefix_code = array();
        $this->postfix_code = array();
        $this->modifier_plugins = array();
        $this->suppressNocacheProcessing = false;
        $this->tag_nocache = false;
        $this->nocache_nolog = false;

        return;
    }

    /**
     * display compiler error messages
     * If parameter $args is empty it is a parser detected syntax error.
     * In this case the parser is called to obtain information about expected tokens.
     * If parameter $msg contains a string this is used as error message
     *
     * @param  string $msg  individual error message or null
     * @param  string $line line-number
     *
     * @throws Smarty_Exception_Compiler when an unexpected token is found
     */
    public function error($msg = null, $line = null)
    {
        // get template source line which has error
        if (! isset($line)) {
            $line = $this->lex->line;
        } else {
            $line = $line - $this->line_offset;
        }
        throw new Smarty_Exception_Compiler($msg, $line, $this->context, $this->lex);
    }

    /**
     * Create Smarty content class for compiled template files
     *
     * @param  Smarty $tpl_obj    template object
     * @param  bool   $noinstance flag if code for creating instance shall be suppressed
     *
     * @return string
     */
    public function _createSmartyContentClass(Smarty $tpl_obj, $noinstance = false)
    {
        $template_code = new Smarty_Compiler_Code();
        if (! $noinstance) {
            $template_code->php("<?php ");
        }
        $template_code->php("/* Smarty version " . Smarty::SMARTY_VERSION . ", created on " . strftime("%Y-%m-%d %H:%M:%S") . " compiled from \"{$this->context->filepath}\" */")->newline();
        $template_code->php("if (!class_exists('{$this->content_class}',false)) {")->newline()->indent();
        $template_code->php("class {$this->content_class} extends Smarty_Template" . ($this->isInheritance ? '_Inheritance' : '') . " {")->newline()->indent();
        $template_code->php("public \$version = '" . Smarty::SMARTY_VERSION . "';")->newline();
        $template_code->php("public \$has_nocache_code = " . ($this->has_nocache_code ? 'true' : 'false') . ";")->newline();
        $template_code->php("public \$filepath = '{$this->compiled_filepath}';")->newline();
        $template_code->php("public \$timestamp = {$this->timestamp};")->newline();
        if ($this->isInheritanceChild) {
            $template_code->php("public \$is_inheritance_child = true;")->newline();
        }
        if (! empty($tpl_obj->cached_subtemplates)) {
            $template_code->php("public \$cached_subtemplates = ")->repr($tpl_obj->cached_subtemplates, false)->raw(';')->newline();
        }
        if (! $noinstance) {
            $template_code->php("public \$file_dependency = ")->repr($this->file_dependency, false)->raw(';')->newline();
        }
        if (! empty($this->required_plugins['compiled'])) {
            $plugins = array();
            foreach ($this->required_plugins['compiled'] as $tmp) {
                foreach ($tmp as $data) {
                    $plugins[$data['file']] = $data['function'];
                }
            }
            $template_code->php("public \$required_plugins = ")->repr($plugins, false)->raw(';')->newline();
        }

        if (! empty($this->required_plugins['nocache'])) {
            $plugins = array();
            foreach ($this->required_plugins['nocache'] as $tmp) {
                foreach ($tmp as $data) {
                    $plugins[$data['file']] = $data['function'];
                }
            }
            $template_code->php("public \$required_plugins_nocache = ")->repr($plugins, false)->raw(';')->newline();
        }

        if (! empty($this->template_functions)) {
            $template_code->php("public \$template_functions = ")->repr($this->template_functions, false)->raw(';')->newline();
        }
        if (! empty($this->inheritance_blocks)) {
            $template_code->php("public \$inheritance_blocks = ")->repr($this->inheritance_blocks, false)->raw(';')->newline();
        }
        if (! empty($this->called_nocache_template_functions)) {
            $template_code->php("public \$called_nocache_template_functions = ")->repr($this->called_nocache_template_functions, false)->raw(';')->newline();
        }
        $template_code->newline()->newline()->php("function _renderTemplate (\$_scope) {")->newline()->indent();
        $template_code->php("ob_start();")->newline();
        $template_code->mergeCode($this->template_code);
        if (! empty($this->compiled_footer_code)) {
            $template_code->buffer .= implode('', $this->compiled_footer_code);
        }
        $template_code->php("return ob_get_clean();")->newline();
        $template_code->outdent()->php('}')->newline()->newline();
        foreach ($this->template_functions_code as $code) {
            $template_code->mergeCode($code)->newline();
        }
        foreach ($this->inheritance_blocks_code as $code) {
            $template_code->mergeCode($code)->newline();
        }
        $template_code->php("function _getSourceInfo () {")->newline()->indent();
        $template_code->php("return ")->repr($template_code->traceback)->raw(";")->newline();
        $template_code->outdent()->php('}')->newline();

        $template_code->outdent()->php('}')->newline();
        $template_code->outdent()->php('}')->newline();
        if (! $noinstance) {
            foreach (self::$merged_inline_content_classes as $key => $inlinetpl_obj) {
                $template_code->mergeCode($inlinetpl_obj['code']);
                unset(self::$merged_inline_content_classes[$key], $inlinetpl_obj);
            }
            $template_code->php("\$template_class_name = '{$this->content_class}';")->newline();
        }

        return $template_code;
    }

}
