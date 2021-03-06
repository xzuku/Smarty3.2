/**
* Smarty Internal Plugin Template Parser
*
* This is the template parser
* 
* 
* @package Smarty
* @subpackage Compiler
* @author Uwe Tews
*/
%stack_size 500
%name TP_
%declare_class {class Smarty_Compiler_Template_PHP_Parser extends Smarty_Exception_Magic}
%include_class
{
    const Err1 = "Security error: Call to private object member not allowed";
    const Err2 = "Security error: Call to dynamic object member not allowed";
    const Err3 = "PHP in template not allowed. Use SmartyBC to enable it";
    // states whether the parse was successful or not
    public $successful = true;
    public $retvalue = 0;
    public  $lex = null;
    public  $compiler = null;
    public  $prefix_number = 0;
    public  $block_nesting_level = 0;
    private $internalError = false;
    private $strip = false;
    private $text_is_php = false;
    private $is_xml = false;
    private $db_quote_code_buffer = '';
    private $asp_tags = null;
    private $php_handling = null;
    private $security = null;
    private $opMap = array('and' => '&&', 'or' => '||', 'eq' => '==', 'neq' => '!=', 'ne' => '!=', 'gt' => '>',
                            'lt' => '<', 'ge' => '>=', 'gte' => '>=', 'le' => '<=' , 'lte' => '<=', 'mod' => '%',
                            'not' => '!');

    function __construct($lex, $compiler) {
        $this->lex = $lex;
        $this->lex->parser_class = get_class($this);
        $this->compiler = $compiler;
        $this->compiler->prefix_code = array();
        if ($this->security = isset($this->compiler->context->smarty->security_policy)) {
            $this->php_handling = $this->compiler->context->smarty->security_policy->php_handling;
        } else {
            $this->php_handling = $this->compiler->context->smarty->php_handling;
        }
       $this->asp_tags = (ini_get('asp_tags') != '0');
    }
}


%token_prefix TP_

%parse_accept
{
    $this->successful = !$this->internalError;
    $this->internalError = false;
    $this->retvalue = $this->_retvalue;
    //echo $this->retvalue."\n\n";
}

%syntax_error
{
    $this->internalError = true;
    // expected token from parser
    $error_text = "<br> Syntax error :Unexpected '<b>{$this->lex->value}</b>'";
    if (count($this->yy_get_expected_tokens($yymajor)) <= 4) {
        foreach ($this->yy_get_expected_tokens($yymajor) as $token) {
            $exp_token = $this->yyTokenName[$token];
            if (isset($this->lex->smarty_token_names[$exp_token])) {
                // token type from lexer
                $expect[] = "'<b>{$this->lex->smarty_token_names[$exp_token]}</b>'";
            } else {
                // otherwise internal token name
                $expect[] = $this->yyTokenName[$token];
            }
        }
        $error_text .= ', expected one of: ' . implode(' , ', $expect) . '<br>';
    }
    $this->compiler->error($error_text);
}

%stack_overflow
{
    $this->internalError = true;
    $this->compiler->error("Stack overflow in template parser");
}

%left VERT.
%left COLON.

//
// complete template
//
start ::= template. {
   // execute end of template
   if ($this->compiler->context->caching) {
       $this->compiler->has_code = true;
       $this->compiler->nocache_nolog = true;
   }
}

//
// loop over template elements
//
                      // single template element
template       ::= template_element. {
}

                      // loop of elements
template       ::= template template_element. {
}

                      // empty template
template       ::= . 

//
// template elements
//
                      // Template init
template_element ::= TEMPLATEINIT(i). {
}


                      // Smarty tag
template_element ::= smartytag(st) RDEL. {
    if ($this->compiler->has_code) {
        $this->compiler->nocacheCode(st,true);
    } else { 
        $this->compiler->template_code->raw(st);
    }  
    $this->block_nesting_level = count($this->compiler->_tag_stack);
} 

                      // comments
template_element ::= COMMENT. {
}

                      // Literal
template_element ::= literal(l). {
    $this->compiler->template_code->php('echo ')->string(l)->raw(";\n");
}

                      // '<?php' tag
template_element ::= PHPSTARTTAG(st). {
    if ($this->php_handling == Smarty::PHP_PASSTHRU) {
        $this->compiler->template_code->php("echo '<?php';\n");
    } elseif ($this->php_handling == Smarty::PHP_QUOTE) {
        $this->compiler->template_code->php("echo '&lt;?php';\n");
    } elseif ($this->php_handling == Smarty::PHP_ALLOW) {
        if (!($this->compiler->template instanceof SmartyBC)) {
            $this->compiler->error (self::Err3);
        }
        $this->text_is_php = true;
    }
}

                      // '?>' tag
template_element ::= PHPENDTAG. {
    if ($this->is_xml) {
        $this->is_xml = false;
        $this->compiler->template_code->php("echo '?>';\n");
    } elseif ($this->php_handling == Smarty::PHP_PASSTHRU) {
        $this->compiler->template_code->php("echo '?>';\n");
    } elseif ($this->php_handling == Smarty::PHP_QUOTE) {
        $this->compiler->template_code->php("echo '?&gt;';\n");
    } elseif ($this->php_handling == Smarty::PHP_ALLOW) {
        $this->text_is_php = false;
    }
}

                      // '<%' tag
