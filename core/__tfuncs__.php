<?php
if(!function_exists('eq')){
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
}
if(!function_exists('success')){
	/**
	 * 成功とする
	 * @return boolean
	 */
	function success(){
		list($debug) = debug_backtrace(false);
		return Test::equals(true,true,true,$debug["line"],$debug["file"]);
	}
}
if(!function_exists('fail')){
	/**
	 * 失敗
	 */
	function fail($msg=null){
		throw new LogicException('Test fail: '.$msg);
	}
}
if(!function_exists('notice')){
	/**
	 * メッセージ
	 */
	function notice($msg=null){
		list($debug) = debug_backtrace(false);
		Test::notice((($msg instanceof \Exception) ? $msg->getMessage()."\n\n".$msg->getTraceAsString() : (string)$msg),$debug['line'],$debug['file']);
	}
}
if(!function_exists('meq')){
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
}
if(!function_exists('nmeq')){
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
}
if(!function_exists('neq')){
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
}
if(!function_exists('ftmp')){
	/**
	 * テンポラリファイルを作成する
	 * @param string $path
	 * @param string $body
	 */
	function ftmp($path,$body){
		Test::ftmp($path,$body);
	}
}
if(!function_exists('tmp_path')){
	/**
	 * テンポラリファイルを保存するパスを返す
	 * @return string
	 */
	function tmp_path($path=null){
		return Test::tmp_path($path);
	}
}
if(!function_exists('test_browser')){
	/**
	 * テスト用のHttpインスタンスを返す
	 * @return Http
	 */
	function test_browser(){
		return Test::browser();
	}
}
if(!function_exists('test_map_url')){
	/**
	 * mapに定義されたurlをフォーマットして返す
	 * @param string $name
	 * @return string
	 */
	function test_map_url($name){
		list($entry,$map_name) = (strpos($name,'::') !== false) ? explode('::',$name,2) : array(Test::current_entry(),$name);
		$maps = Test::flow_output_maps($entry);
		$args = func_get_args();
		array_shift($args);
		App::branch($entry);
		if(isset($maps[$map_name][sizeof($args)])) return App::url(vsprintf($maps[$map_name][sizeof($args)],$args));
		throw new RuntimeException($name.'['.sizeof($args).'] not found');
	}
}
if(!function_exists('handled_var')){
	/**
	 * テスト用Httpのアクセス結果を取得する
	 * @param string $url
	 * @param string $name
	 * @return string
	 */
	function handled_var($url,$name){
		return Test::handled_var($url,$name);
	}
}
if(!function_exists('b')){
	/**
	 * Httpリクエスト
	 * @return Http
	 */
	function b(){
		return new Http();
	}
}
if(!function_exists('xml')){
	/**
	 * XMLで取得する
	 * @param $xml 取得したXmlオブジェクトを格納する変数
	 * @param $src 対象の文字列
	 * @param $name ノード名
	 * @return boolean
	 */
	function xml(&$xml,$src,$name=null){
		return Tag::setof($xml,$src,$name);
	}
}

