<?php
/**
 * rhaco2の環境定義クラス
 * @author tokushima
 */
class Rhaco2{
	static private $rep = array('http://rhaco.org/repository/2/lib/');
	/**
	 * リポジトリの場所を指定する
	 * @param string $rep
	 */
	static public function repository($rep){
		array_unshift(self::$rep,$rep);
	}
	static public function repositorys(){
		return self::$rep;
	}
}
/**
 * set_error_handlerされる関数
 * @param integer $errno
 * @param string $errstr
 * @param string $errfile
 * @param integer $errline
 */
function error_handler($errno,$errstr,$errfile,$errline){
	if(strpos($errstr,'Use of undefined constant') !== false && preg_match("/\'(.+?)\'/",$errstr,$m) && class_exists($m[1])) return define($m[1],$m[1]);
	if(strpos($errstr,' should be compatible with that of') !== false || strpos($errstr,'Strict Standards') !== false) return true;
	throw new ErrorException($errstr,0,$errno,$errfile,$errline);
}
/**
 * register_shutdown_functionされる関数
 */
function shutdown_handler(){
	$error = error_get_last();
	if($error !== null) Log::error($error);
	if(class_exists('App')){
		try{
			App::__shutdown__();
		}catch(Exception $e){
			Log::error($e);
		}
	}
	if(class_exists('Test')) Test::__shutdown__();
	if(class_exists('Request')) Request::__shutdown__();
	if(class_exists('Log')) Log::__shutdown__();
}
ini_set('display_errors','Off');
ini_set('html_errors','Off');
if(ini_get('date.timezone') == '') date_default_timezone_set('Asia/Tokyo');
if('neutral' == mb_language()) mb_language('Japanese');
mb_internal_encoding('UTF-8');
umask(0);
set_error_handler('error_handler');
register_shutdown_function('shutdown_handler');
Lib::__import__();
if(class_exists('Test')) Test::__import__();
Log::__import__();
$exception = $isweb = $run = null;
if(($run = sizeof(debug_backtrace())) > 0 || !($isweb = (isset($_SERVER['REQUEST_URI']) && isset($_SERVER['REQUEST_METHOD'])))){
	try{
		if(is_file($f=(dirname(__FILE__).'/__settings__.php'))) require_once($f);
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