template_element ::= ASPSTARTTAG(st). {
    if ($this->php_handling == Smarty::PHP_PASSTHRU) {
        $this->compiler->template_code->php("echo '<%';\n");
    } elseif ($this->php_handling == Smarty::PHP_QUOTE) {
        $this->compiler->template_code->php("echo '&lt;%';\n");
    } elseif ($this->php_handling == Smarty::PHP_ALLOW) {
        if ($this->asp_tags) {
            if (!($this->compiler->template instanceof SmartyBC)) {
                $this->compiler->error (self::Err3);
            }
            $this->text_is_php = true;
        } else {
            $this->compiler->template_code->php("echo '<%';\n");
        }
    } elseif ($this->php_handling == Smarty::PHP_REMOVE) {
        if (!$this->asp_tags) {
            $this->compiler->template_code->php("echo '<%';\n");
        }
    }
}
  
                      // '%>' tag
template_element ::= ASPENDTAG(et). {
    if ($this->php_handling == Smarty::PHP_PASSTHRU) {
        $this->compiler->template_code->php("echo '%>';\n");
    } elseif ($this->php_handling == Smarty::PHP_QUOTE) {
        $this->compiler->template_code->php("echo '%&gt;';\n");
    } elseif ($this->php_handling == Smarty::PHP_ALLOW) {
        if ($this->asp_tags) {
            $this->text_is_php = false;
        } else {
            $this->compiler->template_code->php("echo '%>';\n");
        }
    } elseif ($this->php_handling == Smarty::PHP_REMOVE) {
        if (!$this->asp_tags) {
            $this->compiler->template_code->php("echo '%>';\n");
        }
    }
}

template_element ::= FAKEPHPSTARTTAG(o). {
    if ($this->strip) {
        $this->compiler->template_code->php('echo ')->string(preg_replace('![\t ]*[\r\n]+[\t ]*!', '', o))->raw(";\n");
    } else {
        $this->compiler->template_code->php('echo ')->string(o)->raw(";\n");
    }
}

                      // XML tag
template_element ::= XMLTAG. {
    $this->is_xml = true; 
    $this->compiler->template_code->php("echo '<?xml';\n");
}

                      // template text
template_element ::= TEXT(o). {
    if ($this->text_is_php) {
        $this->compiler->prefix_code[] = o;
        $this->compiler->nocacheCode('', true);
    } else {
        // inheritance child templates shall not output text
        if (!$this->compiler->isInheritanceChild || $this->compiler->block_nesting_level > 0) {
            if ($this->strip) {
                $this->compiler->template_code->php('echo ')->string(preg_replace('![\t ]*[\r\n]+[\t ]*!', '', o))->raw(";\n");
            } else {
                $this->compiler->template_code->php('echo ')->string(o)->raw(";\n");
            }
        }
    }
}

                      // strip on
template_element ::= STRIPON(d). {
    $this->strip = true;
}
                      // strip off
template_element ::= STRIPOFF(d). {
    $this->strip = false;
}

                    // Litteral
literal(res) ::= LITERALSTART LITERALEND. {
    res = '';
}

literal(res) ::= LITERALSTART literal_elements(l) LITERALEND. {
    res = l;
}
 
literal_elements(res) ::= literal_elements(l1) literal_element(l2). {
    res = l1.l2;
}

literal_elements(res) ::= . {
    res = '';
}

literal_element(res) ::= literal(l). {
    res = l;
}

literal_element(res) ::= LITERAL(l). {
    res = l;
}

literal_element(res) ::= PHPSTARTTAG(st). {
    res = st;
}

literal_element(res) ::= FAKEPHPSTARTTAG(st). {
    res = st;
}

literal_element(res) ::= PHPENDTAG(et). {
    res = et;
}

literal_element(res) ::= ASPSTARTTAG(st). {
    res = st;
}

literal_element(res) ::= ASPENDTAG(et). {
    res = et;
}

//
// output tags start here
//

                  // output with optional attributes
//smartytag(res)   ::= LDEL value(e). {
//    res = $this->compiler->compileTag('Internal_PrintExpression',array(),array('value'=>e));
//}

//smartytag(res)   ::= LDEL expr(e) modifierlist(l) attributes(a). {
//    res = $this->compiler->compileTag('Internal_PrintExpression',a,array('value'=>e,'modifier_list'=>l));
//}

smartytag(res)   ::= LDEL expr(e) attributes(a). {
    res = $this->compiler->compileTag('Internal_PrintExpression',a,array('value'=>e));
}

//
// Smarty tags start here
//

                  // assign new style
//smartytag(res)   ::= LDEL DOLLAR ID(i) EQUAL value(e). {
//    res = $this->compiler->compileTag('assign',array(array('value'=>e),array('var'=>"'".i."'")));
//}

//smartytag(res)   ::= LDEL DOLLAR ID(i) EQUAL expr(e). {
//    res = $this->compiler->compileTag('assign',array(array('value'=>e),array('var'=>"'".i."'")));
//}
                 
