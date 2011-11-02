<?php
/**
 * $resultが$expectationが同じである事を検証する
 * @param mixed $expectation なるべき値
 * @param mixed $result テスト対象の値
 * @return boolean
 */
function eq($expectation,$result){
	list($debug) = debug_backtrace(false);
	return Test::equals($expectation,$result,true,$debug["line"],$debug["file"]);
}
/**
 * 成功とする
 * @return boolean
 */
function success(){
	list($debug) = debug_backtrace(false);
	return Test::equals(true,true,true,$debug["line"],$debug["file"]);
}
/**
 * 失敗
 * @return boolean
 */
function fail(){
	list($debug) = debug_backtrace(false);
	return Test::equals(false,true,true,$debug["line"],$debug["file"]);
}
/**
 *　文字列中に指定した文字列がすべて存在していれば成功
 * @param string $keyword スペース区切りで複数可能
 * @param string $src
 * @return boolean
 */
function meq($keyword,$src){
	list($debug) = debug_backtrace(false);
	if(empty($keyword)) throw new InvalidArgumentException("undef keyword");
	return Test::equals(true,Text::match($src,$keyword),true,$debug["line"],$debug["file"]);
}
/**
 *　文字列中に指定した文字列がすべて存在していなければ成功
 * @param string $keyword スペース区切りで複数可能
 * @param string $src
 * @return boolean
 */
function nmeq($keyword,$src){
	list($debug) = debug_backtrace(false);
	return Test::equals(false,Text::match($src,$keyword),true,$debug["line"],$debug["file"]);
}
/**
 * $resultが$expectationが同じではない事を検証する
 * @param mixed $expectation ならないはずの値
 * @param mixed $result テスト対象の値
 * @return boolean
 */
function neq($expectation,$result){
	list($debug) = debug_backtrace(false);
	return Test::equals($expectation,$result,false,$debug["line"],$debug["file"]);
}
/**
 * テンポラリファイルを作成する
 * @param string $path
 * @param string $body
 */
function ftmp($path,$body){
	Test::ftmp($path,$body);
}
/**
 * テンポラリファイルを保存するパスを返す
 * @return string
 */
function tmp_path($path=null){
	return Test::tmp_path($path);
}
/**
 * テスト用のHttpインスタンスを返す
 * @return Http
 */
function test_browser(){
	return Test::browser();
}
/**
 * mapに定義されたurlをフォーマットして返す
 * @param string $name
 * @return string
 */
function test_map_url($name){
	list($file) = debug_backtrace(false);
	$args = func_get_args();
	array_unshift($args,$file["file"]);
	return call_user_func_array(array("Test","map_url"),$args);
}
/**
 * テスト用Httpのアクセス結果を取得する
 * @param string $url
 * @param string $name
 * @return string
 */
function handled_var($url,$name){
	return Test::handled_var($url,$name);
}
