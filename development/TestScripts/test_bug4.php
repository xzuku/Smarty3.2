<?php
/**
 * Test script for the Smarty compiler
 *
 * It displays a form in which a template source code can be entered.
 * The template source will be compiled, rendered and the result is displayed.
 * The compiled code is displayed as well
 *
 * @author Uwe Tews
 * @package SmartyTestScripts
 */
require '../../distribution/libs/SmartyBC.class.php';

// set_time_limit(1000);
//ini_set('pcre.backtrack_limit', -1);
$smarty = new Smarty;
$smarty->php_handling = Smarty::PHP_REMOVE;
$smarty->setErrorReporting(E_ALL + E_STRICT);
error_reporting(E_ALL + E_STRICT);
$smarty->addPluginsDir('./plugins');
$smarty->addTemplateDir('../PHPunit/templates');
//$smarty->_parserdebug= true;
//$smarty->auto_literal = false;
//$smarty->force_cache = true;
//$smarty->force_compile = true;
//$smarty->compile_check = Smarty::COMPILECHECK_CACHEMISS;
// $smarty->cache_lifetime = -1;
//$smarty->debugging_ctrl = 'URL';
//$smarty->debugging = true;
// $smarty->default_modifiers = array('escape:"htmlall"','strlen');
// $smarty->use_sub_dirs = true;
// $smarty->setCacheLifetime(10);
//$smarty->setCaching(1);
//$smarty->compile_check = false;
// $smarty->cache_modified_check = true;
//$smarty->merge_compiled_includes = true;
// $smarty->error_unassigned = true;
// $smarty->security=true;;
// $smarty->enableSecurity();
// $smarty->loadFilter('output', 'trimwhitespace');
// $smarty->loadFilter("variable", "htmlspecialchars");
// $smarty->loadFilter('pre', 'translate');
// $smarty->display('child.tpl');
//$smarty->clearAllCache();
// echo $smarty->clear_cache(null,'uwe');
// $smarty->compile_id = 'bar';
// $smarty->cache_id = 'uwe|tews';
// $smarty->left_delimiter = '[%';
// $smarty->right_delimiter = '%]';
//$smarty->use_sub_dirs = true;
//$smarty->enableSecurity();
//$smarty->security_policy->disabled_tags=array('cycle');
//$smarty->security_policy->allowed_tags=array('counter');
$smarty->setCacheLifetime(100000);
$start = microtime(true);
$smarty->assign('foo', 1);
$tpl = $smarty->createTemplate('eval: befor include {$foo} {include file=\'eval:{$foo=2} in include {$foo}\' scope = parent} after include {$foo}', null, null, $smarty);
echo $smarty->fetch($tpl);
echo '<br>';
echo $smarty->fetch('eval: root value {$foo}');
echo '<br>memory ' . memory_get_usage();
echo '<br>' . (microtime(true) - $start) . '<br>';
echo '<br>' . memory_get_peak_usage();