//smartytag(res)   ::= LDEL DOLLAR ID(i) EQUAL expr(e) attributes(a). {
//    res = $this->compiler->compileTag('assign',array_merge(array(array('value'=>e),array('var'=>"'".i."'")),a));
//}

smartytag(res)   ::= LDEL varindexed(vi) EQUAL expr(e) attributes(a). {
    res = $this->compiler->compileTag('assign',array_merge(array(array('value'=>e),array('var'=>vi['var'])),a),array('smarty_internal_index'=>vi['smarty_internal_index']));
} 
                 
                  // tag with optional Smarty2 style attributes
smartytag(res)   ::= LDEL ID(i) attributes(a). {
    res = $this->compiler->compileTag(i,a);
}

//smartytag(res)   ::= LDEL ID(i). {
//    res = $this->compiler->compileTag(i,array());
//}

                  // registered object tag
//smartytag(res)   ::= LDEL ID(i) PTR ID(m) attributes(a). {
//    res = $this->compiler->compileTag(i,a,array('object_method'=>m));
//}

                  // tag with modifier and optional Smarty2 style attributes
smartytag(res)   ::= LDEL ID(i) modifierlist(l)attributes(a). {
    res = 'ob_start(); '.$this->compiler->compileTag(i,a).' echo ';
    res .= $this->compiler->compileTag('Internal_Modifier',array(),array('modifier_list'=>l,'value'=>'ob_get_clean()')).';';
}

                  // registered object tag with modifiers
smartytag(res)   ::= LDEL ID(i) PTR ID(me) modifierlist(l) attributes(a). {
    res = 'ob_start(); '.$this->compiler->compileTag(i,a,array('object_method'=>me)).' echo ';
    res .= $this->compiler->compileTag('Internal_Modifier',array(),array('modifier_list'=>l,'value'=>'ob_get_clean()')).';';
}

                  // {if}, {elseif} and {while} tag
//smartytag(res)   ::= LDELIF(i) expr(ie). {
//    $tag = trim(substr(i,$this->lex->ldel_length));
//    res = $this->compiler->compileTag(($tag == 'else if')? 'elseif' : $tag,array(),array('if condition'=>ie));
//}

smartytag(res)   ::= LDELIF(i) expr(ie) attributes(a). {
    $tag = trim(substr(i,$this->lex->ldel_length));
    res = $this->compiler->compileTag(($tag == 'else if')? 'elseif' : $tag,a,array('if condition'=>ie));
}

//smartytag(res)   ::= LDELIF(i) statement(ie). {
//    $tag = trim(substr(i,$this->lex->ldel_length));
//    res = $this->compiler->compileTag(($tag == 'else if')? 'elseif' : $tag,array(),array('if condition'=>ie));
//}

smartytag(res)   ::= LDELIF(i) statement(ie)  attributes(a). {
    $tag = trim(substr(i,$this->lex->ldel_length));
    res = $this->compiler->compileTag(($tag == 'else if')? 'elseif' : $tag,a,array('if condition'=>ie));
}

                  // {for} tag
smartytag(res)   ::= LDELFOR statements(st) SEMICOLON optspace expr(ie) SEMICOLON optspace varvar(v2) EQUAL expr(e) attributes(a). {
    res = $this->compiler->compileTag('for',array_merge(a,array(array('start'=>st),array('ifexp'=>ie),array('var'=>v2),array('step'=>'='.e))),1);
}
smartytag(res)   ::= LDELFOR statements(st) SEMICOLON optspace expr(ie) SEMICOLON optspace IDINCDEC(v2) attributes(a). {
    $len =strlen(v2);
    res = $this->compiler->compileTag('for',array_merge(a,array(array('start'=>st),array('ifexp'=>ie),array('var'=>substr(v2,1,$len-3)),array('step'=>substr(v2,$len-2)))),1);
}

smartytag(res)   ::= LDELFOR statement(st) TO expr(v) attributes(a). {
    res = $this->compiler->compileTag('for',array_merge(a,array(array('start'=>st),array('to'=>v))),0);
}

smartytag(res)   ::= LDELFOR statement(st) TO expr(v) STEP expr(v2) attributes(a). {
    res = $this->compiler->compileTag('for',array_merge(a,array(array('start'=>st),array('to'=>v),array('step'=>v2))),0);
}

                  // {foreach} tag
smartytag(res)   ::= LDELFOREACH attributes(a). {
    res = $this->compiler->compileTag('foreach',a);
}

                  // {foreach $array as $var} tag
smartytag(res)   ::= LDELFOREACH SPACE value(v1) AS varvar(v0) attributes(a). {
    res = $this->compiler->compileTag('foreach',array_merge(a,array(array('from'=>v1),array('item'=>v0))));
}

smartytag(res)   ::= LDELFOREACH SPACE value(v1) AS varvar(v2) APTR varvar(v0) attributes(a). {
    res = $this->compiler->compileTag('foreach',array_merge(a,array(array('from'=>v1),array('item'=>v0),array('key'=>v2))));
}

smartytag(res)   ::= LDELFOREACH SPACE expr(e) AS varvar(v0) attributes(a). {
    res = $this->compiler->compileTag('foreach',array_merge(a,array(array('from'=>e),array('item'=>v0))));
}

