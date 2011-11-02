<?php
/**
 * ライブラリ制御
 * @author tokushima
 */
class Lib{
	static private $lib_path;
	static private $vendor_path;
	static private $imported = array();
	static private $import_op = array();

	static public function __import__(){
		if(!isset(self::$lib_path)) self::$lib_path = App::path('libs');
		if(!isset(self::$vendor_path)) self::$vendor_path = App::path('vendors');
	}
	/**
	 * ベースパスの設定
	 * @param string $libs_path ライブラリのファイルパス
	 * @param string $vendors_path ベンダのファイルパス
	 */
	static public function config_path($libs_path,$vendors_path=null){
		if(isset($libs_path)) self::$lib_path = $libs_path;
		if(isset($vendors_path)) self::$vendor_path = $vendors_path;
	}
	/**
	 * libsのパスを返す
	 * @return string
	 */
	static public function path(){
		return self::$lib_path;
	}
	/**
	 * vandorsのパスを返す
	 * @return string
	 */
	static public function vendors_path(){
		return self::$vendor_path;
	}
	/**
	 * 指定のクラスに定義されたメソッドか
	 * @param string $realpath クラスのファイルパス
	 * @param strng $class クラス名
	 * @param string $method メソッド名
	 * @return boolean
	 */
	static public function is_self_method($realpath,$class,$method){
		return (method_exists($class,$method) && ($i = new ReflectionMethod($class,$method)) && $i->isStatic() && str_replace("\\","/",$i->getFileName()) == $realpath);
	}
	static private function regist_import($realpath,$package_path=null){
		$class = preg_replace("/^.+\/([^\/]+)\.php$/","\\1",$realpath);
		if(!class_exists($class) && !interface_exists($class)){
			self::$imported[empty($package_path) ? $realpath : $package_path] = self::$imported[$realpath] = $class;
			try{
				ob_start();
					require($realpath);
				ob_get_clean();
				if(self::is_self_method($realpath,$class,'__import__')) call_user_func(array($class,'__import__'));
				if(self::is_self_method($realpath,$class,'__shutdown__')) App::register_shutdown($class);
			}catch(Exception $e){
				unset(self::$imported[empty($package_path) ? $realpath : $package_path]);
				unset(self::$imported[$realpath]);
				throw $e;
			}
		}
		return $class;
	}
	/**
	 * importしたクラスのファイルパスを返す
	 * @param string $package パッケージパス
	 * @return string
	 */
	static public function imported_path($package){
		$class = self::import($package);
		foreach(array_keys(self::$imported,$class) as $p){
			if(is_file($p)) return $p;
		}
		throw new LogicException('no package '.$package);
	}
	/**
	 * ライブラリをインポートする
	 * @param string $package パッケージ名
	 * @return string インポートされたクラス名
	 */
	static public function import($package){
		if(isset(self::$imported[$package])) return self::$imported[$package];
		if(class_exists($package) && ctype_upper($package[0])) return $package;

		foreach(array(self::$lib_path,self::$vendor_path) as $path){
			$realpath = $path.'/'.str_replace('.','/',$package);
			if(is_file($realpath.'.php')){
				$realpath = $realpath.'.php';
				array_unshift(self::$import_op,false);
				break;
			}
			$realpath = $realpath.'/'.preg_replace("/^.+\/([^\/]+)$/","\\1",$realpath).'.php';
			if(is_file($realpath)){
				Gettext::set(dirname($realpath).'/resources/locale/messages/');
				array_unshift(self::$import_op,dirname($realpath));
				break;
			}
		}
		if(self::package_path($realpath) != $package) throw new InvalidArgumentException($package.' not found (import)');
		$class = self::regist_import($realpath,$package);
		array_shift(self::$import_op);
		return $class;
	}
	/**
	 * ライブラリをダウンロードする
	 * @param string $package
	 */
	static public function download($package,$loaded=array()){
		try{
			self::import($package);
		}catch(InvalidArgumentException $e){
			foreach(Rhaco2::repositorys() as $search_path){
				$search_path = str_replace('\\','/',$search_path);
				if(substr($search_path,-1) != '/') $search_path = $search_path.'/';			
				$dl_package = str_replace('/','_',$package);
				$package = preg_replace('/^(.+)_\d+$/','\\1',$package);
	
				try{
					File::untgz($search_path.$dl_package.'.tgz',self::$vendor_path);
					$loaded[$package] = $package;
				    if(is_dir(self::$vendor_path.'/'.str_replace('.','/',$package))){
						foreach(File::ls(self::$vendor_path.'/'.str_replace('.','/',$package),true) as $f){
							if($f->ext() == '.php' && preg_match_all('/[^\w]import\(([\"\'])(.+)\\1/',file_get_contents($f->fullname()),$m)){
								foreach($m[2] as $p){
									if(!isset($loaded[$p])) $loaded = array_merge($loaded,self::download($p,$loaded));
								}
							}
						}
					}else if(is_file($file = self::$vendor_path.'/'.str_replace('.','/',$package).'.php')){
						if(preg_match_all('/[^\w]import\(([\"\'])(.+)\\1/',file_get_contents($file),$m)){
							foreach($m[2] as $p){
								if(!isset($loaded[$p])) $loaded = array_merge($loaded,self::download($p,$loaded));
							}
						}
					}
					return $loaded;
				}catch(InvalidArgumentException $e){}
			}
			throw new LogicException($package.' not found. (download)');
		}
		return array();
	}
	/**
	 * ファイルパス、またはクラス名からパッケージ名を返す
	 * @param string $path ファイルパス
	 * @return string
	 */
	static public function package_path($path){
		try{
			if(is_object($path)) $path = get_class($path);
			$p = $path;
			if(class_exists($p)){
				while(true){
					$ref = new ReflectionClass($p);
					$p = $ref->getFileName();
					if(substr($p,-4) === ".php") break;
					$c = $ref->getParentClass();
					if($c === false) throw new LogicException();
					$p = $c->getName();
				}
			}
			$p = str_replace("\\","/",self::module_root_path($p));
			$b = (strpos($p,self::path()) === 0) ? self::path() : ((strpos($p,self::vendors_path()) === 0) ? self::vendors_path() : null);
			if(isset($b)){
				$p = str_replace('/','.',substr($p,strlen($b) + 1));
				return $p;
			}
		}catch(Exception $e){}
		throw new LogicException('no package '.$path);
	}
	/**
	 * ファイルパス、またはクラス名からパッケージ名、モジュール名を返す
	 * @param string $path ファイルパス
	 * @return string[] package,module
	 */
	static public function module_path($path){
		try{
			$m = null;
			$p = $path;
			if(class_exists($p)){
				$ref = new ReflectionClass($p);
				$p = $ref->getFileName();
			}
			$r = str_replace("\\","/",self::module_root_path($p));
			$m = str_replace('/','.',substr(str_replace($r,'',$p),1,-4));
			$b = (strpos($r,self::path()) === 0) ? self::path() : ((strpos($r,self::vendors_path()) === 0) ? self::vendors_path() : null);

			if(isset($b)){
				$r = str_replace('/','.',substr($r,strlen($b) + 1));
				return array($r,$m);
			}
		}catch(Exception $e){}
		throw new LogicException('no package '.$path);
	}
	/**
	 * モジュールを読み込む
	 * @param string $path モジュールパス
	 */
	static public function module($path){
		if(!isset(self::$import_op[0]) || self::$import_op[0] === false) throw new LogicException('no module package '.$path);
		$realpath = self::$import_op[0].'/'.str_replace('.','/',$path).'.php';
		try{
			if(is_file($realpath)) return self::regist_import($realpath);
		}catch(LogicException $e){
			throw $e;
		}
		throw new LogicException($path.' module not found');
	}
	/**
	 * $file_pathが属するパッケージのパスを返す
	 * @param $file_path ファイルパス
	 * @return string
	 */
	static public function module_root_path($file_path){
		$package = File::dirname($file_path);
		while($package !== null){
			$package_class = File::basename($package);
			if($package_class === null) break;
			if(ctype_upper($package_class[0]) && is_file($package.'/'.$package_class.'.php')) return $package;
			$package = File::dirname($package);
		}
		$file = new File($file_path);
		return substr($file->fullname(),0,strlen($file->ext()) * -1);
	}
	/**
	 * クラス一覧を返す
	 * @param boolean $libs ライブラリを含むか
	 * @param boolean $in_vendor ベンダも含むか
	 * @return string{} path=>name
	 */
	static public function classes($libs=true,$in_vendor=false){
		$class = $package = $serach_path = array();
		if($libs && is_dir(self::$lib_path)) $serach_path[] = self::$lib_path;
		if($in_vendor && is_dir(self::$vendor_path)) $serach_path[] = self::$vendor_path;

		foreach($serach_path as $search){
			foreach(File::dir($search,true) as $dir){
				$c = basename($dir);
				if(ctype_upper($c[0]) && is_file($dir.'/'.$c.'.php')){
					$package[$dir] = $dir;
					$class[str_replace('/','.',str_replace($search.'/','',$dir))] = basename($dir);
				}
			}
		}
		foreach($serach_path as $search){
			foreach(File::ls($search,true) as $f){
				if($f->is_class() && strpos($f->directory(),App::work()) !== 0){
					$bool = true;
					foreach($package as $p){
						if(strpos($f->directory(),$p) !== false){
							$bool = false;
							break;
						}
					}
					if($bool) $class[substr(str_replace('/','.',str_replace($search.'/','',$f->fullname())),0,-4)] = $f->oname();
				}
			}
		}
		return $class;
	}
}