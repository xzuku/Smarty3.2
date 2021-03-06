<?php
/**
 * Test script for nocache sections
 * @author Uwe Tews
 * @package SmartyTestScripts
 */

require '../../distribution/libs/Smarty.class.php';

$smarty = new Smarty;

$smarty->force_compile = true;
$smarty->caching = 0;
$smarty->cache_lifetime = 20;
$smarty->autoload_filters = array('output' => array('trimwhitespace'));

$smarty->assign('a', array(1, 2, 3, 4, 5));

$smarty->display('test_nocache.tpl');