smartytag(res)   ::= LDELFOREACH SPACE expr(e) AS varvar(v1) APTR varvar(v0) attributes(a). {
    res = $this->compiler->compileTag('foreach',array_merge(a,array(array('from'=>e),array('item'=>v0),array('key'=>v1))));
}

                  // {setfilter}
smartytag(res)   ::= LDELSETFILTER ID(m) modparameters(p). {
    res = $this->compiler->compileTag('setfilter',array(),array('modifier_list'=>array(array_merge(array(m),p))));
}

smartytag(res)   ::= LDELSETFILTER ID(m) modparameters(p) modifierlist(l) RDEL. {
    res = $this->compiler->compileTag('setfilter',array(),array('modifier_list'=>array_merge(array(array_merge(array(m),p)),l)));
}

                  
                  
                  // end of block tag  {/....}                  
smartytag(res)   ::= LDELSLASH ID(i). {
    res = $this->compiler->compileTag(i.'close',array());
}

smartytag(res)   ::= LDELSLASH ID(i) modifierlist(l). {
    res = $this->compiler->compileTag(i.'close',array(),array('modifier_list'=>l));
}

                  // end of block object tag  {/....}                 
smartytag(res)   ::= LDELSLASH ID(i) PTR ID(m). {
    res = $this->compiler->compileTag(i.'close',array(),array('object_method'=>m));
}

smartytag(res)   ::= LDELSLASH ID(i) PTR ID(m) modifierlist(l). {
    res = $this->compiler->compileTag(i.'close',array(),array('object_method'=>m,'modifier_list'=>l));
}

//
//Attributes of Smarty tags 
//
                  // list of attributes
attributes(res)  ::= attributes(a1) attribute(a2). {
    res = a1;
    res[] = a2;
}

                  // single attribute
attributes(res)  ::= attribute(a). {
    res = array(a);
}

                  // no attributes
attributes(res)  ::= . {
    res = array();
}
                  
                  // attribute
attribute(res)   ::= SPACE ID(v) EQUAL ID(id). {
    if (preg_match('~^true$~i', id)) {
        res = array(v=>'true');
    } elseif (preg_match('~^false$~i', id)) {
        res = array(v=>'false');
    } elseif (preg_match('~^null$~i', id)) {
        res = array(v=>'null');
    } else {
        res = array(v=>"'".id."'");
    }
}

attribute(res)   ::= ATTR(v) expr(e). {
    res = array(trim(v," =\n\r\t")=>e);
}

//attribute(res)   ::= ATTR(v) value(e). {
//    res = array(trim(v," =\n\r\t")=>e);
//}

attribute(res)   ::= SPACE ID(v). {
    res = "'".v."'";
}

attribute(res)   ::= SPACE expr(e). {
    res = e;
}

//attribute(res)   ::= SPACE value(v). {
//    res = v;
//}

//attribute(res)   ::= SPACE INTEGER(i) EQUAL expr(e). {
//    res = array(i=>e);
//}

                  

//
// statement
//
statements(res)   ::= statement(s). {
    res = array(s);
}

statements(res)   ::= statements(s1) COMMA statement(s). {
    s1[]=s;
    res = s1;
}


statement(res)    ::= varindexed(vi) EQUAL expr(e). {
    res = vi;
    res['value'] = e;
}

statement(res)    ::= OPENP statement(st) CLOSEP. {
    res = st;
}


//
// expressions
//

                  // single value
expr(res)        ::= value(v). {
    res = v;
}

                 // ternary
expr(res)        ::= ternary(v). {
    res = v;
}

                 // resources/streams
expr(res)        ::= DOLLAR ID(i) COLON ID(i2). {
    res = '$this->smarty->getStreamVariable(\''. i .'://'. i2 . '\')';
}

                  // arithmetic expression
expr(res)        ::= expr(e) MATH(m) value(v). {
    res = e . trim(m) . v;
}

expr(res)        ::= expr(e) UNIMATH(m) value(v). {
    res = e . trim(m) . v;
}
 
                  // bit operation 
expr(res)        ::= expr(e) ANDSYM(m) value(v). {
    res = e . trim(m) . v;
} 

                  // array
//expr(res)       ::= array(a). {
//    res = a;
//}

                  // modifier
expr(res)        ::= expr(e) modifierlist(l). {
    res = $this->compiler->compileTag('Internal_Modifier',array(),array('value'=>e,'modifier_list'=>l));
}

// if expression
                    // simple expression
expr(res)        ::= expr(e1) IFCOND(c) expr(e2). {
    $operator = strtolower(trim(c));
    if (isset($this->opMap[$operator])) {
        $operator = $this->opMap[$operator];
    }
    res = e1." {$operator} ".e2;
}

//expr(res)        ::= expr(e1) ISIN array(a).  {
//    res = 'in_array('.e1.','.a.')';
//}

expr(res)        ::= expr(e1) ISIN value(v).  {
    res = 'in_array('.e1.',(array)'.v.')';
}

expr(res)        ::= expr(e1) LOP(o) expr(e2).  {
    $operator = strtolower(trim(o));
    if (isset($this->opMap[$operator])) {
        $operator = $this->opMap[$operator];
    }
    res = e1." {$operator} ".e2;
}

