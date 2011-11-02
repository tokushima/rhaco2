<?php
/**
 * extensionを読み込む
 * @param string $module_name
 * @param string $doc
 */
function extension_load($module_name,$doc=null){
	if(!extension_loaded($module_name)){
		try{
			dl($module_name.".".PHP_SHLIB_SUFFIX);
		}catch(Exception $e){
			throw new LogicException("undef ".$module_name."\n".$doc);
		}
	}
}
/**
 * ユニークな名前でクラスを作成する
 * @param string $code
 * @param string $extends
 * @return string $class_name
 */
function create_class($code='',$extends=null,$comment=null){
	if(empty($extends)) $extends = 'Object';
	while(true){
		$class_name = 'U'.md5(uniqid().uniqid('',true));
		if(!class_exists($class_name)) break;
	}
	call_user_func(create_function('',"/**\n".$comment."\n*/\nclass ".$class_name.' extends '.$extends.'{ '.$code.' }'));
	return $class_name;
}
/**
 * referenceを返す
 *
 * @param object $obj
 * @return object
 */
function R($obj){
	if(is_string($obj)){
		$class_name = import($obj);
		return new $class_name;
	}
	return $obj;
}
/**
 * クラスアクセス
 *
 * @param string $class_name
 * @return object
 */
function C($class_name){
	return Object::c(is_object($class_name) ? get_class($class_name) : Lib::import($class_name));
}
/**
 * call_user_func_arrayのエイリアス
 * @param callback $function コールする関数
 * @param array $param_arr 関数に渡すパラメータを指定する配列
 */
function call($function,$param_arr=array()){
	return call_user_func_array($function,$param_arr);
}
/**
 * アノテーション値を返す
 * @param array $prop array($object,$prop_name)
 * @param string $param アノテーション名
 * @param mixed $default 
 */
function a(array $prop,$param,$default=null){
	if(!isset($prop[0])) $prop[0] = $this;
	$res = $prop[0]->a($prop[1],$param);
	return ($res === null) ? $default : $res;
}
/**
 * あるオブジェクトが指定したインタフェースをもつか調べる
 *
 * @param mixed $object
 * @param string $interface
 * @return boolean
 */
function is_implements_of($object,$interface){
	$class_name = (is_object($object)) ? get_class($object) : $object;
	return in_array($interface,class_implements($class_name));
}
/**
 * $classがclassか(interfaceやabstractではなく）
 * @param $class
 * @return boolean
 */
function is_class($class){
	if(!class_exists($class)) return false;
	$ref = new ReflectionClass($class);
	return (!$ref->isInterface() && !$ref->isAbstract());
}

/**
 * Content-Type: text/plain
 */
function header_output_text(){
	Http::send_header("Content-Type: text/plain;");
}
/**
 * 改行付きで出力
 *
 * @param string $value
 * @param boolean $fmt
 */
function println($value,$fmt=null){
	if(php_sapi_name() == 'cli'){
		if(substr(PHP_OS,0,3) == 'WIN'){
			$value = Text::encode($value,'SJIS','UTF-8');
		}else if($fmt !== null){
			$fmt = ($fmt === true) ? '1;34' : (($fmt === false) ? '1;31' : $fmt);
			$value = "\033[".$fmt."m".$value."\033[0m";
		}
	}
	print($value."\n");
}
/**
 * ライブラリのクラス一覧を返す
 * @param $in_vendor
 * @return array
 */
function get_classes($in_vendor=false){
	return Lib::classes(true,$in_vendor);
}
/**
 * importし、クラス名を返す
 * @param string $class_path
 * @return string
 */
function import($class_path){
	return Lib::import($class_path);
}
/**
 * パッケージモジュールをimportする
 * @param string $path
 * @return string
 */
function module($path){
	return Lib::module($path,true);
}
/**
 * パッケージのパスを返す
 * @return string
 */
function module_path($path=null){
	list($file) = debug_backtrace(false);
	$root = Lib::module_root_path($file["file"]);
	return (empty($path)) ? $root : File::absolute($root,$path);
}
/**
 * パッケージのテンプレートのパスを返す
 * @param string $path ベースパスに続くテンプレートのパス
 * @return string
 */
