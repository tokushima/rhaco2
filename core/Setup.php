<?php
/**
 * setup制御
 * @author tokushima
 */
class Setup extends Object{
	static private $cmds = array();

	/**
	 * setupを開始する
	 */
	static public function start($exception){
		ini_set('display_errors','On');
		try{
			$req = new Request("_request_=false");
			$maxlen = 0;
			$cmd = $value = null;
	
			if($req->is_vars()){
				$keys = array_keys($req->vars());
				$cmd = array_shift($keys);
				$value = $req->in_vars($cmd);
			}
			self::search_cmd($req,$cmd,$value,$maxlen,__CLASS__);
			if($exception instanceof Exception) throw $exception;
			foreach(Lib::classes(true,true) as $package => $name){
				self::search_cmd($req,$cmd,$value,$maxlen,$package);
			}
			self::info($maxlen);
		}catch(Exceptions $e){
			Log::error($e);
			self::println((string)$e,false);
		}catch(Exception $e){
			Log::error($e);
			self::println($e->getMessage(),false);
		}
	}
	static private function cmd_info($cmd){
		self::println("Usage:");
		$document = self::$cmds[$cmd][2];
		$class_name = self::$cmds[$cmd][0];
		$option = array();
		
		if(preg_match_all("/@.+/",$document,$match)){
			foreach($match[0] as $m){
				if(preg_match("/@(\w+)\s+([^\s]+)\s+\\$(\w+)(.*)/",$m,$p)){
					if($p[2] == '$this' || $p[2] == 'self') $p[2] = $class_name;			
					if($p[1] == 'param'){
						self::println(sprintf('  -%s [(%s) %s]',$cmd,$p[2],trim($p[4])));
					}else{
						$option[$p[3]] = array($p[2],trim($p[4]));
					}
				}else if(preg_match("/@(\w+)\s+\\$(\w+)(.*)/",$m,$p)){
					$option[$p[2]] = array(null,trim($p[3]));
				}
			}
		}
		if(!empty($option)){
			self::println("\n".'  option:');
			$l = Text::length(array_keys($option));

			foreach($option as $k => $v){
				self::println('    '.sprintf('-%s%s %s',str_pad($k,$l),(empty($v[0]) ? '' : ' ('.$v[0].')'),trim($v[1])));
			}
		}
		$document = trim(preg_replace("/@.+/","",$document));
		if(!empty($document)){
			self::println("\n\n".'  description:');
			self::println('    '.str_replace("\n","\n    ",$document)."\n\n");
		}
		exit;
	}
	static private function info($maxlen){
		ksort(self::$cmds);
		$app_info = App::info();
		self::println($app_info["name"].((empty($app_info["summary"]) ? "" : ", ".$app_info["summary"]."."))."\n","1;35");
		$desc = Text::plain($app_info["description"]);
		if(!empty($desc)){
			self::println(str_repeat("=",50));
			self::println($desc);
			self::println(str_repeat("=",50));
			self::println("");
		}
		self::println("try 'php rhaco2.php -help *****' for more information",true);
		foreach(self::$cmds as $name => $method){
			list($summary) = explode("\n",$method[2]);
			self::println("  ".str_pad($name,$maxlen)." : ".$summary);
		}
	}
	static private function search_cmd($req,$cmd,$value,&$maxlen,$class_name){
		try{
			$ref = new ReflectionClass(Lib::import($class_name));
			foreach($ref->getMethods(ReflectionMethod::IS_STATIC) as $method){
				if($method->isStatic() && $method->isPublic() && strpos($method->getName(),"__setup_") === 0 && substr($method->getName(),-2) == '__'){
					$setup_name = substr($method->getName(),8,-2);
					$document = trim(preg_replace("/^[\s]*\*[\s]{0,1}/m","",str_replace(array("/"."**","*"."/"),"",$method->getDocComment())));
					self::$cmds[$setup_name] = array($class_name,$method->getName(),$document);
					if(strlen($setup_name) > $maxlen) $maxlen = strlen($setup_name);
	
					if(isset($cmd) && (isset(self::$cmds[$cmd]) || ($cmd === "help" && isset(self::$cmds[$value])))){
						if($cmd === "help"){
							self::cmd_info($value);
						}else{
							try{
								call_user_func_array(array(Lib::import(self::$cmds[$cmd][0]),self::$cmds[$cmd][1]),array($req,$value));
							}catch(Exceptions $e){
								Log::error($e);
								self::println("  ".(string)$e,false);							
							}catch(Exception $e){
								Log::error($e);
								self::println("  ".$e->getMessage(),false);
							}
						}
						exit;
					}
				}
			}
		}catch(Exception $e){
			Log::error($e);
		}
	}
	/**
	 * po,moファイルを生成する
	 * @param string $value パッケージ名、未指定の場合はアプリケーションが対象
	 */
	static public function __setup_gettext__(Request $req,$value){
		if(empty($value)){
			$base = File::absolute(App::path(),'resources/locale/messages');
			$gettext = new Gettext();
			$gettext->search(dirname(__FILE__));
			$gettext->search(App::path());
			try{
				foreach(Lib::classes(true,true) as $path => $name){
					try{
						$class = Lib::import($path);
						$ref = new ReflectionClass($class);
						if(is_subclass_of($ref->getName(),'Object') && !$ref->isInterface() && !$ref->isAbstract()){
							$obj = new $class();
							foreach($obj->props() as $prop) $gettext->add($obj->a($prop,'label'),$path);
						}
					}catch(Exception $e){}
				}
				$gettext->write(File::absolute($base,'messages-xx.po'));
			}catch(Exception $e){}
			
			foreach(File::ls($base) as $f){
				if($f->is_ext('po') && preg_match("/^messages\-([\w]+)$/",$f->oname(),$lang) && $lang[1] != 'xx'){
					$po = clone($gettext);
					Gettext::mo($po->write($f->fullname()));
				}
			}
			self::println("generate gettext file[s]",true);
		}else{
			$path = Lib::imported_path(Lib::import($value));
			if(strpos($path,Lib::path()) !== 0) throw new InvalidArgumentException('target is a package (lib only)');
			if(basename(dirname($path)) !== substr(basename($path),0,-4)) throw new InvalidArgumentException('package is no folder');
			$package_dir = dirname($path);
			$base = File::absolute($package_dir,'resources/locale/messages');
			$gettext = new Gettext();
			$gettext->search($package_dir);
			$gettext->write(File::absolute($base,'messages-xx.po'));

			foreach(File::ls($base) as $f){
				if($f->is_ext('po') && preg_match("/^messages\-([\w]+)$/",$f->oname(),$lang) && $lang[1] != 'xx'){
					Gettext::mo($gettext->write($f->fullname()));
				}
			}
			self::println("generate gettext file[s]",true);
		}
	}
	/**
	 * testを実行する
	 * @param string $value クラス名
	 * @request string $m メソッド名
	 * @request string $b ブロック名、メソッド指定時のみ有効
	 * @request $fail failを表示する
	 * @request $succes succesを表示する
	 * @request $none noneを表示する
	 */
	static public function __setup_test__(Request $req,$value){
		$level = ($req->is_vars('fail') ? Test::FAIL : 0) | ($req->is_vars('success') ? Test::SUCCESS : 0) | ($req->is_vars('none') ? Test::NONE : 0);
		if($level === 0) $level = (Test::FAIL);
		Test::exec_type($level|Test::COUNT);
		$start_time = microtime(true);
		
		if($req->in_vars('mem') != '') ini_set('memory_limit',$req->in_vars('mem'));
		if(empty($value)){
			Test::verifies();
		}else{
			Test::verify($value,$req->in_vars("m"),$req->in_vars("b"));
		}
		Test::flush();
		self::println(sprintf(" ( %s sec / %s MByte) \n",number_format((microtime(true)-$start_time),3),number_format((memory_get_usage() / 1024 / 1024),3)));
	}
	/**
	 * vendorsを削除しライブラリをimportする
	 */
	static public function __setup_clean__(Request $req,$value){
		if(is_dir(Lib::vendors_path())) File::rm(Lib::vendors_path(),false);
		self::__setup_import__($req,$value);
	}
	/**
	 * ライブラリをimportする
	 * @param string $value パッケージ名
	 */
	static public function __setup_import__(Request $req,$value){
		$packages = array();
		if(empty($value)){
			foreach(File::ls(App::path()) as $f){
				if($f->is_ext('php')){
					$src = file_get_contents($f->fullname());
					if(preg_match_all("/[^\w:](import|R|C)\(([\"\'])([\w\.]+?)\\2\)/",$src,$match)){
						foreach($match[3] as $path) $packages[trim($path)] = trim($path);
					}
					if(Tag::setof($tag,$src,'app')){
						if(preg_match_all("/[^\w](class|session)=([\"\'])([\w\.]+?)\\2/",$src,$match)){
							foreach($match[3] as $path) $packages[trim($path)] = trim($path);
						}
					}
				}
			}
			if(is_dir(Lib::path())){
				foreach(File::ls(Lib::path(),true) as $f){
					if($f->is_ext('php') && preg_match_all("/[^\w:](import|R|C)\(([\"\'])([\w\.]+?)\\2\)/",file_get_contents($f->fullname()),$match)){
						foreach($match[3] as $path) $packages[trim($path)] = trim($path);
					}
				}
			}
		}else{
			$packages[$value] = $value;
		}
		$loaded = array();
		foreach($packages as $p){
			try{
				$realpath = File::path_slash(Lib::path(),null,true).str_replace('.','/',$p);
				if(!is_file($realpath.'.php') && !is_file($realpath.'/'.preg_replace("/^.+\/([^\/]+)$/","\\1",$realpath).'.php')) Lib::import($p);
				println('exists '.$p);
			}catch(InvalidArgumentException $e){
				try{
					$loaded = array_merge($loaded,Lib::download($p));
				}catch(LogicException $e){
					self::println($e->getMessage(),false);
					exit;
				}
			}
		}
		foreach($loaded as $p) self::println('imported '.$p,true);
	}
	/**
	 * ソースドキュメントを表示する
	 * @param string $vlaue クラス名
	 * @request string $m メソッド名
	 */
	static public function __setup_man__(Request $req,$value){
		if(empty($value)){
			$libs = array_keys(Lib::classes(true,true));
			asort($libs);
			$len = Text::length($libs);
	
			self::println("  Imported classes::",true);
			foreach($libs as $path){
				$ref = new ReflectionClass(Lib::import($path));
				$summary = trim(preg_replace("/^[\s]*\*[\s]{0,1}/m","",str_replace(array("/"."**","*"."/"),"",$ref->getDocComment())));
				list($summary) = explode("\n",$summary);
				self::println("    ".str_pad($path,$len)." : ".$summary);
			}
		}else{
			$class = Lib::import($value);
			if($req->is_vars('m')){
				$params = array();
				$name = $document = null;
				
				try{
					$ref = new ReflectionMethod($class,$req->in_vars('m'));
					$name = $ref->getName();
					$document = trim(preg_replace("/^[\s]*\*[\s]{0,1}/m","",str_replace(array("/"."**","*"."/"),"",$ref->getDocComment())));
					foreach($ref->getParameters() as $p){
						$params[$p->getName()] = array(
										'mixed'
										,$p->isPassedByReference()
										,$p->isDefaultValueAvailable()
										,($p->isDefaultValueAvailable() ? $p->getDefaultValue() : null)
										,null
									);
					}
				}catch(ReflectionException $e){
					$ref = new ReflectionClass($class);
					$class_src = File::read($ref->getFileName());
					if(preg_match_all("/\n?.+->call_module\(([\"'])(.+?)\\1/",$class_src,$match,PREG_OFFSET_CAPTURE)){
						foreach($match[0] as $k => $v){
							if($match[2][$k][0] == $req->in_vars('m')){
								$name = $match[2][$k][0];
								$doc = substr($class_src,0,$v[1]);
								$doc_end = strrpos($doc,'*'.'/');
								if($doc_end !== false && substr_count(substr($class_src,$doc_end,$v[1]-$doc_end),"\n") === 0){
									$doc_start = strrpos($doc,'/'.'**');
									$document = preg_replace("/^[\s]*\*[\s]{0,1}/m","",str_replace(array("/"."**","*"."/"),"",substr($doc,$doc_start,$doc_end-$doc_start+2)));
									
									if(preg_match_all("/@param\s+([^\s]+)\s+\\$(\w+)(.*)/",$document,$match)){
										foreach($match[0] as $k => $v){
											$params[$match[2][$k]] = array('mixed',true,false,null.null);
										}
									}
								}
								break;
							}
						}
					}
				}
				if(empty($name)) throw new InvalidArgumentException(sprintf('`%s::%s` not found',$class,$req->in_vars('m')));
				self::println("\n".'class '.$class.' in method '.$name.':');
				$doc = trim(preg_replace("/@.+/","",$document));
				if(!empty($doc)){
					self::println(' Description:');
					self::println('   '.str_replace("\n","\n   ",$doc));
				}
				if(preg_match_all("/@param\s+([^\s]+)\s+\\$(\w+)(.*)/",$document,$match)){
					foreach($match[0] as $k => $v){
						if(isset($params[$match[2][$k]])){
							$params[$match[2][$k]][0] = str_replace(array('$this','self'),$class,$match[1][$k]);
							$params[$match[2][$k]][4] = $match[3][$k];
						}
					}
				}
				self::println("\n".' Parameter:');
				$len = Text::length(array_keys($params));
				foreach($params as $k => $v){
					self::println(sprintf('   %s%s : [%s%s] %s',($v[1] ? '&' : ' '),str_pad($k,$len),$v[0],($v[2] ? '='.(isset($v[3]) ? $v[3] : 'null') : ''),$v[4]));
				}
				if(preg_match("/@return\s+([^\s]+)(.*)/",$document,$match)){
					self::println("\n".' Return:');
					self::println(sprintf('   [%s] %s',$match[1],$match[2]));
				}
			}else{
				$ref = new ReflectionClass($class);
				self::println("\n".'class '.$ref->getName().':');
				
				$document = trim(preg_replace("/^[\s]*\*[\s]{0,1}/m","",str_replace(array("/"."**","*"."/"),"",$ref->getDocComment())));
				$doc = trim(preg_replace("/@.+/","",$document));
				if(!empty($doc)){
					self::println(' Description:');
					self::println('   '.str_replace("\n","\n   ",$doc));
				}
				$methods = $static_methods = $pmethods = $static_pmethods = $properties = $modules = array();
				foreach($ref->getMethods() as $method){
					if(substr($method->getName(),0,1) != '_' && ($method->isPublic() || $method->isProtected())){
						list($line) = explode("\n",trim(preg_replace("/@.+/","",preg_replace("/^[\s]*\*[\s]{0,1}/m","",str_replace(array("/"."**","*"."/"),"",$method->getDocComment())))));
						if($method->isStatic()){
							if($method->getDeclaringClass()->getName() == $ref->getName()){
								if($method->isPublic()){
									$static_methods[$method->getName()] = $line;
								}else{
									$static_pmethods[$method->getName()] = $line;
								}
							}
						}else{
							if($method->isPublic()){
								$methods[$method->getName()] = $line;
							}else{
								$pmethods[$method->getName()] = $line;
							}
						}
					}
				}
				$r = new ReflectionClass($class);
				$d = '';
				while(true){
					$d = $r->getDocComment().$d;
					if(($r = $r->getParentClass()) === false) break;
				}
				$d = preg_replace("/^[\s]*\*[\s]{0,1}/m",'',str_replace(array('/'.'**','*'.'/'),'',$d));
				foreach($ref->getProperties() as $prop){
					if(!$prop->isPrivate()){
						if(substr($prop->getName(),0,1) != "_" && !$prop->isStatic()) $properties[$prop->getName()] = array('mixed',null);
					}
				}
				if(preg_match_all("/@var\s([\w_]+[\[\]\{\}]*)\s\\\$([\w_]+)(.*)/",$d,$m)){
					foreach($m[2] as $k => $n){
						if(isset($properties[$n])) $properties[$n] = array($m[1][$k],trim(preg_replace('/^(.*)@.*$/','\\1',$m[3][$k])));
					}
				}
				$class_src = implode("\n",array_slice(explode("\n",File::read($ref->getFileName())),$ref->getStartLine()-1,$ref->getEndLine()-$ref->getStartLine()));
				if(preg_match_all("/\n?.+->call_module\(([\"'])(.+?)\\1/",$class_src,$match,PREG_OFFSET_CAPTURE)){
					foreach($match[0] as $k => $v){
						$summary = null;
						$doc = substr($class_src,0,$v[1]);
						$doc_end = strrpos($doc,'*'.'/');
						if($doc_end !== false && substr_count(substr($class_src,$doc_end,$v[1]-$doc_end),"\n") === 0){
							$doc_start = strrpos($doc,'/'.'**');
							$module_doc = preg_replace("/^[\s]*\*[\s]{0,1}/m","",str_replace(array("/"."**","*"."/"),"",substr($doc,$doc_start,$doc_end-$doc_start+2)));
							list($summary) = explode("\n",trim(preg_replace("/@.+/","",$module_doc)));
						}
						$modules[$match[2][$k][0]] = $summary;
					}
				}
				ksort($methods);
				ksort($static_methods);
				ksort($pmethods);
				ksort($static_pmethods);
				ksort($properties);
				ksort($modules);
				$len = Text::length(array_merge(array_keys($static_methods),array_keys($methods),array_keys($properties),array_keys($modules)));
				if(!empty($static_methods)){
					self::println("\n".'  Static methods defined here:');
					foreach($static_methods as $k => $v) self::println('    '.str_pad($k,$len).' : '.$v);
				}
				if(!empty($methods)){
					self::println("\n".'  Methods defined here:');
					foreach($methods as $k => $v) self::println('    '.str_pad($k,$len).' : '.$v);
				}
				if(!empty($properties)){
					self::println("\n".'  Properties defined here:');
					foreach($properties as $k => $v) self::println('    '.str_pad($k,$len).' : ('.$v[0].') '.$v[1]);
				}
				if(!empty($modules)){
					self::println("\n".'  Modules defined here:');
					foreach($modules as $k => $v) self::println('    '.str_pad($k,$len).' : '.$v);
				}
				if(!empty($static_pmethods)){
					self::println("\n".'  (Protected) Static methods defined here:');
					foreach($static_methods as $k => $v) self::println('    '.str_pad($k,$len).' : '.$v);
				}
				if(!empty($pmethods)){
					self::println("\n".'  (Protected) Methods defined here:');
					foreach($pmethods as $k => $v) self::println('    '.str_pad($k,$len).' : '.$v);
				}
			}
		}
		self::println("\n");
	}
	/**
	 * 定義名のリスト
	 */
	static public function __setup_def__(Request $req,$value){
		$consts = array();
		foreach(array_keys(Lib::classes(true,true)) as $package){
			$package_name = preg_replace("/^.+\.(\w+)$/","\\1",$package);
			$php = File::absolute(Lib::path(),str_replace('.','/',$package)).'.php';
			if(!is_file($php)) $php = File::absolute(Lib::path(),str_replace('.','/',$package).'/'.$package_name).'.php';
			if(!is_file($php)) $php = File::absolute(Lib::vendors_path(),str_replace('.','/',$package)).'.php';
			if(!is_file($php)) $php = File::absolute(Lib::vendors_path(),str_replace('.','/',$package).'/'.$package_name).'.php';

			$files = array();
			if(is_file($php)){
				if(strpos($php,$package_name.'/'.$package_name.'.php') !== false){
					foreach(File::ls($php) as $file){
						if($file->is_ext('php')) $files[] = $file->fullname();
					}
				}else{
					$files[] = $php;					
				}
			}
			$docs = array();
			foreach($files as $file){
				$class_src = str_replace(array("\r\n","\r"),"\n",File::read($file));

				if(preg_match_all('/module_const\((["\'])(.+?)\\1/',$class_src,$match)){
					foreach($match[2] as $name) $consts[$package.'@'.trim($name)] = '';
				}
				if(preg_match_all('/module_const_array\((["\'])(.+?)\\1/',$class_src,$match)){
					foreach($match[2] as $name) $consts[$package.'@'.trim($name)] = '';
				}
				if(preg_match_all("/@const\s+([^\s]+)\s+\\$(\w+)(.*)/",$class_src,$match)){
					foreach($match[0] as $k => $v) $docs[$package.'@'.trim($match[2][$k])] = array($match[1][$k],trim($match[3][$k]));
				}
				if(preg_match_all("/@const\s+\\$(\w+)(.*)/",$class_src,$match)){
					foreach($match[0] as $k => $v) $docs[$package.'@'.trim($match[1][$k])] = array('string',trim($match[2][$k]));
				}
			}
			foreach($docs as $k => $v){
				if(isset($consts[$k])) $consts[$k] = $docs[$k];
			}
		}
		$len = Text::length(array_keys($consts));

		self::println("Define list:",true);
		foreach($consts as $k => $v){
			self::println("    ".(App::defined($k) ? "[*]" : "[-]")." ".str_pad($k,$len)." : ".(empty($v) ? '' : sprintf('(%s) %s',$v[0],$v[1])));
		}
	}
	/**
	 * .htaccessを作成する
	 * @param string $value base path
	 */
	static public function __setup_htaccess__(Request $req,$value){
		$path = str_replace("\\","/",getcwd())."/.htaccess";
		if(empty($value)) $value = "/".basename(getcwd());
		if(substr($value,0,1) !== "/") $value = "/".$value;

		$apps = array();
		foreach(File::ls(App::path()) as $f){
			if($f->is_ext('php') && Tag::setof($t,$f->get(),'app')) $apps[$f->oname()] = true;
		}
		uasort($apps,'strnatcmp');
		krsort($apps);
		if(isset($apps['index'])){
			unset($apps['index']);
			$apps['index'] = 'index';
		}
		$rules = "RewriteEngine On\n"
					."RewriteBase ".$value."\n\n";		
		foreach($apps as $app => $v){
			$rules .= "RewriteCond %{REQUEST_FILENAME} !-f\n"
						."RewriteCond %{REQUEST_FILENAME} !-d\n"
						."RewriteRule ^".(($app == 'index') ? '' : $app)."[/]{0,1}(.*)\$ ".$app.".php/\$1?%{QUERY_STRING} [L]\n\n";
		}
		$ex = is_file($path);
		File::write($path,$rules);
		self::println(($ex ? 'rewrite ' : 'create ').$path,true);
	}
	/**
	 * アプリケーションのひな形を作成する
	 * @param string $value アプリケーションxmlの名前
	 */
	static public function __setup_new__(Request $req,$value,$op=true){
		if(empty($value)) $value = "index";
		$path = str_replace("\\","/",getcwd())."/".$value.".php";

		if(is_file($path)) throw new InvalidArgumentException($path.": File exists");
		File::write($path
						,"<?php require dirname(__FILE__).\"/rhaco2.php\"; app(); ?>\n"
						."<a"."pp>\n"
							."\t<handler>\n"
							."\t\t<map url=\"\" class=\"yourdomain.HelloWorld\" method=\"sample\" template=\"index.html\" />\n"
							."\t</handler>\n"
						."</"."app>\n"
					);
		self::println('Create '.$path,true);
		
		foreach(array('resources/templates','resources/media','libs') as $p){
			if(!is_dir(App::path($p))){
				File::mkdir(App::path($p));
				self::println('Create '.App::path($p),true);
			}
		}
		$path = App::path('libs/yourdomain/HelloWorld.php');
		if(!is_file($path)){
			File::write($path
							,"<?php\n"
							."class HelloWorld extends Flow{\n"
							."\tpublic function sample(){\n"
							."\t\t\$this->vars('message','hello world');\n"
							."\t}\n"
							."}\n"
						);
			self::println('Create '.$path,true);
		}
		$path = App::path('resources/media/style.css');
		if(!is_file($path)){
			File::write($path
							,"body{ font-family: Georgia; }\n"
							."h1{ font-style: italic; }\n"
						);
			self::println('Create '.$path,true);
		}
		$path = App::path('resources/templates/index.html');
		if(!is_file($path)){
			File::write($path
							,"<html>\n"
							."<head>\n"
							."\t<title>new page</title>\n"
							."\t<link href=\"style.css\" rel=\"stylesheet\" type=\"text/css\" />\n"
							."</head>\n"
							."<body>\n"
							."\t<h1>{\$message}</h1>\n"
							."\t<a href=\"http://rhaco.org/\">powered by rhaco</a>\n"
							."</body>\n"
							."</html>\n"
						);
			self::println('Create '.$path,true);
		}
		$settings_path = File::absolute(getcwd(),"__settings__.php");
		$pwd = str_replace("\\","/",getcwd());
		
		if(!is_file($settings_path)){
			$ref = new ReflectionClass("Object");
			$jump_path = str_replace("\\","/",$ref->getFileName());
			$url = Command::stdin("Application URL","http://localhost/".basename($pwd));
			if(!empty($url) && substr($url,-1) != "/") $url .= "/";
			$work = Command::stdin("Working Directory",App::work());
			$mode = Command::stdin("Application Mode",'dev');
			App::config_path($pwd,$url,$work,$mode);
			$config = sprintf(Text::plain('
								<?php
								App::config_path(__FILE__,"%s","%s","%s");
							'),$url,$work,$mode
						);
			File::write($settings_path,$config."\n");
			self::println('write '.$settings_path,true);

			if(is_file(File::absolute(getcwd(),"index.php"))){
				if(Command::stdin('Create .htaccess','y',array('y','n')) == 'y'){
					$base = Command::stdin('rewrite base',basename(getcwd()));
					self::__setup_htaccess__($req,$base);
				}
			}
			self::__setup_log__($req,Command::stdin('Display Log','off',array('on','off')));		
		}
	}
	/**
	 * ログを制御する
	 * @param string $value 標準出力に出力する(on/off)
	 * @request string $exception 例外の詳細表示(on/off)
	 */
	static public function __setup_log__(Request $req,$value){
		if(!is_file(App::path("__settings__.php"))) File::write(App::path("__settings__.php"),"<?php\n");
		$src = File::read(App::path("__settings__.php"));
		$level = Command::stdin('log level',Log::current_level(),array('none','error','warn','info','debug'));
		$log = 'Log::config_level("'.$level.'",'.(($value == "on") ? "true" : "false").');';
		if(preg_match("/Log::config_level\(.+;/",$src,$match)){
			$src = str_replace($match[0],$log,$src);
		}else{
			$src = $src."\n".$log;
		}
		if($req->is_vars("exception")){
			$log = 'Log::config_exception_trace('.(($req->in_vars("exception") == "on") ? "true" : "false").');';
			if(preg_match("/Log::config_exception_trace\(.+;/",$src,$match)){
				$src = str_replace($match[0],$log,$src);
			}else{
				$src = $src."\n".$log;
			}
		}
		self::update_settings($src);
	}
	static private function update_settings($src){
		File::write(App::path("__settings__.php"),$src);
		self::println('update __settings__.php',true);
	}
	/**
	 * アプリケーションの設定を変更する
	 * @request string $url アプリケーションのURL
	 * @request string $mode アプリケーションのモード
	 * @request string $work アプリケーションのワーキングフォルダ
	 */
	static public function __setup_config__(Request $req,$value){
		if($req->is_vars('url') || $req->is_vars('mode') || $req->is_vars('work')){
			$url = $req->in_vars('url',App::url());
			$mode = $req->in_vars('mode',App::mode());
			$work = $req->in_vars('work',App::work());

			if(!is_file(App::path("__settings__.php"))) File::write(App::path("__settings__.php"),"<?php\n");
			$src = File::read(App::path("__settings__.php"));
			$config = sprintf('App::config_path(__FILE__,"%s","%s","%s");',$url,$work,$mode);
			if(preg_match("/App::config_path\(.+/",$src,$match)){
				$src = str_replace($match[0],$config,$src);
			}else{
				$src = $src.$config."\n";
			}
			self::update_settings($src);
			self::println('__settings__.php changed',true);
		}else{
			self::println('mode: '.App::mode(),true);
			self::println('url : '.App::url(),true);
			self::println('work: '.App::work(),true);
		}
	}
	/**
	 * アプリケーションのインストレーションを表示する
	 * @param string $value アプリケーションファイル名
	 */
	static public function __setup_installation__(Request $req,$value){
		$installation = null;
		if(empty($value)) $value = 'index';
		if(is_file(App::path($value.'.php'))){
			$src = File::read(App::path($value.'.php'));
			if(Tag::setof($tag,$src,'app')){
				$installation = Text::plain($tag->f('installation.value()'));
			}
		}
		self::println('Installation',true);
		self::println(str_repeat('-',70));
		self::println(str_replace("\t",'  ',$installation));
		self::println(str_repeat('-',70));
	}
	final static private function space_line_count($value){
		return sizeof(explode("\n",$value)) - 1;
	}
	static private function println($value,$fmt=null){
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
}