expr(res)        ::= expr(e1) ISDIVBY expr(e2). {
    res = '!('.e1.' % '.e2.')';
}

expr(res)        ::= expr(e1) ISNOTDIVBY expr(e2).  {
    res = '('.e1.' % '.e2.')';
}

expr(res)        ::= expr(e1) ISEVEN. {
    res = '!(1 & '.e1.')';
}

expr(res)        ::= expr(e1) ISNOTEVEN.  {
    res = '(1 & '.e1.')';
}

expr(res)        ::= expr(e1) ISEVENBY expr(e2).  {
    res = '!(1 & '.e1.' / '.e2.')';
}

expr(res)        ::= expr(e1) ISNOTEVENBY expr(e2). {
    res = '(1 & '.e1.' / '.e2.')';
}

expr(res)        ::= expr(e1) ISODD.  {
    res = '(1 & '.e1.')';
}

expr(res)        ::= expr(e1) ISNOTODD. {
    res = '!(1 & '.e1.')';
}

expr(res)        ::= expr(e1) ISODDBY expr(e2). {
    res = '(1 & '.e1.' / '.e2.')';
}

expr(res)        ::= expr(e1) ISNOTODDBY expr(e2).  {
    res = '!(1 & '.e1.' / '.e2.')';
}

expr(res)        ::= value(v1) INSTANCEOF(i) ID(id). {
    res = v1.i.id;
}
expr(res)        ::= value(v1) INSTANCEOF(i) NAMESPACE(id). {
    res = v1.i.id;
}


expr(res)        ::= value(v1) INSTANCEOF(i) value(v2). {
    $this->prefix_number++;
    $this->compiler->prefix_code[] = '$_tmp'.$this->prefix_number.'='.v2.';';
    res = v1.i.'$_tmp'.$this->prefix_number;
}

ldelexprrdel(res)   ::= LDEL expr(e) RDEL. {
    res = e;
}

//
// ternary
//
ternary(res)        ::= OPENP expr(v) CLOSEP  QMARK DOLLAR ID(e1) COLON  expr(e2). {
    res = v.' ? '. $this->compiler->compileVariable("'".e1."'") . ' : '.e2;
}

ternary(res)        ::= OPENP expr(v) CLOSEP  QMARK  expr(e1) COLON  expr(e2). {
    res = v.' ? '.e1.' : '.e2;
}


                 // value
value(res)       ::= variable(v). {
    res = v;
}

                  // +/- value
value(res)        ::= UNIMATH(m) value(v). {
    res = m.v;
}

                  // logical negation
value(res)       ::= NOT(o) value(v). {
    $operator = strtolower(trim(o));
    if (isset($this->opMap[$operator])) {
        $operator = $this->opMap[$operator];
    }

    res = $operator.v;
}

value(res)       ::= TYPECAST(t) value(v). {
    res = t.v;
}

                 // numeric
value(res)       ::= NUMBER(n). {
    res = n;
}

value(res)       ::= array(a). {
    res = a;
}

                 // ID, true, false, null
value(res)       ::= ID(id). {
    if (preg_match('~^true$~i', id)) {
        res = 'true';
    } elseif (preg_match('~^false$~i', id)) {
        res = 'false';
    } elseif (preg_match('~^null$~i', id)) {
        res = 'null';
    } else {
        res = "'".id."'";
    }
}

                  // function call
value(res)       ::= function(f). {
    res = f;
}

                  // expression
value(res)       ::= OPENP expr(e) CLOSEP. {
    res = "(". e .")";
}

                  // singele quoted string
value(res)       ::= SINGLEQUOTESTRING(t). {
    res = t;
}

                  // double quoted string
value(res)       ::= doublequoted_with_quotes(s). {
    res = s;
}

value(res)    ::= IDINCDEC(v). {
    $len = strlen(v);
    res = '$_scope->_tpl_vars->' . substr(v,1,$len-3) . '->value' . substr(v,$len-2);
}

                  // static class access
value(res)       ::= ID(c)static(s). {
    if (!$this->security || isset($this->compiler->context->smarty->_registered['class'][c]) || $this->compiler->context->smarty->security_policy->isTrustedStaticClass(c, $this->compiler)) {
        if (isset($this->compiler->context->smarty->_registered['class'][c])) {
            res = $this->compiler->context->smarty->_registered['class'][c].s;
        } else {
            res = c.s;
        } 
    } else {
        $this->compiler->error ("static class '".c."' is undefined or not allowed by security setting");
    }
}

                  // namespace class access
value(res)       ::= NAMESPACE(c) static(s). {
    res = c.s;
}

                  // name space constant
value(res)       ::= NAMESPACE(c). {
    res = c;
}

value(res)    ::= DOLLAR ID(i) static(s). {
    res = $this->compiler->compileVariable(i).s;
}

                  // Smarty tag
value(res)       ::= smartytag(st). {
    $this->prefix_number++;
    $code = new Smarty_Compiler_Code();
    $code->iniTagCode($this->compiler);
    $code->php("ob_start();")->newline();
    $code->mergeCode(st);
    $code->php("\$_tmp{$this->prefix_number} = ob_get_clean();")->newline();
    $this->compiler->prefix_code[] = $code;
    res = '$_tmp'.$this->prefix_number;
}


