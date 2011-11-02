<?php
/**
 * PEAR ライブラリ制御
 * 
 * @const string $pear_path pearパッケージを配置するファイルパス
 * @author yabeken
 * @author Kazutaka Tokushima
 * @license New BSD License
 */
class Pea{
	static private $pear_path;
	static private $data_dir;
	static private $set_error_handler = false;

	static private $channel = array();
	static private $install = array();

	static private function browser(){
		$http = new Http();
		$http->agent("Pea (PEAR Client)");
		return $http;
	}
	static private function init(){
		self::$pear_path = File::path_slash(module_const("pear_path",App::path("pear")),null,true);
		self::$data_dir = self::$pear_path."data";
		set_include_path(self::$pear_path.PATH_SEPARATOR.get_include_path());
		if(!File::exist(self::$pear_path."PEAR.php")) self::install("pear.php.net/PEAR","stable",true);
		
		define("__PEA_DATA_DIR__",self::$data_dir);
	}
	/**
	 * PEAR ライブラリを読み込む
	 * @param string $package_path パッケージ名
	 * @param string $target_state ステート名
	 */
	static public function import($package_path,$target_state="stable"){
		if(self::$pear_path === null) self::init();
		list($domain,$package_name,$package_version) = self::parse_package($package_path);
		$path = self::$pear_path.strtr($package_name,"_","/").".php";
		if(!File::exist($path)) self::install($package_path,$target_state,true);
		include_once($path);
	}
	static private function install($package_path,$target_state,$dependency){
		if(self::$pear_path === null) self::init();
		list($domain,$package_name,$package_version) = self::parse_package($package_path);
		if(isset(self::$install[strtolower($domain."/".$package_name)])) return;

		if(!isset(self::$channel[$domain])) self::channel_discover($domain);
		$allreleases_xml = self::$channel[$domain]."/r/".strtolower($package_name)."/allreleases.xml";
		if(!Tag::setof($a,self::browser()->do_get($allreleases_xml)->body(),"a")) throw new RuntimeException($package_path." not found");

		$target_package = $package_name;
		$target_version = null;
		$state_no = array("stable"=>0,"beta"=>1,"alpha"=>2,"devel"=>3);
		if(!isset($state_no[$target_state])) throw new RuntimeException($target_state." is invalid state.");
		
		foreach($a->in("r") as $r){
			$v = $r->f("v.value()");
			if(!empty($package_version)){
				if($package_version == $v) $target_version = $v;
			}else{
				$s = $r->f("s.value()");
				if($state_no[$s] <= $state_no[$target_state]){
					$target_version = $v;
				}
			}
			if(!empty($target_version)) break;
		}
		if(empty($target_version)){
			$all = array();
			foreach($a->in("r") as $r){
				$all[] = $r->f("v.value()")." [".$r->f("s.value()")."]";
			}
			throw new RuntimeException($package_path." not found.".(empty($all) ? "" : " all versions: ".implode(", ",$all)));
		}
		$download_base = self::$pear_path."_download/";
		$download_path = $download_base.str_replace(array(".","-"),"_",$domain)."_".$target_package."_".strtr($target_version,".","_");
		$download_url = "http://".$domain."/get/".$target_package."-".$target_version.".tgz";
		if(!File::exist($download_path)) self::download($download_url,$download_path);

		try{
			$package_xml = File::exist(File::path($download_path,"package.xml")) ? File::path($download_path,"package.xml") : File::path($download_path,"package2.xml");
			self::$install[strtolower($domain."/".$target_package)] = $package_xml;

			if(Tag::setof($package,File::read($package_xml),"package")){
				switch($package->in_param("version")){
					case "1.0":
						if($dependency){
							foreach($package->f("deps.in(dep)") as $dep){
								if($dep->in_param("type")=="pkg" && $dep->in_param("optional") == "no"){
									self::install($dep->value(),$target_state,$dependency);
								}
							}
						}
						foreach($package->f("release.filelist.in(file)") as $file) self::copy($file,$target_package,$target_version,$download_path);
						break;
					case "2.0":
						if($dependency){
							foreach($package->f("dependencies.required.in(package)") as $dep){
								self::install($dep->f("channel.value()")."/".$dep->f("name.value()"),$target_state,$dependency);
							}
						}
						foreach($package->f("contents.in(dir)") as $dir){
							foreach($dir->in("file") as $file) self::copy($file,$target_package,$target_version,$download_path);
						}
						break;
					default:
						throw new RuntimeException("unknown package version");
				}
			}
		}catch(Exception $e){
			File::rm($download_base);
			throw $e;
		}
		unset(self::$install[strtolower($domain."/".$target_package)]);
		if(empty(self::$install)) File::rm($download_base);
	}
	static private function download($url,$outpath){
		$tmpname = File::absolute($outpath,File::temp_path($outpath));
		if(self::browser()->do_download($url,$tmpname)->status() != 200){
			File::rm($tmpname);
			throw new RuntimeException("download failed [{$url}]");
		}
		File::untgz($tmpname,$outpath);
		File::rm($tmpname);
	}
	static private function parse_package($package_path){
		list($domain,$name) = (strpos($package_path,"/")===false) ? array("pear.php.net",$package_path) : explode("/",$package_path,2);
		list($name,$version) = (strpos($name,"-")===false) ? array($name,null) : explode("-",$name,2);
		return array($domain,$name,$version);
	}
	static private function channel_discover($domain){
		if(Tag::setof($channel,self::browser()->do_get("http://{$domain}/channel.xml")->body())){
			$url = $channel->f("rest.baseurl[0].value()");
			if(!empty($url)){
				self::$channel[$domain] = (substr($url,-1)=="/") ? substr($url,0,-1) : $url;
				return self::$channel[$domain];
			}
		}
		throw new RuntimeException("channel [{$domain}] not found");
	}
	static private function copy(Tag $file,$target_package,$target_version,$download_path){
		$role = $file->in_param("role");
		$baseinstalldir = self::$pear_path.$file->in_param("baseinstalldir");
		$name = $file->in_param("name");
		$src = File::path($download_path,File::path($target_package."-".$target_version,$name));

		if($role == "php"){
			$dest = File::path($baseinstalldir,$name);
			File::copy($src,$dest);
			$src = File::read($dest);
			
			if(preg_match_all("/[^\\\\]([\"']).*@data_dir@/",$src,$match)){
				foreach($match[0] as $k => $v){
					$src = str_replace($v,str_replace("@data_dir@",$match[1][$k].".constant('__PEA_DATA_DIR__').".$match[1][$k],$v),$src);
				}
			}
			File::write($dest,$src);
		}else if($role == "data"){
			File::copy($src,File::absolute(self::$data_dir."/".$target_package."/",$name));
		}
	}
	/**
	 * PEAR パッケージをインストール
	 * @param string $value パッケージ名
	 * @request string $state (stable, beta, alpha, devel)
	 * @request string $dependency (on, off) 依存関係のあるライブラリもインストールするか、通常はon
	 */
	static public function __setup_pear_install__(Request $req,$package){
		if(empty($package)) throw new RuntimeException("package name is empty");
		self::install($package,$req->in_vars("state","stable"),($req->in_vars("dependency") != "off"));
		println("installed ".$package);
	}
	static public function __error_handler__($errno,$errstr,$errfile,$errline){
		switch($errno){
			case E_ERROR:
			case E_CORE_ERROR:
			case E_COMPILE_ERROR:
			case E_USER_ERROR:
			case E_RECOVERABLE_ERROR:
				throw new ErrorException($errstr,0,$errno,$errfile,$errline);
			default:
				return true;
		}
	}
	/**
	 * Pear用のエラー制御を開始する
	 * E_*_ERROR 以外はエラーとしない
	 */
	static public function begin_loose_syntax(){
		if(self::$set_error_handler === false){
			set_error_handler(array(__CLASS__,"__error_handler__"),E_ALL);
			Log::debug(__METHOD__);
		}
		self::$set_error_handler = true;
	}
	/**
	 * Pear用のエラー制御を終了する
	 * エラー制御を元に戻す
	 */
	static public function end_loose_syntax(){
		if(self::$set_error_handler){
			restore_error_handler();
			Log::debug(__METHOD__);
		}
		self::$set_error_handler = false;
	}
}
