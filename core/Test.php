<?php
/**
 * テスト処理
 * @author tokushima
 */
class Test extends Object{
	const SUCCESS = 2;
	const NONE = 4;
	const FAIL = 8;
	const COUNT = 16;
	static private $exec_type;
	static private $each_flush = false;
	static private $result = array();
	static private $current_class;
	static private $current_entry;
	static private $current_method;
	static private $current_file;
	static private $in_test = false;
	static private $flow_output_maps;
	static private $current_map_test_file;

	/**
	 * 表示種類の定義
	 * @param int $type Test::NONE Test::FAIL Test::SUCCESSによる論理和
	 */
	final static public function exec_type($type){
		self::$exec_type = decbin($type);
	}
	/**
	 * Httpインスタンスを返す
	 * @return Http
	 */
	final static public function browser(){
		File::mkdir(self::tmp_path());
		return new Http("api_url=".App::url());
	}
	/**
	 * テストの実行毎にflushさせるようにする
	 */
	final static public function each_flush(){
		self::$each_flush = true;
	}
	/**
	 * 結果を取得する
	 * @return string{}
	 */
	final public static function get(){
		return self::$result;
	}
	/**
	 * 結果をクリアする
	 */
	final public static function clear(){
		self::$result = array();
	}
	/**
	 * 結果を出力しバッファをクリアする
	 */
	final public static function flush(){
		print(new self());
		self::clear();
	}
	final static public function search_path(){
		return array(App::path(),App::path('tests').'/',Lib::path());
	}
	final static private function get_unittest($filename){
		$result = array();
		$result['@']['line'] = 0;
		$result['@']['blocks'][] = array($filename,$filename,0);
		return array('filename'=>$filename,'class_name'=>null,'entry_name'=>null,'tests'=>$result);
	}
	final static private function get_entry_doctest($filename){
		$entry = basename($filename,'.php');
		$result = array();
		$read = Text::uld(File::read($filename));

		if(Tag::setof($app,$read,'app')){
			if(preg_match_all("/<!--"."-(.+?)-->/s",$read,$match,PREG_OFFSET_CAPTURE)){
				foreach($match[1] as $key => $m){
					$line = self::space_line_count(substr($read,0,$m[1]));
					$test_block = str_repeat("\n",$line).$m[0];
					$test_block_name = preg_match("/^[\s]*#(.+)/",$test_block,$match) ? trim($match[1]) : null;
					if(trim($test_block) == '') $test_block = null;
					
					$result['@']['line'] = $line;
					$result['@']['blocks'][] = array($test_block_name,$test_block,$line);
				}
				self::merge_setup_teardown($result);
			}
		}
		return array('filename'=>$filename,'class_name'=>null,'entry_name'=>$entry,'tests'=>$result);
	}
	final static private function get_doctest($path){
		$result = array();
		$class_name = (!class_exists($path) && !interface_exists($path)) ? Lib::import($path) : $path;
		$rc = new ReflectionClass($class_name);
		$filename = $rc->getFileName();
		$class_src_lines = file($filename);
		$class_src = implode('',$class_src_lines);
		
		foreach($rc->getMethods() as $method){
			if($method->getDeclaringClass()->getName() == $rc->getName()){
				$method_src = implode('',array_slice($class_src_lines,$method->getStartLine()-1,$method->getEndLine()-$method->getStartLine(),true));				
				$result = array_merge($result,self::get_method_doctest($rc->getName(),$method->getName(),$method->getStartLine(),$method->isPublic(),$method_src));
				$class_src = str_replace($method_src,str_repeat("\n",self::space_line_count($method_src)),$class_src);
			}
		}
		$result = array_merge($result,self::get_method_doctest($rc->getName(),'@',1,false,$class_src));
		self::merge_setup_teardown($result);
		return array('filename'=>$filename,'class_name'=>$class_name,'entry_name'=>null,'tests'=>$result);
	}
	final static private function merge_setup_teardown(&$result){
		if(isset($result['@']['blocks'])){
			foreach($result['@']['blocks'] as $k => $block){
				if($block[0] == '__setup__' || $block[0] == '__teardown__'){
					$result['@'][$block[0]] = array($result['@']['blocks'][$k][2],$result['@']['blocks'][$k][1]);
					unset($result['@']['blocks'][$k]);
				}
			}
		}
	}
	final static private function get_method_doctest($class_name,$method_name,$method_start_line,$is_public,$method_src){
		$result = array();
		if(preg_match_all("/\/\*\*"."\*.+?\*\//s",$method_src,$doctests,PREG_OFFSET_CAPTURE)){
			foreach($doctests[0] as $doctest){
				if(isset($doctest[0][5]) && $doctest[0][5] != "*"){
					$test_start_line = $method_start_line + substr_count(substr($method_src,0,$doctest[1]),"\n") - 1;
					$test_block = str_repeat("\n",$test_start_line).str_replace(array("self::","new self("),array($class_name."::","new ".$class_name."("),preg_replace("/^[\s]*\*[\s]{0,1}/m","",str_replace(array("/"."***","*"."/"),"",$doctest[0])));
					$test_block_name = preg_match("/^[\s]*#(.+)/",$test_block,$match) ? trim($match[1]) : null;
					if(trim($test_block) == "") $test_block = null;
					$result[$method_name]['line'] = $method_start_line;
					$result[$method_name]['blocks'][] = array($test_block_name,$test_block,$test_start_line);
				}
			}
		}else if($is_public && $method_name[0] != '_'){
			$result[$method_name]['line'] = $method_start_line;
			$result[$method_name]['blocks'] = array();
		}
		return $result;
	}
	final static private function verify_format($path){
		if(php_sapi_name() == 'cli' && substr(PHP_OS,0,3) != 'WIN'){
			$f = ' Testing.. '.$path;
			$l = strlen($f);
			print($f);
			self::verify($path);
			print("\033[".$l.'D'.str_repeat(' ',$l)."\033[".$l.'D');
		}else{
			self::verify($path);		
		}
	}
	/**
	 * ディエクトリパスを指定してテストを実行する
	 * @param string $path
	 * @return Test
	 */
	final public static function verifies(){
		foreach(Lib::classes(true) as $path => $class){
			self::verify_format($path);
		}
		list($entry_path,$test_path) = self::search_path();
		foreach(array($entry_path,$test_path) as $p){
			if(is_dir($p)){
				foreach(File::ls($p) as $f){
					if($f->is_ext('php') && !$f->is_private()) self::verify_format($f->oname());
				}
			}
		}
		return new self();
	}	
	/**
	 * テストを実行する
	 * @param string $class_path パッケージパス
	 * @param string $method メソッド名
	 * @param string $block_name ブロック名
	 */
	final public static function verify($class_path,$method_name=null,$block_name=null){
		Exceptions::clear();
		list($entry_path,$tests_path) = self::search_path();
		try{
			$doctest = self::get_doctest(Lib::import($class_path));
		}catch(Exception $e){
			if(is_file($class_path)){
				$doctest = (strpos($class_path,'/tests/') === false) ? self::get_entry_doctest($class_path) : self::get_unittest($class_path);
			}else{
				if(is_file($f=$entry_path.'/'.$class_path.'.php')){
					$doctest = self::get_entry_doctest($f);
				}else if(is_file($f=($tests_path.str_replace('.','/',$class_path).'.php'))){
					$doctest = self::get_unittest($f);
				}else{
					throw new ErrorException($class_path.' test not found');
				}
			}
		}
		if(!isset(self::$flow_output_maps)){
			self::$flow_output_maps = array();
			foreach(File::ls($entry_path) as $app_file){
				$entry_name = basename($app_file,'.php');
				$parse_app = Flow::parse_app($app_file);
				foreach($parse_app['apps'] as $app){
					if($app['type'] == 'handle'){
						foreach($app['maps'] as $p => $c){
							$count = 0;
							if(!empty($p)) $p = substr(preg_replace_callback("/([^\\\\])(\(.*?[^\\\\]\))/",create_function('$m','return $m[1]."%s";'),' '.$p,-1,$count),1);
							if(!empty($c['name'])) self::$flow_output_maps[$entry_name][$c['name']][$count] = $p;
						}
					}
				}
			}
		}
		self::$current_file = $doctest['filename'];
		self::$current_class = $doctest['class_name'];
		self::$current_entry = $doctest['entry_name'];
		self::$current_map_test_file = null;
		self::$current_method = null;

		foreach($doctest['tests'] as $test_method_name => $tests){
			if($method_name === null || $method_name === $test_method_name){
				self::$current_method = $test_method_name;

				if(empty($tests['blocks'])){
					self::$result[self::$current_file][self::$current_class][self::$current_method][$tests['line']][] = array("none");									
				}else{
					foreach($tests['blocks'] as $test_block){
						list($name,$block) = $test_block;
						if($block_name === null || $block_name === $name){
							if(isset($doctest['entry_name'])){
								$pre_branch = App::branch();
								App::branch(new File($doctest['filename']));
								self::$current_map_test_file = $doctest['filename'];
							}
							try{
								ob_start();
								if(!isset($doctest['class_name']) && !isset($doctest['entry_name'])){
									if(is_file($f=(dirname($doctest['filename']).'/__setup__.php'))) include($f);
									include($doctest['filename']);
									if(is_file($f=(dirname($doctest['filename']).'/__teardown__.php'))) include($f);
								}else{
									if(isset($tests['__setup__'])) eval($tests['__setup__'][1]);
									eval($block);
									if(isset($tests['__teardown__'])) eval($tests['__teardown__'][1]);
								}
								Exceptions::clear();
								if(isset($doctest['entry_name'])){
									App::branch($pre_branch);
								}								
								$result = ob_get_clean();
								if(preg_match("/(Parse|Fatal) error:.+/",$result,$match)) throw new ErrorException($match[0]);
							}catch(Exception $e){
								if(ob_get_level() > 0) $result = ob_get_clean();
								list($message,$file,$line) = array($e->getMessage(),$e->getFile(),$e->getLine());
								$trace = $e->getTrace();
								foreach($trace as $k => $t){
									if(isset($t['class']) && isset($t['function']) && ($t['class'].'::'.$t['function']) == __METHOD__ && isset($trace[$k-2])
										&& $trace[$k-1]['file'] == __FILE__ && isset($trace[$k-1]['function']) && $trace[$k-1]['function'] == 'eval'
									){
										$file = isset(self::$current_map_test_file) ? self::$current_map_test_file : self::$current_file;
										$line = $trace[$k-2]['line'];
										break;
									}
								}
								self::$result[self::$current_file][self::$current_class][self::$current_method][$line][] = array("exception",$message,$file,$line);
								Log::warn('['.$line.':'.$file.'] '.$message);
							}
						}
					}
				}
			}
		}
		return new self();
	}
	final static private function expvar($var){
		if(is_numeric($var)) return strval($var);
		if(is_object($var)) $var = get_object_vars($var);
		if(is_array($var)){
			foreach($var as $key => $v){
				$var[$key] = self::expvar($v);
			}
		}
		return $var;
	}
	/**
	 * 判定を行う
	 * @param mixed $arg1 望んだ値
	 * @param mixed $arg2 実行結果
	 * @param boolean 真偽どちらで判定するか
	 * @param int $line 行番号
	 * @param string $file ファイル名
	 * @return boolean
	 */
	final public static function equals($arg1,$arg2,$eq,$line,$file=null){
		$result = ($eq) ? (self::expvar($arg1) === self::expvar($arg2)) : (self::expvar($arg1) !== self::expvar($arg2));
		self::$result[(empty(self::$current_file) ? $file : self::$current_file)][self::$current_class][self::$current_method][$line][] = ($result) ? array() : array(var_export($arg1,true),var_export($arg2,true));
		if(self::$each_flush) print(new Test());
		return $result;
	}
	static private function fcolor($msg,$color="30"){
		return (php_sapi_name() == 'cli' && substr(PHP_OS,0,3) != 'WIN') ? "\033[".$color."m".$msg."\033[0m" : $msg;
	}
	private function head(&$result,$class,$file){
		$result .= "\n";
		$result .= (empty($class) ? "*****" : $class)." [ ".$file." ]\n";
		$result .= str_repeat("-",80)."\n";
		return true;
	}
	protected function __str__(){
		$result = '';
		$tab = '  ';
		$success = $fail = $none = 0;
		$cli = (isset($_SERVER['argc']) && !empty($_SERVER['argc']) && substr(PHP_OS,0,3) != 'WIN');

		foreach(self::$result as $file => $f){
			foreach($f as $class => $c){
				$print_head = false;

				foreach($c as $method => $m){
					foreach($m as $line => $r){
						foreach($r as $l){
							switch(sizeof($l)){
								case 0:
									$success++;
									if(substr(self::$exec_type,-2,1) != "1") break;
									if(!$print_head) $print_head = $this->head($result,$class,$file);
									$result .= "[".$line."]".$method.": ".self::fcolor("success","32")."\n";
									break;
								case 1:
									$none++;
									if(substr(self::$exec_type,-3,1) != "1") break;
									if(!$print_head) $print_head = $this->head($result,$class,$file);
									$result .= "[".$line."]".$method.": ".self::fcolor("none","1;35")."\n";
									break;
								case 2:
									$fail++;
									if(substr(self::$exec_type,-4,1) != "1") break;
									if(!$print_head) $print_head = $this->head($result,$class,$file);
									$result .= "[".$line."]".$method.": ".self::fcolor("fail","1;31")."\n";
									$result .= $tab.str_repeat("=",70)."\n";
									ob_start();
										var_dump($l[0]);
										$result .= self::fcolor($tab.str_replace("\n","\n".$tab,ob_get_contents()),"33");
									ob_end_clean();
									$result .= "\n".$tab.str_repeat("=",70)."\n";

									ob_start();
										var_dump($l[1]);
										$result .= self::fcolor($tab.str_replace("\n","\n".$tab,ob_get_contents()),"31");
									ob_end_clean();
									$result .= "\n".$tab.str_repeat("=",70)."\n";
									break;
								case 4:
									$fail++;
									if(substr(self::$exec_type,-4,1) != "1") break;
									if(!$print_head) $print_head = $this->head($result,$class,$file);
									$result .= "[".$line."]".$method.": ".self::fcolor("exception","1;31")."\n";
									$result .= $tab.str_repeat("=",70)."\n";
									$result .= self::fcolor($tab.$l[1]."\n\n".$tab.$l[2].":".$l[3],"31");
									$result .= "\n".$tab.str_repeat("=",70)."\n";
									break;
							}
						}
					}
				}
			}
		}
		Test::clear();
		$result .= "\n";
		if(substr(self::$exec_type,-5,1) == "1") $result .= self::fcolor(" success: ".$success." ","7;32")." ".self::fcolor(" fail: ".$fail." ","7;31")." ".self::fcolor(" none: ".$none." ","7;35")."\n";
		return $result;
	}
	/**
	 * テンポラリファイルを作成する
	 * デストラクタで削除される
	 * @param string $path ファイルパス
	 * @param string $body 内容
	 */
	static public function ftmp($path,$body){
		File::write(self::tmp_path($path),Text::plain($body));
	}
	/**
	 * テンポラリファイルを保存するパスを返す
	 * @param string $path テンポラリからの相対ファイルパス
	 * @return string
	 */
	static public function tmp_path($path=null){
		return File::absolute(App::work("test_tmp"),$path);
	}
	static public function __import__(){
		self::exec_type(self::FAIL|self::COUNT);
		if(is_dir(self::tmp_path())){
			Object::C(Flow)->add_module(new self());
			self::$in_test = true;
		}
	}
	static public function __shutdown__(){
		if(!self::$in_test) File::rm(self::tmp_path());
	}
	/**
	 * @see Flow
	 */
	public function flow_handle_check_result($vars,$url){
		if(self::$in_test) File::write(self::tmp_path(sha1(md5($url))),serialize($vars));
	}
	/**
	 * 指定のURL実行時のFlowの結果から値を取得する
	 * @param string $url 取得したいURL
	 * @param string $name コンテキスト名
	 * @return mixed
	 */
	static public function handled_var($url,$name){
		$path = self::tmp_path(sha1(md5($url)));
		$vars = (is_file($path)) ? unserialize(File::read($path)) : array();
		return array_key_exists($name,$vars) ? $vars[$name] : null;
	}
	final static private function space_line_count($value){
		return sizeof(explode("\n",$value)) - 1;
	}
	static public function current_entry(){
		return self::$current_entry;
	}
	static public function flow_output_maps($entry_name=null){
		return (isset($entry_name)) ? self::$flow_output_maps[$entry_name] : self::$flow_output_maps;
	}
}