value(res)       ::= value(v) modifierlist(l). {
    res = $this->compiler->compileTag('Internal_Modifier',array(),array('value'=>v,'modifier_list'=>l));
}


//
// variables 
//
                  // Smarty variable (optional array)
variable(res)    ::= varindexed(vi). {
    if (vi['var'] == 'smarty') {
        res = $this->compiler->compileTag('Internal_SpecialVariable',array(),vi['smarty_internal_index']);
    } else {
        res = $this->compiler->compileVariable(vi['var']).vi['smarty_internal_index'];
    }
}

                  // variable with property
variable(res)    ::=  varvar(v) AT ID(p). {
    res = '$_scope->_tpl_vars->' . trim(v,"'") . '->' . p;
}

                  // object
variable(res)    ::= object(o). {
    res = o;
}

                  // config variable
//variable(res)    ::= HATCH ID(i) HATCH. {
//    $var = trim(i,'\'');
//    res = "\$_scope->_tpl_vars->___config_var_{$var}";
//}

variable(res)    ::= HATCH ID(i) HATCH arrayindex(a). {
    $var = trim(i,'\'');
    res = "\$_scope->_tpl_vars->___config_var_{$var}".a;
}

//variable(res)    ::= HATCH variable(v) HATCH. {
//    res = "\$_scope->_tpl_vars->___config_var_{{v}}";
//}

variable(res)    ::= HATCH varindexed(v) HATCH arrayindex(a). {
    res = "\$_scope->_tpl_vars->___config_var_{{v}}".a;
}

varindexed(res)  ::=  varvar(v) arrayindex(a). {
    res = array('var'=>v, 'smarty_internal_index'=>a);
}

//
// array index
//
                    // multiple array index
arrayindex(res)  ::= arrayindex(a1) indexdef(a2). {
    res = a1.a2;
}

                    // no array index
arrayindex        ::= . {
    return;
}

// single index definition
                    // Smarty2 style index 
indexdef(res)    ::= DOT varvar(v).  {
    res = '['.$this->compiler->compileVariable(v).']';
}

indexdef(res)    ::= DOT varvar(v) AT ID(p). {
    res = '['.$this->compiler->compileVariable(v).'->'.p.']';
}

indexdef(res)   ::= DOT ID(i). {
    res = "['". i ."']";
}

// tricky handling of  d.d  in index
indexdef(res)   ::= DOT NUMBER(n). {
    res = "[". implode('][', explode('.', n)) ."]";
}

indexdef(res)   ::= DOT ldelexprrdel(e). {
    res = "[". e ."]";
}

                    // section tag index
indexdef(res)   ::= OPENB ID(i)CLOSEB. {
    res = '['.$this->compiler->compileTag('Internal_SpecialVariable',array(),'[\'section\'][\''.i.'\'][\'index\']').']';
}

indexdef(res)   ::= OPENB ID(i) DOT ID(i2) CLOSEB. {
    res = '['.$this->compiler->compileTag('Internal_SpecialVariable',array(),'[\'section\'][\''.i.'\'][\''.i2.'\']').']';
}

                    // PHP style index
indexdef(res)   ::= OPENB expr(e) CLOSEB. {
    res = "[". e ."]";
}

                    // für assign append array
indexdef(res)  ::= OPENB CLOSEB. {
    res = '[]';
}

static(res) ::= DOUBLECOLON static_class_access(s). {
    res = '::'.s;
}

//
// variable variable names
//
    // singel identifier element
varvar(res)      ::= DOLLAR ID(i). {
    res = i;
}

varvar(res)      ::= DOLLAR varvarele(v). {
    $this->prefix_number++;
    $code = new Smarty_Compiler_Code;
    $code->iniTagCode($this->compiler);
    $code->php('$_tmp'.$this->prefix_number.'='.v.";\n");
    $this->compiler->prefix_code[] = $code;
    res = '$_tmp'.$this->prefix_number;
}

                    // sequence of identifier elements
varvarele(res)      ::= varvarele(v1) varvarele(v2). {
    res = v1.'.'.v2;
}

                    // fix sections of element
varvarele(res)   ::= ID(s). {
    res = '\''.s.'\'';
}

                    // variable sections of element
varvarele(res)   ::= ldelexprrdel(e). {
    res = '('.e.')';
}

//
// objects
//
object(res)    ::= varindexed(vi) objectchain(oc). {
    if (vi['var'] == '\'smarty\'') {
        res =  $this->compiler->compileTag('Internal_SpecialVariable',array(),vi['smarty_internal_index']).oc;
    } else {
        res = $this->compiler->compileVariable(vi['var']).vi['smarty_internal_index'].oc;
    }
}

                    // single element
objectchain(res) ::= objectelement(oe). {
    res  = oe;
}

      // chain of elements
objectchain(res) ::= objectchain(oc) objectelement(oe). {
    res  = oc.oe;
}

                    // variable
