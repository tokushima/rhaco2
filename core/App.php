<?php
/**
 * アプリケーション定義
 * @author tokushima
 */
class App{
	static private $def = array();
	static private $shutdown = array();

	static private $path;
	static private $work;
	static private $common;
	static private $mode = 'noname';
	static private $url;
	static private $surl;

	static private $branch;

	/**
	 * 定義情報を設定
	 * @param string $name パッケージ名@定義名
	 * @param mixed $value 値
	 */
	static public function def($name,$value){
		if(!isset(self::$def[$name])){
			if(func_num_args() > 2){
				$args = func_get_args();
				array_shift($args);
				$value = $args;
			}
			self::$def[$name] = $value;
		}
	}
	/**
	 * 定義情報を取得
	 * @param string $package パッケージ名
	 * @param string $name 定義名
	 * @param mixed $default 未定義の場合の値
	 */
	static public function module_const($package,$name,$default=null){
		return (isset(self::$def[$package."@".$name])) ? self::$def[$package."@".$name] : $default;
	}
	/**
	 * 定義情報があるか
	 * @param $name 定義名
	 * @return boolean
	 */
	static public function defined($name){
		return isset(self::$def[$name]);
	}
	/**
	 * 特定キーワードの定義情報一覧を返す
	 * @param string $key キーワード
	 * @return string{}
	 */
	static public function constants($key){
		$result = array();
		foreach(self::$def as $k => $value){
			if(strpos($k,$key) === 0) $result[$k] = $value;
		}
		return $result;
	}
	/**
	 * 終了処理するクラスを登録する
	 * @param Object $object 登録するインスタンス
	 */
	static public function register_shutdown($object){
		self::$shutdown[] = array($object,'__shutdown__');
	}
	/**
	 * 終了処理を実行する
	 */
	static public function __shutdown__(){
		krsort(self::$shutdown,SORT_NUMERIC);
		foreach(self::$shutdown as $s) call_user_func($s);
	}
	/**
	 * 初期定義
	 *
	 * @param string $path アプリケーションのルートパス
	 * @param string $url アプリケーションのURL
	 * @param string $work 一時ファイルを書き出すパス
	 * @param string $mode モード
	 * @param string $vendors_path vendorsのパス
	 * @param string $libs_path libsのパス
	 * @param string $common_path __common__.phpのパス
	 */
	static public function config_path($path,$url=null,$work=null,$mode=null,$vendors_path=null,$libs_path=null,$common_path=null){
		if(empty($path)){
			$debug = debug_backtrace(false);
			$debug = array_pop($debug);
			$path = $debug['file'];
		}
		if(is_file($path)) $path = dirname($path);
		self::$path = preg_replace("/^(.+)\/$/","\\1",str_replace("\\","/",$path))."/";

		if(isset($work)){
			if(is_file($work)) $work = dirname($work);
			self::$work = preg_replace("/^(.+)\/$/","\\1",str_replace("\\","/",$work))."/";
		}else{
			self::$work = self::$path.'work/';
		}
		if(!empty($url)){
			$r = (isset($_SERVER['HTTP_X_FORWARDED_HOST']) ? $_SERVER['HTTP_X_FORWARDED_HOST'] : (
						isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost')));
			$url = str_replace('://*','://'.$r,$url);
			if(substr($url,-1) !== '/') $url = $url.'/';
			self::$url = $url;
			self::$surl = str_replace('http://','https://',$url);
		}
		self::$mode = (empty($mode)) ? 'noname' : $mode;
		self::$common = (empty($common_path) ? dirname(__FILE__) : preg_replace("/^(.+)\/$/","\\1",str_replace("\\","/",$common_path))).'/';
		if(isset($vendors_path) || isset($libs_path)) Lib::config_path($libs_path,$vendors_path);
		if(isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) && !empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])){
			list($lang)	= explode(',',$_SERVER['HTTP_ACCEPT_LANGUAGE']);
			list($lang)	= explode('-',$lang);
			Gettext::lang($lang);
			Gettext::set(self::$path.'resources/locale/messages/');
		}
	}
	static public function load_common(){
		if(!isset(self::$common)) self::$common = dirname(__FILE__).'/';
		if(is_file($f=(self::$common.'__common_'.self::mode().'__.php'))) require_once($f);
		if(is_file($f=(self::$common.'__common__.php'))) require_once($f);
	}
	/**
	 * アプリケーションのブランチ名
	 * @param string $branch セットするブランチ名
	 */
	static public function branch($branch=null){
		if(isset($branch)){
			if($branch instanceof File) $branch = $branch->oname();
			self::$branch = ($branch == 'index') ? null : $branch;
		}
		return self::$branch;
	}
	/**
	 * アプリケーションパスとの絶対パスを返す
	 * @param string $path 追加のパス
	 * @return string
	 */
	static public function path($path=null){
		if(!isset(self::$path)) self::$path = dirname(self::called_filename()).'/';
		return File::absolute(self::$path,$path);
	}
	/**
	 * workパスとの絶対パスを返す
	 * @param string $path 追加のパス
	 * @return string
	 */
	static public function work($path=null){
		if(!isset(self::$work)) self::$work = self::path('work').'/';
		if(isset($path[0]) && $path[0] == '/') $path = substr($path,1);
		return File::absolute(self::$work,$path);		
	}
	/**
	 * アプリケーションURLとの絶対パスを返す
	 * @param string $path 追加のパス
	 * @param boolean $branch ブランチ名を結合するか
	 * @return string
	 */
	static public function url($path=null,$branch=true){
		if(!isset(self::$url)){
			list($d) = debug_backtrace(false);
			$f = str_replace("\\",'/',$d['file']);
			$url = dirname('http://localhost/'.preg_replace("/.+\/workspace\/(.+)/","\\1",$f));
			self::$url = $url.'/';
		}
		$basepath = self::$url.(($branch && !empty(self::$branch)) ? self::$branch.'/' : '');
		return File::absolute($basepath,$path);
	}
	/**
	 * アプリケーションURLとの絶対パスをhttpsとして返す
	 * @param string $path 追加のパス
	 * @param boolean $branch ブランチ名を結合するか
	 * @return string
	 */
	static public function surl($path=null,$branch=true){
		if(!isset(self::$surl)){
			self::$surl = str_replace('http://','https://',self::url());			
		}
		$basepath = self::$surl.(($branch && !empty(self::$branch)) ? self::$branch.'/' : '');
		return File::absolute($basepath,$path);
	}
	/**
	 * 現在のアプリケーションモードを取得
	 * @return string
	 */
	static public function mode(){
		return self::$mode;
	}
	/**
	 * 呼び出しもとのファイル名を返す
	 * @return string
	 */
	static public function called_filename(){
		$debug = debug_backtrace(false);
		$root = array_pop($debug);
		return (isset($root['file'])) ? str_replace("\\","/",$root['file']) : null;
	}
	/**
	 * アプリケーションの説明
	 * @param string $path アプリケーションXMLのファイルパス
	 * @return string{} "title"=>"..","summary"=>"..","description"=>"..","installation"=>".."
	 */
	static public function info($path=null){
		$name = $summary = $description = $installation = $info = '';
		if(empty($path)) $path = self::path();
		$app = empty(self::$branch) ? 'index' : self::$branch;
		$filename = is_file(File::absolute($path,$app.'.php')) ?
						File::absolute($path,$app.'.php') :
						(is_file(File::absolute($path,basename($path).'.php')) ? File::absolute($path,basename($path).'.php') : null);
		if(is_file($filename)){
			$name = basename(dirname($filename));
			$src = File::read($filename);
			if(Tag::setof($t,$src,'app')){
				$summary = $t->in_param('summary');
				$name = $t->in_param('name',$t->in_param('label',$name));
				$info = $t->in_param('info');
				$description = $t->f('description.value()');
				$installation = $t->f('installation.value()');
			}else if(preg_match("/\/"."\*\*(.+?)\*\//ms",$src,$match)){
				$description = trim(preg_replace("/^[\s]*\*[\s]{0,1}/m","",str_replace(array("/"."**","*"."/"),"",$match[0])));
				$info = (preg_match("/@info[\s](.+)/",$description,$match)) ? trim($match[1]) : null;
				if(preg_match("/@name[\s]+(.+)/",$description,$match)){
					$description = str_replace($match[0],"",$description);
					$name = trim($match[1]);
				}
				if(preg_match("/@summary[\s]+(.+)/",$description,$match)){
					$description = str_replace($match[0],"",$description);
					$summary = trim($match[1]);
				}
			}
		}
		return array('name'=>$name,'summary'=>$summary,'description'=>$description,'installation'=>$installation,'filename'=>$filename,'info'=>$info);
	}
}