function module_templates($path=null){
	list($file) = debug_backtrace(false);
	$root = Lib::module_root_path($file["file"])."/resources/templates";
	return (empty($path)) ? $root : File::absolute($root,$path);
}
/**
 * パッケージのmediaのパスを返す
 * @param strng $path ベースパスに続くメディアのパス
 * @return string
 */
function module_media($path=null){
	list($file) = debug_backtrace(false);
	$root = Lib::module_root_path($file["file"])."/resources/media";
	return (empty($path)) ? $root : File::absolute($root,$path);
}
/**
 * パッケージ名を返す
 * @return string
 */
function module_package(){
	list($debug) = debug_backtrace(false);
	return Lib::package_path($debug["file"]);
}
/**
 * パッケージルートのクラス名を返す
 * @return string
 */
function module_package_class(){
	list($debug) = debug_backtrace(false);
	$package = Lib::package_path($debug["file"]);
	return substr($package,strrpos($package,".")+1);
}
/**
 * モジュールの定数を取得する
 * def()と対で利用する
 * 
 * @param string $name 設定名
 * @param mixed $default 未設定の場合に返す値
 * @return mixed
 */
function module_const($name,$default=null){
	list($debug) = debug_backtrace(false);
	return App::module_const(Lib::package_path($debug["file"]),$name,$default);
}
/**
 * モジュール定数を配列として受け取る
 * @param string $name 設定名
 * @return mixed[]
 */
function module_const_array($name,$option=null){
	$packege = null;
	list($debug) = debug_backtrace(false);
	$result = App::module_const(Lib::package_path($debug["file"]),$name);
	if(!is_array($result)) $result = ($result === null) ? array() : array($result);
	if(isset($option)){
		if(is_array($option)){
			$names_cnt = sizeof($option);
			$result_cnt = sizeof($result);
			$chunk = array();
			for($i=0;$i<$result_cnt;$i+=$names_cnt){
				$c = array();
				foreach($option as $k => $name) $c[$name] = isset($result[$i+$k]) ? $result[$i+$k] : null;
				$chunk[] = $c;
			}
			$result = $chunk;
		}else if(is_int($option)){
			$num = $option-sizeof($result);
			if($num > 0) $result = array_merge($result,array_fill(0,$num,null));
		}
	}
	return $result;
}
/**
 * 文字列表現を返す
 * @param $obj
 * @return string
 */
function str($obj){
	return Text::str($obj);
}
/**
 * 定義情報を設定する
 * module_const() と対で利用する
 * 
 * @param string $name パッケージ名@設定名
 * @param mixed $value 値
 */
function def($name,$value){
	$args = func_get_args();
	call_user_func_array(array("App","def"),$args);
}
/**
 * アプリケーションのurlを取得する
 *
 * @param string $path
 * @return string
 */
function url($path=null){
	return App::url($path);
}
/**
 * アプリケーションのファイルパスを取得する
 *
 * @param string $path
 * @return string
 */
function path($path=null){
	return App::path($path);
}
/**
 * アプリケーションのワーキングファイルパスを取得する
 *
 * @param string $path
 * @return string
 */
function work_path($path=null){
	return App::work($path);
}
/**
 * 国際化文字列の定義
 * @param $msg
 * @return string
 */
function trans($msg){
	$args = func_get_args();
	return call_user_func_array(array("Gettext","trans"),$args);
}
/**
 * ヒアドキュメントのようなテキストを生成する
 * １行目のインデントに合わせてインデントが消去される
 * @param string $text
 * @return string
 */
function text($text){
	return Text::plain($text);
}
/**
 * アプリケーションXML
 * @param string $path xmlファイルのパス
 */
function app($path=null){
	if($path === null){
		list($debug) = debug_backtrace(false);
		$path = $debug["file"];
	}
	Flow::load($path);
}
/**
 * ディープコピーをする
 * @param mixed $var
 * @return mixed
 */
function deepcopy($var){
	return unserialize(serialize($var));
}
/**
 * カレントのワーキングディレクトリを取得する
 * @return string
 */
function pwd(){
	return str_replace("\\",'/',getcwd());
}