objectelement(res)::= PTR ID(i) arrayindex(a). {
    if ($this->security && substr(i,0,1) == '_') {
        $this->compiler->error (self::Err1);
    }
    res = '->'.i.a;
}

objectelement(res)::= PTR varindexed(v). {
    if ($this->security) {
        $this->compiler->error (self::Err2);
    }
    res = '->{'.v.'}';
}

objectelement(res)::= PTR ldelexprrdel(e) arrayindex(a). {
    if ($this->security) {
        $this->compiler->error (self::Err2);
    }
    res = '->{'.e.a.'}';
}

objectelement(res)::= PTR ID(ii) ldelexprrdel(e) arrayindex(a). {
    if ($this->security) {
        $this->compiler->error (self::Err2);
    }
    res = '->{\''.ii.'\'.'.e.a.'}';
}

                    // method
objectelement(res)::= PTR method(f).  {
    res = '->'.f;
}


//
// function
//
function(res)     ::= ID(f) OPENP params(p) CLOSEP. {
    if (!$this->security || $this->compiler->context->smarty->security_policy->isTrustedPhpFunction(f, $this->compiler)) {
        if (strcasecmp(f,'isset') === 0 || strcasecmp(f,'empty') === 0 || strcasecmp(f,'array') === 0 || is_callable(f)) {
            $func_name = strtolower(f);
            if ($func_name == 'isset') {
                if (count(p) == 0) {
                    $this->compiler->error ('Illegal number of paramer in "isset()"');
                }
                $par = implode(',',p);
                preg_match('/\$_scope->_tpl_vars->([0-9]*[a-zA-Z_]\w*)(.*)/',$par,$match);
                if (isset($match[1])) {
                    $search = array('/\$_scope->_tpl_vars->([0-9]*[a-zA-Z_]\w*)/','/->value.*/');
                    $replace = array('$this->_getVariable(\'\1\', null, false, false)','');
                    $this->prefix_number++;
                    $code = new Smarty_Compiler_Code();
                    $code->iniTagCode($this->compiler);
                    $code->php("\$_tmp{$this->prefix_number} = "  .preg_replace($search, $replace, $par) . ';')->newline();
                    $this->compiler->prefix_code[] = $code;
                    $isset_par = '$_tmp'.$this->prefix_number.$match[2];
                } else {
                    $this->prefix_number++;
                    $code = new Smarty_Compiler_Code();
                    $code->iniTagCode($this->compiler);
                    $code->php("\$_tmp{$this->prefix_number} = " . $par . ';')->newline();
                    $this->compiler->prefix_code[] = $code;
                    $isset_par = '$_tmp'.$this->prefix_number;
                }
                res = f . "(". $isset_par .")";
            } elseif (in_array($func_name,array('empty','reset','current','end','prev','next'))){
                if (count(p) != 1) {
                    $this->compiler->error ("Illegal number of paramer in \"{$func_name}\"");
                }
                res = $func_name.'('.p[0].')';
            } else {
                res = f . "(". implode(',',p) .")";
            }
        } else {
            $this->compiler->error ("unknown function \"" . f . "\"");
        }
    }
}

//
// namespace function
//
function(res)     ::= NAMESPACE(f) OPENP params(p) CLOSEP. {
    if (!$this->security || $this->compiler->context->smarty->security_policy->isTrustedPhpFunction(f, $this->compiler)) {
        if (is_callable(f)) {
            res = f . "(". implode(',',p) .")";
        } else {
            $this->compiler->error ("unknown function \"" . f . "\"");
        }
    }
}

//
// method
//
method(res)     ::= ID(f) OPENP params(p) CLOSEP. {
    if ($this->security && substr(f,0,1) == '_') {
        $this->compiler->error (self::Err1);
    }
    res = f . "(". implode(',',p) .")";
}

method(res)     ::= DOLLAR ID(f) OPENP params(p) CLOSEP.  {
    if ($this->security) {
        $this->compiler->error (self::Err2);
    }
    $this->prefix_number++;
    $code = new Smarty_Compiler_Code();
    $code->iniTagCode($this->compiler);
    $code->php("\$_tmp{$this->prefix_number} = " . $this->compiler->compileVariable("'".f."'") . ';')->newline();
    $this->compiler->prefix_code[] = $code;
    res = '$_tmp'.$this->prefix_number.'('. implode(',',p) .')';
}

// function/method parameter
                    // multiple parameters
params(res)       ::= params(p) COMMA expr(e). {
    res = array_merge(p,array(e));
}

                    // single parameter
params(res)       ::= expr(e). {
    res = array(e);
}

                    // kein parameter
params(res)       ::= . {
    res = array();
}

//
// modifier
// 
//
// modifier
//
modifierlist(res) ::= modifierlist(l) modifier(m) modparameters(p). {
    res = array_merge(l,array(array_merge(m,p)));
}

modifierlist(res) ::= modifier(m) modparameters(p). {
    res = array(array_merge(m,p));
}

modifier(res)    ::= VERT AT ID(m). {
    res = array(m);
}

modifier(res)    ::= VERT ID(m). {
    res =  array(m);
}

//
// modifier parameter
//
                    // multiple parameter
