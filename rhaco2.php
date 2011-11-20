<?php
require(dirname(__FILE__).'/core/App.php');
require(dirname(__FILE__).'/core/Exceptions.php');
require(dirname(__FILE__).'/core/FileIterator.php');
require(dirname(__FILE__).'/core/Gettext.php');
require(dirname(__FILE__).'/core/Lib.php');
require(dirname(__FILE__).'/core/Object.php');
require(dirname(__FILE__).'/core/Paginator.php');
require(dirname(__FILE__).'/core/Request.php');
require(dirname(__FILE__).'/core/Store.php');
require(dirname(__FILE__).'/core/Tag.php');
require(dirname(__FILE__).'/core/TagIterator.php');
require(dirname(__FILE__).'/core/Template.php');
require(dirname(__FILE__).'/core/Templf.php');
require(dirname(__FILE__).'/core/Text.php');
require(dirname(__FILE__).'/core/Setup.php');
require(dirname(__FILE__).'/core/Test.php');
require(dirname(__FILE__).'/core/Command.php');
require(dirname(__FILE__).'/core/File.php');
require(dirname(__FILE__).'/core/Flow.php');
require(dirname(__FILE__).'/core/Http.php');
require(dirname(__FILE__).'/core/Log.php');
require(dirname(__FILE__).'/core/__funcs__.php');
require(dirname(__FILE__).'/core/__tfuncs__.php');
require(dirname(__FILE__).'/core/jump.php');

if(is_file($f=(dirname(__FILE__).'/__settings__.php'))) require_once($f);
if(is_file($f=(dirname(__FILE__).'/__common_'.App::mode().'__.php'))) require_once($f);
if(is_file($f=(dirname(__FILE__).'/__common__.php'))) require_once($f);

$exception = $isweb = $run = null;
if(($run = sizeof(debug_backtrace())) > 0 || !($isweb = (isset($_SERVER['REQUEST_URI']) && isset($_SERVER['REQUEST_METHOD'])))){
	try{
		if(is_file(getcwd().'/__settings__.php')) require_once(getcwd().'/__settings__.php');
		if(class_exists('App')) App::load_common();
	}catch(Exception $e){
		if($isweb) throw $e;
		$exception = $e;
	}
	if($run == 0 && $isweb){
		header('HTTP/1.1 404 Not Found');
		exit;
	}
}
if($run == 0 && !$isweb) Setup::start($exception);