modparameters(res) ::= modparameters(mps) modparameter(mp). {
    res = array_merge(mps,mp);
}

                    // no parameter
modparameters(res)      ::= . {
    res = array();
}

                    // parameter expression
modparameter(res) ::= COLON value(mp). {
    res = array(mp);
}

                  // static class method call
static_class_access(res)       ::= method(m). {
    res = m;
}

                  // static class method call with object chainig
static_class_access(res)       ::= method(m) objectchain(oc). {
    res = m.oc;
}

                  // static class constant
static_class_access(res)       ::= ID(v). {
    res = v;
}

                  // static class variables
static_class_access(res)       ::=  DOLLAR ID(v) arrayindex(a). {
    res = '$'.v.a;
}

                  // static class variables with object chain
static_class_access(res)       ::= DOLLAR ID(v) arrayindex(a) objectchain(oc). {
    res = '$'.v.a.oc;
}



//
// ARRAY element assignment
//
array(res)           ::=  OPENB arrayelements(a) CLOSEB.  {
    res = 'array('.a.')';
}

arrayelements(res)   ::=  arrayelement(a).  {
    res = a;
}

arrayelements(res)   ::=  arrayelements(a1) COMMA arrayelement(a).  {
    res = a1.','.a;
}

arrayelements        ::=  .  {
    return;
}

arrayelement(res)    ::=  value(e1) APTR expr(e2). {
    res = e1.'=>'.e2;
}

arrayelement(res)    ::=  ID(i) APTR expr(e2). { 
    res = '\''.i.'\'=>'.e2;
}

arrayelement(res)    ::=  expr(e). {
    res = e;
}


//
// double quoted strings
//
doublequoted_with_quotes(res) ::= QUOTE QUOTE. {
    res = "''";
}

doublequoted_with_quotes(res) ::= QUOTE doublequoted(s) QUOTE. {
    res = s;
}


doublequoted(res)          ::= doublequoted(o1) doublequotedcontent(o2). {
    if (o2 === false) {
       res = o1;
    } else {
       res = o1. '.' . o2;
    }
}

doublequoted(res)          ::= doublequotedcontent(o). {
    if (o === false) {
       res = "''";
    } else {
       res = o;
    }
}

doublequotedcontent(res)           ::=  BACKTICK variable(v) BACKTICK. {
    if (empty($this->db_quote_code_buffer)) {
        res = '(string)'.v;
    } else {
        $this->db_quote_code_buffer .= 'echo (string)'.v.';';
        res = false;
    }
}

doublequotedcontent(res)           ::=  BACKTICK expr(e) BACKTICK. {
    if (empty($this->db_quote_code_buffer)) {
        res = '(string)('.e.')';
    } else {
        $this->db_quote_code_buffer .= 'echo (string)('.e.');';
        res = false;
    }
}

doublequotedcontent(res)           ::=  DOLLARID(i). {
    if (empty($this->db_quote_code_buffer)) {
        res = '(string)$_scope->_tpl_vars->'. substr(i,1) . '->value';
    } else {
        $this->db_quote_code_buffer .= 'echo (string)$_scope->_tpl_vars->'. substr(i,1) . '->value;';
        res = false;
    }
}

doublequotedcontent(res)           ::=  DQTAG LDEL variable(v) RDEL. {
    if (empty($this->db_quote_code_buffer)) {
        res = '(string)'.v;
    } else {
        $this->db_quote_code_buffer .= 'echo (string)'.v.';';
        res = false;
    }
}

doublequotedcontent(res)           ::=  DQTAG ldelexprrdel(e). {
    if (empty($this->db_quote_code_buffer)) {
        res = '(string)('.e.')';
    } else {
        $this->db_quote_code_buffer .= 'echo (string)('.e.');';
        res = false;
    }
}

doublequotedcontent(res)     ::=  DQTAG smartytag(st) RDEL. {
    if (empty($this->db_quote_code_buffer)) {
            $this->db_quote_code_buffer = "ob_start();\n";
    }
    $this->db_quote_code_buffer .= st->buffer;
        if ($this->block_nesting_level == count($this->compiler->_tag_stack)) {
        $this->prefix_number++;
        $code = new Smarty_Compiler_Code();
        $code->iniTagCode($this->compiler);
        $code->formatPHP( $this->db_quote_code_buffer . ' $_tmp'.$this->prefix_number.'=ob_get_clean();')->newline();
        $this->compiler->prefix_code[] = $code;
        $this->db_quote_code_buffer = '';
        res = '$_tmp'.$this->prefix_number;
    } else {
        res = false;
    }

}

doublequotedcontent(res)           ::=  TEXT(o). {
    if (empty($this->db_quote_code_buffer)) {
        res = '"'.o.'"';
    } else {
        $this->db_quote_code_buffer .= 'echo ' . sprintf('"%s"', addcslashes(o, "\0\t\n\r\"\$\\")) . ';';
        res = false;
    }
}
// we did push 1 level too much
doublequotedcontent(res)           ::=  DQTAG. {
    $this->compiler->lex->yypopstate();
    res = '';
}


//
// optional space
//
optspace(res)     ::= SPACE(s).  {
    res = s;
}

optspace(res)     ::= .          {
    res = '';
}
