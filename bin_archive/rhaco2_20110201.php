<?php
/**
 * @version 20110201
 */
if(!class_exists('Object')){
/**
 * アプリケーション定義
 * @author tokushima
 */
class App{
	static private $def = array();
	static private $shutdown = array();
	static private $path;
	static private $work;
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
	 */
	static public function config_path($path,$url=null,$work=null,$mode=null,$vendors_path=null,$libs_path=null){
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
			$url = str_replace('://*','://'.(isset($_SERVER['HTTP_X_FORWARDED_HOST']) ? $_SERVER['HTTP_X_FORWARDED_HOST'] : (
						isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : ''))),$url);			
			if(substr($url,-1) !== '/') $url = $url.'/';
			self::$url = $url;
			self::$surl = str_replace('http://','https://',$url);
		}
		self::$mode = (empty($mode)) ? 'noname' : $mode;
		if(isset($vendors_path) || isset($libs_path)) Lib::config_path($libs_path,$vendors_path);
		if(isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) && !empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])){
			list($lang)	= explode(',',$_SERVER['HTTP_ACCEPT_LANGUAGE']);
			list($lang)	= explode('-',$lang);
			Gettext::lang($lang);
			Gettext::set(self::$path.'resources/locale/messages/');
		}
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
			self::$url = preg_replace('/^(.+:\/\/.[^\/]+).*$/','\\1',dirname(Request::current_url()))
							.dirname(preg_replace('/^(.+\.php).*$/','\\1',$_SERVER['PHP_SELF'])).'/';
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
/**
 * 例外
 * @author Kazutaka Tokushima
 * @license New BSD License
 */
class Exceptions extends Exception{
	static private $self;
	protected $message = 'exceptions';
	private $messages = array();
	/**
	 * Exceptionを追加する
	 * @param Exception $exception 例外
	 * @param string $group グループ名
	 */
	static public function add(Exception $exception,$group=null){
		if(self::$self === null) self::$self = new self();
		if($exception instanceof self){
			foreach($exception->messages as $key => $es){
				foreach($es as $e) self::$self->messages[$key][] = $e;
			}
		}else{
			if(empty($group)) $group = 'exceptions';
			self::$self->messages[$group][] = $exception;
		}
	}
	/**
	 * 追加されたExceptionのクリア
	 */
	static public function clear(){
		self::$self = null;
	}
	/**
	 * 追加されたExceptionからメッセージ配列を取得
	 * @param string $group グループ名
	 * @return string[]
	 */
	static public function messages($group=null){
		$result = array();
		foreach(self::gets($group) as $m) $result[] = $m->getMessage();
		return $result;
	}
	/**
	 * 追加されたExceptionからException配列を取得
	 * @param string $group グループ名
	 * @return Exception[]
	 */
	static public function gets($group=null){
		if(!self::has($group)) return array();
		if(!empty($group)) return self::$self->messages[$group];
		$result = array();
		foreach(self::$self->messages as $k => $exceptions) $result = array_merge($result,$exceptions);
		return $result;
	}
	/**
	 * 追加されたグループ名一覧
	 * @return string[]
	 */
	static public function groups(){
		if(!self::has()) return array();
		return array_keys(self::$self->messages);
	}
	/**
	 * Exceptionが追加されているか
	 * @param string $group グループ名
	 * @return boolean
	 */
	static public function has($group=null){
		return (isset(self::$self) && ((empty($group) && !empty(self::$self->messages)) || (!empty($group) && isset(self::$self->messages[$group]))));
	}
	static public function invalid($group=null){
		Log::warn('method `Exceptions::invalid()` is deprecated. use `Exceptions::has()` instead.');
		return self::has($group);
	}
	/**
	 * Exceptionが追加されていればthrowする
	 * @param string $group グループ名
	 */
	static public function throw_over($group=null){
		if(self::has($group)) throw self::$self;
	}
	static public function validation($group=null){
		Log::warn('method `Exceptions::validation()` is deprecated. use `Exceptions::throw_over()` instead.');
		self::throw_over($group);
	}
	public function __toString(){
		if(self::$self === null || empty(self::$self->messages)) return null;
		$exceptions = self::gets();
		$result = count($exceptions)." exceptions: ";
		foreach($exceptions as $e){
			$result .= "\n ".$e->getMessage();
		}
		return $result;
	}
}
/**
 * Fileイテレータ
 * @author Kazutaka Tokushima
 * @license New BSD License
 */
class FileIterator implements Iterator{
	private $pointer;
	private $hierarchy = 0;
	private $resource = array();
	private $path = array();
	private $next = false;
	private $type;
	private $recursive;
	private $a;
	public function __construct($directory,$type,$recursive,$a){
		$this->resource[0] = opendir($directory);
		$this->path[0] = $directory;
		$this->type = $type;
		$this->recursive = $recursive;
		$this->a = $a;
	}
	/**
	 * @see Iterator
	 */
	public function rewind(){
	}
	/**
	 * @see Iterator
	 */
	public function next(){
	}
	/**
	 * @see Iterator
	 */
	public function key(){
		return $this->path[$this->hierarchy];
	}
	/**
	 * @see Iterator
	 */
	public function current(){
		return ($this->type === 0) ? $this->pointer : new File($this->pointer);
	}
	/**
	 * @see Iterator
	 */
	public function valid(){
		if($this->next !== false){
			$this->hierarchy++;
			$this->resource[$this->hierarchy] = $this->next;
			$this->path[$this->hierarchy] = $this->pointer;
			$this->next = false;
			return $this->valid();
		}
		$pointer = readdir($this->resource[$this->hierarchy]);
		if($pointer === "." || $pointer === ".." || (!$this->a && $pointer[0] === ".")) return $this->valid();
		if($pointer === false){
			closedir($this->resource[$this->hierarchy]);
			if($this->hierarchy === 0) return false;
			$this->hierarchy--;
			return $this->valid();
		}
		$this->pointer = $this->path[$this->hierarchy]."/".$pointer;
		if($this->recursive && is_dir($this->pointer)) $this->next = opendir($this->pointer);
		if(($this->type === 0 && !is_dir($this->pointer)) || ($this->type === 1 && !is_file($this->pointer))) return $this->valid();
		return true;
	}
}
/**
 * gettext
 * @author tokushima
 */
class Gettext{
	static private $lang;
	static private $messages = array();
	static private $messages_path = array();
	static private $message_head = array();
	private $search_messages = array();
	/**
	 * 対象文字列を検索する
	 * @param string $path 検索対象のパス
	 * @param string $base 基点となるパス、コメントで使用する
	 * @return $this
	 */
	public function search($path,$base=null){
		$path = str_replace("\\",'/',$path);
		if(is_dir($path) && ($handle = opendir($path))){
			if(empty($base)) $base = $path;
			if(substr($base,-1) != '/') $base .= '/';
			while($pointer = readdir($handle)){
				if($pointer != '.' && $pointer != '..' && $pointer[0] != '.'){
					$filename = sprintf("%s/%s",$path,$pointer);
					if(is_file($filename)){
						if(sprintf('%u',@filesize($filename)) < (1024 * 1024)){
							$src = str_replace(array("\\\\","\\\"","\\'"),array('__ESC_DESC__','__ESC_DQ__','__ESC_SQ__'),File::read($filename));
							foreach(explode("\n",$src) as $line => $value){
								if(preg_match_all("/trans\(([\"\'])(.+?)\\1([^\)\s]*)/",$value,$match)){
									foreach($match[2] as $k => $msg){
										$msg = str_replace(array('__ESC_DESC__','__ESC_DQ__','__ESC_SQ__'),array("\\","\"","'"),$msg);
										$this->add($msg,str_replace($base,"",$filename),($line + 1),!empty($match[3][$k]));
									}
								}
							}
						}
					}else if(is_dir($filename)){
						$this->search($filename,$base);
					}
				}
			}
			closedir($handle);
		}
		return $this;
	}
	/**
	 * メッセージを追加する
	 * @param string $msg メッセージ
	 * @param string $filename メッセージを含むファイルパス
	 * @param integer $line メッセージを含む行番号
	 * @param boolean $plural 複数形か
	 */
	public function add($msg,$filename,$line=0,$plural=false){
		$this->search_messages[$msg]['#: '.$filename.(($line > 0) ? (':'.$line) : '')] = $plural;
		return $this;
	}
	/**
	 * 検索されたメッセージ配列を返す
	 * @return array
	 */
	public function messages(){
		return $this->search_messages;
	}
	/**
	 * LANGを設定、取得する
	 * @param string $lang 言語コード
	 * @return string
	 */
	static public function lang($lang=null){
		if(!empty($lang)){
			self::$lang = $lang;
			self::$messages = array();
			self::$message_head = array();
			foreach(self::$messages_path as $dir_name => $null) self::set($dir_name);
		}
		return self::$lang;
	}
	/**
	 * 国際化メッセージを設定する
	 * @param string $dir_name メッセージファイルのあるフォルダ
	 */
	static public function set($dir_name){
		if(is_dir($dir_name)){
			self::$messages_path[$dir_name] = true;
			$dir_name = str_replace("\\","/",$dir_name);
			if(substr($dir_name,-1) != '/') $dir_name .= '/';
			
			$mo_filename = $dir_name.'messages-'.self::$lang.'.mo';
			if(!is_file($mo_filename)) return;
			$bin = file_get_contents($mo_filename);
			$values = array();
			$head_no = sizeof(self::$message_head) + 1;
			self::$message_head[$head_no] = null;
	
			list(,$magick) = unpack('L',substr($bin,0,4));
			list(,$count) = unpack('l',substr($bin,8,4));
			list(,$id_length) = unpack('l',substr($bin,16,4));
	
			for($i=0,$y=28,$z=$id_length;$i<$count;$i++,$y+=8,$z+=8){
				list(,$key_len) = unpack('l',substr($bin,$y,4));
				list(,$key_offset) = unpack('l',substr($bin,$y+4,4));
	
				list(,$value_len) = unpack('l',substr($bin,$z,4));
				list(,$value_offset) = unpack('l',substr($bin,$z+4,4));
	
				$key = substr($bin,$key_offset,$key_len);
				if($key === ''){
					$header = explode("\n",substr($bin,$value_offset,$value_len));
					foreach($header as $head){
						list($name,$value) = explode(':',$head,2);
						if(strtolower(trim($name)) === 'plural-forms'){
							self::$message_head[$head_no] = str_replace("n","\$n",preg_replace("/^.*plural[\s]*=(.*)[;]*$/","\\1",$value));
							break;
						}
					}
				}else{
					$values[$key][0] = $head_no;
					$values[$key][1] = explode("\0",substr($bin,$value_offset,$value_len));
				}
			}
			foreach($values as $key => $value){
				if(!isset(self::$messages[$key])) self::$messages[$key] = $value;
			}
		}
	}
	/**
	 * 対象のパスを検索し、poファイルを書き出す
	 * @param string $path 検索対象のパス
	 * @param string $output_path poファイルのパス
	 */
	static public function po($path,$output_path){
		$self = new self();
		$self->search($path);
		for($i=2;$i<func_num_args();$i++){
			$arg = func_get_arg($i);
			if($arg instanceof self) $arg = $arg->messages();
		}
		$self->write($output_path);
	}
	/**
	 * poからmoを生成する
	 * @param stirng $po_filename
	 * @param string $mo_filename
	 */
	static public function mo($po_filename,$mo_filename=null){
		if(!is_file($po_filename)) throw new InvalidArgumentException($po_filename.": no such file");		
		$output_path = empty($mo_filename) ? preg_replace("/^(.+\.)po$/","\\1mo",$po_filename) : $mo_filename;
		$read_po_list = self::read($po_filename);
		$po_list = array();
		foreach($read_po_list as $id => $values){
			$c = array_flip(array_values($values));
			if(!(sizeof($c) <= 1 && key($c) === "")){
				$po_list[$id] = $values;
			}
		}
		$count = sizeof($po_list);
		$ids = implode("\0",array_keys($po_list))."\0";
		$keyoffset = 28 + 16 * $count;
		$valueoffset = $keyoffset + strlen($ids);
		$value_src = "";
		$output_src = pack('Lllllll',0x950412de,0,$count,28,(28 + ($count * 8)),0,0);
		$output_values = array();
		foreach($po_list as $id => $values){
			$len = strlen($id);
			$output_src .= pack("l",$len);
			$output_src .= pack("l",$keyoffset);
			$keyoffset += $len + 1;
			$value = implode("\0",$values);
			$len = strlen($value);
			$value_src .= pack("l",$len);
			$value_src .= pack("l",$valueoffset);
			$valueoffset += $len + 1;
			$output_values[] = $value;
		}
		$output_src .= $value_src;
		$output_src .= $ids;
		$output_src .= implode("\0",$output_values)."\0";
		if(!is_dir(dirname($output_path))) mkdir(dirname($output_path),0744,true);
		file_put_contents($output_path,$output_src,LOCK_EX);
		return $output_path;
	}
	/**
	 * poファイルとして書き出す
	 * @param string $output_path
	 * @return string 書き出したファイルパス
	 */
	public function write($output_path){
		$read_messages = is_file($output_path) ? self::read($output_path) : array();		
		ksort($this->search_messages,SORT_STRING);
		$output_src = sprintf(implode("\n",array(
						'# SOME DESCRIPTIVE TITLE.'
						,'msgid ""'
						,'msgstr ""'
						,'"Project-Id-Version: PACKAGE VERSION\n"'
						,'"Report-Msgid-Bugs-To: \n"'
						,'"POT-Creation-Date: %s\n"'
						,'"PO-Revision-Date: YEAR-MO-DA HO:MI+ZONE\n"'
						,'"Last-Translator: FULL NAME <EMAIL@ADDRESS>\n"'
						,'"Language-Team: LANGUAGE <team@exsample.com>\n"'
						,'"Plural-Forms: nplurals=1; plural=0;\n"'))
				,date("Y-m-d H:iO"))."\n\n";
		foreach($this->search_messages as $str => $lines){
			$output_src .= "\n".implode("\n",array_keys($lines))."\n";
			$output_src .= "msgid \"".str_replace(array("\\","\""),array("\\\\","\\\""),$str)."\"\n";
			$msg = isset($read_messages[$str]) ? $read_messages[$str] : array(null);
			
			if(sizeof($msg) > 1){
				foreach($msg as $k => $m) $output_src .= "msgstr[".$k."] \"".str_replace(array("\\","\""),array("\\\\","\\\""),$m)."\"\n";
			}else{
				foreach($msg as $m) $output_src .= "msgstr \"".str_replace(array("\\","\""),array("\\\\","\\\""),$m)."\"\n";
			}
		}
		if(!is_dir(dirname($output_path))) mkdir(dirname($output_path),0744,true);
		file_put_contents($output_path,$output_src,LOCK_EX);
		return $output_path;
	}
	/**
	 * poからメッセージ配列を取得
	 * @param $po_filename
	 * @return array
	 */
	static public function read($po_filename){
		$po_list = array();
		$msgId = "";
		$isId = false;
		$plural_no = 0;
		$src = str_replace(array("\\\\","\\\"","\\'"),array('__ESC_DESC__','__ESC_DQ__','__ESC_SQ__'),File::read($po_filename));
		foreach(explode("\n",$src) as $line){
			if(!preg_match("/^[\s]*#/",$line)){
				if(preg_match("/msgid_plural[\s]+([\"\'])(.+)\\1/",$line,$match)){
					$msgId = self::unescape($match[2]);
					$isId = true;
					$plural_no = 0;
				}else if(preg_match("/msgid[\s]+([\"\'])(.*?)\\1/",$line,$match)){
					$msgId = self::unescape($match[2]);
					$isId = true;
					$plural_no = 0;
				}else if(preg_match("/msgstr\[(\d+)\][\s]+([\"\'])(.*?)\\2/",$line,$match)){
					$plural_no = (int)$match[1];
					$po_list[$msgId][$plural_no] = self::unescape($match[3]);
					$isId = false;
					ksort($po_list[$msgId]);
				}else if(preg_match("/msgstr[\s]+([\"\'])(.*?)\\1/",$line,$match)){
					$po_list[$msgId][$plural_no] = self::unescape($match[2]);
					$isId = false;
				}else if(preg_match("/([\"\'])(.+)\\1/",$line,$match)){
					if($isId){
						$msgId .= self::unescape($match[2]);
					}else{
						if(!isset($po_list[$msgId][$plural_no])) $po_list[$msgId][$plural_no] = '';
						$po_list[$msgId][$plural_no] .= self::unescape($match[2]);
					}
				}
			}
		}
		ksort($po_list,SORT_STRING);
		return $po_list;
	}
	static private function unescape($src){
		return str_replace(array('__ESC_DQ__','__ESC_SQ__','__ESC_DESC__',"\\n"),array("\"","'","\\","\n"),$src);
	}
	/**
	 * 国際化文字列を返す
	 * @param string $key 国際化する文字列
	 * @return string
	 */
	static public function trans($key){
		$args = func_get_args();
		$argsize = func_num_args();
		$key = array_shift($args);
		$message = $key;
		if(isset(self::$messages[$key])){
			$message = self::$messages[$key][1][0];
			if(!empty($args) && sizeof(self::$messages[$key][1]) > 1){
				$plural_param = (int)array_shift($args);
				if(isset(self::$message_head[self::$messages[$key][0]])){
					$n = $plural_param;
					$message = self::$messages[$key][1][(int)self::$message_head[self::$messages[$key][0]]];
				}
			}
		}
		if(strpos($message,'{') !== false && preg_match_all("/\{([\d]+)\}/",$message,$match)){
			$args = array_map(array(__CLASS__,'trans'),$args);
			foreach($match[1] as $k => $v){
				$i = ((int)$v) - 1;
				$message = str_replace($match[0][$k],isset($args[$i]) ? $args[$i] : '',$message);
			}
		}
		return $message;
		
	}	
}
/**
 * ライブラリ制御
 * @author Kazutaka Tokushima
 * @license New BSD License
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
/**
 * 基底クラス
 * @author Kazutaka Tokushima
 * @license New BSD License
 */
class Object{
	static private $_sm = array(array(),array(),array()); // anon,class anon,module
	private $_m = array(array(),array(),array(),array(),true); 	// objects,modules,props,params,static
	protected $_ = array(null,null); // last access prop (object,prop)
	/**
	 * モジュールがあるか
	 * @param string $method
	 * @return boolean
	 */
	final public function has_module($method){
		foreach((($this->_m[4]) ? (isset(self::$_sm[2][get_class($this)]) ? self::$_sm[2][get_class($this)] : array()) : $this->_m[1]) as $obj){
			if(method_exists($obj,$method)) return true;
		}
		return false;
	}
	/**
	 * モジュールの実行
	 * @param string $method
	 * @param mixed $p 0..9
	 * @return mixed
	 */
	final public function call_module($method,&$p0=null,&$p1=null,&$p2=null,&$p3=null,&$p4=null,&$p5=null,&$p6=null,&$p7=null,&$p8=null,&$p9=null){
		if($this->has_module($method)){
			$result = null;
			foreach((($this->_m[4]) ? self::$_sm[2][get_class($this)] : $this->_m[1]) as $obj){
				if(method_exists($obj,$method)) $result = call_user_func_array(array($obj,$method),array(&$p0,&$p1,&$p2,&$p3,&$p4,&$p5,&$p6,&$p7,&$p8,&$p9));
			}
			return $result;
		}
		return $p0;
	}
	/**
	 * モジュールを追加する
	 * @param object $obj モジュールに追加するインスタンス
	 */
	final public function add_module($obj){
		if(!is_object($obj)) throw new InvalidArgumentException('invalid argument');
		if($this->_m[4]){
			self::$_sm[2][get_class($this)][] = $obj;
		}else{
			if(get_class($this) === get_class($obj)) return;
			$this->_m[1][] = $obj;
			foreach($this->_m[0] as $mixin_obj){
				if($mixin_obj instanceof self) $mixin_obj->add_module($obj);
			}
		}
		return $this;
	}
	/**
	 * モジュールをコピーする
	 * @param object $obj モジュールを有するオブジェクト
	 */
	final public function copy_module($obj){
		foreach($obj->_m[1] as $m) $this->add_module($m);	
		return $this;	
	}
	/**
	 * ハッシュとしての値を返す
	 * @return array
	 */
	final public function hash(){
		$args = func_get_args();
		if(method_exists($this,'__hash__')) return call_user_func_array(array($this,'__hash__'),$args);
		$result = array();
		foreach($this->props() as $name){
			if($this->a($name,'get') !== false && $this->a($name,'hash') !== false){
				switch($this->a($name,'type')){
					case 'boolean': $result[$name] = $this->{$name}(); break;
					default: $result[$name] = $this->{'fm_'.$name}();
				}
			}
		}
		return $result;
		
		
	}
	/**
	 * 値をコピーする
	 * @param Object $arg コピーする値
	 * @return $this
	 */
	final public function cp($arg){
		$args = func_get_args();
		if(method_exists($this,'__cp__')){
			call_user_func_array(array($this,'__cp__'),$args);
		}else if(isset($args[0])){
			$vars = $this->prop_values();
			if($args[0] instanceof self){
				foreach($args[0]->prop_values() as $name => $value){
					if(array_key_exists($name,$vars) && $args[0]->a($name,'cp') !== false) $this->{$name}($value);
				}
			}else if(is_array($args[0])){
				foreach($args[0] as $name => $value){
					if(array_key_exists($name,$vars)) $this->{$name}($value);
				}
			}else{
				throw new InvalidArgumentException('cp');
			}
		}
		return $this;
		
		
	}
	/**
	 * objectをmixinさせる
	 * @param object $object mixinさせるインスタンス
	 * @return $this
	 */
	final public function add_object($object){
		if(!is_object($object) || !($object instanceof self) || get_class($object) === get_class($this)) throw new InvalidArgumentException('invalid argument');
		$this->_m[0] = array_reverse(array_merge(array_reverse($this->_m[0],true),array(get_class($object)=>$object)),true);
		return $this;
		
		
		
		
	}
	final public function __set($n,$v){
		if(in_array($n,$this->_m[2])){
			$this->_ = array($this,$n);
			call_user_func_array(array($this,'___set___'),array($v));
			$this->_ = array(null,null);
		}else if($n[0] == '_'){
			$this->{$n} = $v;
		}else{
			$this->{$n} = $v;
			$this->_m[2][] = $n;
		}
	}
	final public function __get($n){
		if(!in_array($n,$this->_m[2])) throw new InvalidArgumentException('Processing not permitted [get]');
		$this->_ = array($this,$n);
		$res = $this->___get___();
		$this->_ = array(null,null);
		return $res;
	}
	final public function __call($n,$args){
		foreach($this->_m[0] as $o){
			try{ return call_user_func_array(array($o,$n),$args);
			}catch(ErrorException $e){}
		}
		list($call,$prop) = (in_array($n,$this->_m[2])) ? array((empty($args) ? 'get' : 'set'),$n) : (preg_match("/^([a-z]+)_([a-zA-Z].*)$/",$n,$n) ? array($n[1],$n[2]) : array(null,null));
		if(empty($call)) throw new ErrorException(get_class($this).'::'.$n.' method not found');
		foreach(array_merge(array($this),$this->_m[0]) as $o){
			if(method_exists($o,'___'.$call.'___')){
				$o->_ = array($this,$prop);
				$result = call_user_func_array(array($o,(method_exists($o,'__'.$call.'_'.$prop.'__') ? '__'.$call.'_'.$prop.'__' : '___'.$call.'___')),$args);
				$o->_ = array(null,null);
				return $result;
			}
		}		
		
		
	}
	final public function __construct(){
		$c = get_class($this);
		foreach(array_keys(get_object_vars($this)) as $name){
			if($name[0] != '_'){
				$ref = new ReflectionProperty($c,$name);
				if(!$ref->isPrivate()) $this->_m[2][] = $name;
			}
		}
		$a = (func_num_args() === 1) ? func_get_arg(0) : null;
		if(!is_string($a) || strpos($a,'_static_=true') === false){
			$this->_m[4] = false;
			$init = true;
			if(!isset(self::$_sm[0][$c])){
				self::$_sm[0][$c] = array();
				$d = null;
				$r = new ReflectionClass($this);
				while($r->getName() != __CLASS__){
					$d = $r->getDocComment().$d;
					$r = $r->getParentClass();
				}
				$d = preg_replace("/^[\s]*\*[\s]{0,1}/m",'',str_replace(array('/'.'**','*'.'/'),'',$d));
				if(preg_match_all("/@var\s([\w_]+[\[\]\{\}]*)\s\\\$([\w_]+)(.*)/",$d,$m)){
					foreach($m[2] as $k => $n){
						$p = (false !== ($s = strpos($m[3][$k],'@{'))) ? json_decode(substr($m[3][$k],$s+1,strrpos($m[3][$k],'}')-$s),true) : array();
						if(!is_array($p)) throw new LogicException('JSON error `'.$n.'`');
						self::$_sm[0][$c][$n] = (isset(self::$_sm[0][$c][$n])) ? array_merge(self::$_sm[0][$c][$n],$p) : $p;
						if(false != ($h = strpos($m[1][$k],'{}')) || false !== ($l = strpos($m[1][$k],'[]'))){
							self::$_sm[0][$c][$n]['type'] = substr($m[1][$k],0,-2);
							self::$_sm[0][$c][$n]['attr'] = (isset($h) && $h !== false) ? 'h' : 'a';
						}else{
							self::$_sm[0][$c][$n]['type'] = $m[1][$k];
						}
						foreach(array_keys(self::$_sm[0][$c]) as $n){
							if(self::$_sm[0][$c][$n]['type'] == 'serial'){
								self::$_sm[0][$c][$n]['primary'] = true;
							}else if(self::$_sm[0][$c][$n]['type'] == 'choice' && method_exists($this,'__choices_'.$n.'__')){
								self::$_sm[0][$c][$n]['choices'] = $this->{'__choices_'.$n.'__'}();
							}
						}
					}
				}
				if(preg_match_all("/@class\s.*@(\{.*\})/",$d,$m)){
					foreach($m[1] as $j){
						$p = json_decode($j,true);
						if(!is_array($p)) throw new LogicException('JSON error @class');
						self::$_sm[1][$c] = array_merge((isset(self::$_sm[1][$c]) ? self::$_sm[1][$c] : array()),$p);
					}
				}
				if(method_exists($this,'__anon__')) $this->__anon__($d);
			}
			if(method_exists($this,'__new__')){
				$args = func_get_args();
				call_user_func_array(array($this,'__new__'),$args);
			}else if(!empty($a) && is_string($a) && preg_match_all("/.+?[^\\\],|.+?$/",$a,$m)){
				$init = (strpos($a,'_init_=false') === false);
				foreach($m[0] as $g){
					if(strpos($g,'=') !== false){
						list($n,$v) = explode('=',$g,2);
						if($n[0] != '_'){
							if(!in_array($n,$this->_m[2])) throw new ErrorException(get_class($this).'::'.$n.' property not found');
							if(substr($v,-1) == ',') $v = substr($v,0,-1);
							$this->{$n}(($v === '') ? null : str_replace("\\,",',',preg_replace("/^([\"\'])(.*)\\1$/","\\2",$v)));
						}
					}
				}
			}
			if($init && method_exists($this,'__init__')) $this->__init__();
		}
		
	}
	final public function __destruct(){
		if(method_exists($this,'__del__')) $this->__del__();
	}
	final public function __toString(){
		return (string)$this->__str__();
	}
	final public function __clone(){
		if(method_exists($this,'__clone__')){
			$this->__clone__();
		}else{
			$this->_m[2] = unserialize(serialize($this->_m[2]));
			$this->_m[0] = unserialize(serialize($this->_m[0]));
			$this->_m[1] = unserialize(serialize($this->_m[1]));
			$this->_m[3] = array();
		}
	}
	/**
	 * プロパティ名を返す
	 * @return string{}
	 */
	final public function props(){
		$r = $this->_m[2];
		foreach($this->_m[0] as $o) $r = array_merge($r,$o->props());
		return array_keys(array_flip($r));
		
	}
	/**
	 * get可能なオブジェクトのプロパティを返す
	 * @return mixed{} (name => value)
	 */
	final public function prop_values(){
		$result = array();
		foreach($this->props() as $name){
			if($this->a($name,'get') !== false) $result[$name] = $this->{$name}();
		}
		return $result;
		
	}
	/**
	 * 文字列表現を返す
	 * @return string
	 */
	final public function str(){
		return (string)$this->__str__();
	}
	final protected function prop_anon($name){
		if(isset($this->_m[3][$name])) return $this->_m[3][$name];
		$c = get_class($this);
		if(isset(self::$_sm[0][$c][$name])){
			return self::$_sm[0][$c][$name];
		}else{
			foreach($this->_m[0] as $o){
				if(null !== ($a = $o->prop_anon($name))) return $a;
			}
		}
		return array();
	}
	/**
	 * クラスのアノテーションを取得
	 * @param string $a アノテーション名
	 * @param mixed $d デフォルト値
	 * @return mixed
	 */
	final public function anon($a,$d=null){
		return isset(self::$_sm[1][$this->get_called_class()][$a]) ? self::$_sm[1][$this->get_called_class()][$a] : $d;
	}
	/**
	 * アノテーションの値を取得/設定
	 * @param string $v 変数名
	 * @param string $a アノテーション名
	 * @param mixed $d 設定する値
	 * @@aram boolean $f
	 * @return mixed
	 */
	final public function a($v,$a,$d=null,$f=false){
		$p = $this->prop_anon($v);
		if($f) $this->_m[3][$v][$a] = $d;
		return isset($p[$a]) ? $p[$a] : $d;
		
		
	}
	/**
	 * 追加されたモジュールを参照する
	 * @param string $name
	 * @return object
	 */
	final public function o($name){
		return $this->_m[0][$name];
	}
	/**
	 * クラスアクセスとして返す
	 * @param string $class_name クラス名
	 * @return object
	 */
	final static public function c($class_name){
		if(!is_subclass_of($class_name,__CLASS__)) throw new BadMethodCallException('Processing not permitted [static]');
		$obj = new $class_name('_static_=true');
		if(!$obj->_m[4]) throw new BadMethodCallException('Processing not permitted [static]');
		return $obj;
	}
	/**
	 * クラスアクセスの場合にクラス名を返す
	 * @return string
	 */
	final public function get_called_class(){
		if(!$this->_m[4]) throw new BadMethodCallException('Processing not permitted [static]');
		return get_class($this);
	}
	protected function __str__(){
		return get_class($this);
	}
	final static private function set_assert($t,$v,$param){
		if($v === null) return null;
		switch($t){
			case null: return $v;
			case 'string': return str_replace(array("\r\n","\r","\n"),'',$v);
			case 'text': return is_bool($v) ? (($v) ? 'true' : 'false') : ((string)$v);
			default:
				if($v === '') return null;
				switch($t){
					case 'number':
						if(!is_numeric($v)) throw new InvalidArgumentException('must be an of '.$t);
						return (float)(isset($param['decimal_places']) ? (floor($v * pow(10,$param['decimal_places'])) / pow(10,$param['decimal_places'])) : $v);
					case 'serial':
					case 'integer':
						if(!is_numeric($v) || (int)$v != $v) throw new InvalidArgumentException('must be an of '.$t);
						return (int)$v;
					case 'boolean':
						if(is_string($v)){ $v = ($v === 'true' || $v === '1') ? true : (($v === 'false' || $v === '0') ? false : $v);
						}else if(is_int($v)){ $v = ($v === 1) ? true : (($v === 0) ? false : $v); }
						if(!is_bool($v)) throw new InvalidArgumentException('must be an of '.$t);
						return (boolean)$v;
					case 'timestamp':
					case 'date':
						if(ctype_digit((string)$v)) return (int)$v;
						if(((int)preg_replace("/[^\d]/",'',$v)) === 0) throw new InvalidArgumentException('must be an of '.$t);
						$time = strtotime($v);
						if($time === false) throw new InvalidArgumentException('must be an of '.$v);
						return $time;
					case 'time':
						if(is_numeric($v)) return $v;
						$d = array_reverse(preg_split("/[^\d\.]+/",$v));
						if($d[0] === '') array_shift($d);
						list($s,$m,$h) = array((isset($d[0]) ? (float)$d[0] : 0),(isset($d[1]) ? (float)$d[1] : 0),(isset($d[2]) ? (float)$d[2] : 0));
						if(sizeof($d) > 3 || $m > 59 || $s > 59 || strpos($h,'.') !== false || strpos($m,'.') !== false) throw new InvalidArgumentException('must be an of '.$t);
						return ($h * 3600) + ($m*60) + ((int)$s) + ($s-((int)$s));
					case 'intdate':
						if(preg_match("/^\d\d\d\d\d+$/",$v)){
							$v = sprintf('%08d',$v);
							list($y,$m,$d) = array((int)substr($v,0,-4),(int)substr($v,-4,2),(int)substr($v,-2,2));
						}else{
							$x = preg_split("/[^\d]+/",$v);
							if(sizeof($x) < 3) throw new InvalidArgumentException('must be an of '.$t);
							list($y,$m,$d) = array((int)$x[0],(int)$x[1],(int)$x[2]);
						}
						if($m < 1 || $m > 12 || $d < 1 || $d > 31 || (in_array($m,array(4,6,9,11)) && $d > 30) || (in_array($m,array(1,3,5,7,8,10,12)) && $d > 31)
							|| ($m == 2 && ($d > 29 || (!(($y % 4 == 0) && (($y % 100 != 0) || ($y % 400 == 0)) ) && $d > 28)))
						) throw new InvalidArgumentException('must be an of '.$t);
						return (int)sprintf('%d%02d%02d',$y,$m,$d);
					case 'email':
						if(!preg_match('/^[\w\''.preg_quote('./!#$%&*+-=?^_`{|}~','/').']+@(?:[A-Z0-9-]+\.)+[A-Z]{2,6}$/i',$v) 
							|| strlen($v) > 255 || strpos($v,'..') !== false || strpos($v,'.@') !== false || $v[0] === '.') throw new InvalidArgumentException('must be an of '.$t);
						return $v;
					case 'alnum':
						if(!ctype_alnum(str_replace('_','',$v))) throw new InvalidArgumentException('must be an of '.$t);
						return $v;
					case 'choice':
						$v = is_bool($v) ? (($v) ? 'true' : 'false') : ((string)$v);
						if(!isset($param['choices']) || !in_array($v,$param['choices'],true)) throw new InvalidArgumentException('must be an of '.$t);
						return $v;
					case 'mixed': return $v;
					default:
						if(!($v instanceof $t)) throw new InvalidArgumentException('must be an of '.$t);
						return $v;
				}
		}
	}
	final protected function ___get___(){
		list($o,$n) = $this->_;
		if($o->a($n,'get') === false) throw new InvalidArgumentException('Processing not permitted [get()]');
		if($o->a($n,'attr') !== null) return (is_array($o->{$n})) ? $o->{$n} : (is_null($o->{$n}) ? array() : array($o->{$n}));
		return ($o instanceof $this) ? $o->{$n} : $o->{$n}();
	}
	final protected function ___set___(){
		list($o,$n) = $this->_;
		if($o->a($n,'set') === false) throw new InvalidArgumentException('Processing not permitted [set()]');
		$a = func_get_args();
		if(!($o instanceof $this)) return call_user_func_array($this->_,$a);
		$p = $o->prop_anon($n);
		if(func_num_args() == 1 && $a[0] === null){
			$o->{$n} = (($o->a($n,'attr') === null) ? null : array());
		}else{
			switch($o->a($n,'attr')){
				case 'a':
					$a = (is_array($a[0])) ? $a[0] : array($a[0]);
					foreach($a as $v) $o->{$n}[] = call_user_func_array(array(__CLASS__,'set_assert'),array($o->a($n,'type'),$v,$p));
					break;
				case 'h':
					$a = (sizeof($a) === 2) ? array($a[0]=>$a[1]) : (is_array($a[0]) ? $a[0] : array((($a[0] instanceof self) ? $a[0]->str() : $a[0])=>$a[0]));
					foreach($a as $k => $v) $o->{$n}[$k] = call_user_func_array(array(__CLASS__,'set_assert'),array($o->a($n,'type'),$v,$p));
					break;
				default:
					$o->{$n} = call_user_func_array(array(__CLASS__,'set_assert'),array($o->a($n,'type'),$a[0],$p));
			}
		}
		return $o->{$n};
	}
	final protected function ___rm___(){
		list($o,$n) = $this->_;
		if($o->a($n,'set') === false) throw new InvalidArgumentException('Processing not permitted [set()]');
		$a = func_get_args();
		if(!($o instanceof $this)) return call_user_func_array(array($this->_[0],'rm_'.$this->_[1]),$a);
		$r = call_user_func($this->_);
		$r = is_object($r) ? clone($r) : $r;
		
		if($o->a($n,'attr') === null){
			$o->{$n} = null;
		}else{
			if(empty($a) || empty($o->{$n})){
				$o->{$n} = array();
			}else{
				$v = array();
				foreach($a as $k){
					if(array_key_exists($k,$o->{$n})){
						$v[$k] = is_object($r[$k]) ? clone($r[$k]) : $r[$k];
						$o->{$n}[$k];
						unset($o->{$n}[$k]);
					}
				}
				$r = $v;
				if(sizeof($a) == 1) $r = empty($r) ? null : array_shift($r);				
			}
		}
		return $r;
	}
	final protected function ___fm___($f=null,$d=null){
		list($o,$n) = $this->_;
		$v = call_user_func($this->_);
		switch($o->a($n,'type')){
			case 'timestamp': return ($v === null) ? null : (date((empty($f) ? 'Y/m/d H:i:s' : $f),(int)$v));
			case 'date': return ($v === null) ? null : (date((empty($f) ? 'Y/m/d' : $f),(int)$v));
			case 'time':
				if($v === null) return 0;
				$h = floor($v / 3600);
				$i = floor(($v - ($h * 3600)) / 60);
				$s = floor($v - ($h * 3600) - ($i * 60));
				$m = str_replace(' ','0',rtrim(str_replace('0',' ',(substr(($v - ($h * 3600) - ($i * 60) - $s),2,12)))));
				return (($h == 0) ? '' : $h.':').(sprintf('%02d:%02d',$i,$s)).(($m == 0) ? '' : '.'.$m);
			case 'intdate': if($v === null) return null;
							return str_replace(array('Y','m','d'),array(substr($v,0,-4),substr($v,-4,2),substr($v,-2,2)),(empty($f) ? 'Y/m/d' : $f));
			case 'boolean': return ($v) ? (isset($d) ? $d : '') : (empty($f) ? 'false' : $f);
		}
		return $v;
	}
	final protected function ___label___(){
		list($o,$n) = $this->_;
		$label = $o->a($n,'label');
		return isset($label) ? $label : $n;
	}
	final protected function ___ar___($i=null,$j=null){
		list($o,$n) = $this->_;
		$v = call_user_func($this->_);
		$a = is_array($v) ? $v : (($v === null) ? array() : array($v));
		if(isset($i)){
			$c = 0;
			$l = ((isset($j) ? $j : sizeof($a)) + $i);
			$r = array();
			foreach($a as $k => $p){
				if($i <= $c && $l > $c) $r[$k] = $p;
				$c++;
			}
			return $r;
		}
		return $a;
	}
	final protected function ___in___($k=null,$d=null){
		$v = call_user_func($this->_);
		return (isset($k)) ? ((is_array($v) && isset($v[$k]) && $v[$k] !== null) ? $v[$k] : $d) : $d;
	}
	final protected function ___is___($k=null){
		list($o,$n) = $this->_;
		$v = call_user_func($this->_);
		
		if($o->a($n,'attr') !== null){
			if($k === null) return !empty($v);
			$v = isset($v[$k]) ? $v[$k] : null;
		}
		switch($o->a($n,'type')){
			case 'string':
			case 'text': return (isset($v) && $v !== '');
		}
		return (boolean)(($o->a($n,'type') == 'boolean') ? $v : isset($v));
	}
	
}
/**
 * ページを管理するモデル
 *
 * @author Kazutaka Tokushima
 * @license New BSD License
 * @var integer $offset 開始位置
 * @var integer $limit 終了位置
 * @var integer $current 現在位置
 * @var integer $total 合計
 * @var integer $first 最初のページ番号 @{"set":false}
 * @var integer $last 最後のページ番号 @{"set":false}
 * @var string $query_name pageを表すクエリの名前
 * @var mixed{} $vars query文字列とする値
 * @var mixed[] $contents １ページ分の内容
 * @var integer $contents_length コンテンツのサイズ @{"set":false}
 * @var boolean $dynamic ダイナミックページネーションとするか @{"set":false}
 * @var string $marker 現在の基点値 @{"set":false}
 * @var string $order 最後のソートキー
 */
class Paginator extends Object{
	protected $offset;
	protected $limit;
	protected $current;
	protected $total;
	protected $first = 1;
	protected $last;
	protected $vars = array();
	protected $query_name = 'page';
	protected $order;

	protected $contents = array();
	protected $contents_length = 0;
	protected $dynamic = false;
	protected $marker;

	private $asc = true;
	private $prop;
	private $next_c;
	private $prev_c;
	private $count_p = null;
	
	protected function __get_query_name__(){
		return (empty($this->query_name)) ? 'page' : $this->query_name;
	}
	/**
	 * 現在のページの最初の位置
	 * @return integer
	 */
	public function page_first(){
		return $this->offset + 1;
	}
	/**
	 * 現在のページの最後の位置
	 * @return integer
	 */
	public function page_last(){
		return (($this->offset + $this->limit) < $this->total) ? ($this->offset + $this->limit) : $this->total;
	}
	/**
	 * 動的コンテンツのPaginater
	 * @param integer $paginate_by １ページの要素数
	 * @param string $marker 基点となる値
	 * @param string $prop 対象とするプロパティ名
	 * @return self
	 */
	static public function dynamic_contents($paginate_by=20,$marker=null,$prop=null){
		$self = new self($paginate_by);
		$self->prop = $prop;
		$self->marker = $marker;
		$self->dynamic = true;

		if(!empty($marker) && $marker[0] == '-'){
			$self->asc = false;
			$self->marker = substr($marker,1);
		}
		return $self;
	}
	protected function __new__($paginate_by=20,$current=1,$total=0){
		$this->limit($paginate_by);
		$this->total($total);
		$this->current($current);
		
	}
	protected function __cp__($obj){
		if(!empty($obj)){
			if($obj instanceof Object){
				foreach($obj->prop_values() as $name => $value) $this->vars[$name] = $obj->{'fm_'.$name}();
			}else if(is_array($obj)){
				foreach($obj as $name => $value){
					if(ctype_alpha($name[0])) $this->vars[$name] = $value;
				}
			}
		}
	}
	/**
	 * 次のページ番号
	 * @return integer
	 */
	public function next(){
		if($this->dynamic) return $this->next_c;
		return $this->current + 1;
		
	}
	/**
	 * 前のページ番号
	 * @return integer
	 */
	public function prev(){
		if($this->dynamic) return $this->prev_c;
		return $this->current - 1;
		
	}
	/**
	 * 次のページがあるか
	 * @return boolean
	 */
	public function is_next(){
		if($this->dynamic) return isset($this->next_c);
		return ($this->last > $this->current);
		
	}
	/**
	 * 前のページがあるか
	 * @return boolean
	 */
	public function is_prev(){
		if($this->dynamic) return isset($this->prev_c);
		return ($this->current > 1);
		
	}
	/**
	 * 前のページを表すクエリ
	 * @return string
	 */
	public function query_prev(){
		return Http::query(array_merge(
							$this->ar_vars()
							,array($this->query_name()=>(($this->dynamic) ? (isset($this->prev_c) ? "-".$this->prev_c : null) : $this->prev()))
						));
		
	}
	/**
	 * 次のページを表すクエリ
	 * @return string
	 */
	public function query_next(){
		return Http::query(array_merge(
							$this->ar_vars()
							,array($this->query_name()=>(($this->dynamic) ? $this->next_c : $this->next()))
						));
		
	}
	/**
	 * orderを変更するクエリ
	 * @param string $order
	 * @param string $pre_order
	 * @return string
	 */
	public function query_order($order){
		if($this->is_vars('order')) $this->order = $this->rm_vars('order');
		return Http::query(array_merge(
							$this->ar_vars()
							,array('order'=>$order,'porder'=>$this->order())
						));
		
	}
	/**
	 * 指定のページを表すクエリ
	 * @param integer $current 現在のページ番号
	 * @return string
	 */
	public function query($current){
		return Http::query(array_merge($this->ar_vars(),array($this->query_name()=>$current)));
		
	}
	protected function __set_current__($value){
		$value = intval($value);
		$this->current = ($value === 0) ? 1 : $value;
		$this->offset = $this->limit * round(abs($this->current - 1));
	}
	protected function __set_total__($total){
		$this->total = intval($total);
		$this->last = ($this->total == 0 || $this->limit == 0) ? 0 : intval(ceil($this->total / $this->limit));
	}
	protected function ___which___($paginate){
		return null;
	}
	protected function __is_first__($paginate){
		return ($this->which_first($paginate) !== $this->first);
	}
	protected function __is_last__($paginate){
		return ($this->which_last($paginate) !== $this->last());
	}
	protected function __which_first__($paginate=null){
		if($paginate === null) return $this->first;
		$paginate = $paginate - 1;
		$first = ($this->current > ($paginate/2)) ? @ceil($this->current - ($paginate/2)) : 1;
		$last = ($this->last > ($first + $paginate)) ? ($first + $paginate) : $this->last;
		return (($last - $paginate) > 0) ? ($last - $paginate) : $first;
	}
	protected function __which_last__($paginate=null){
		if($paginate === null) return $this->last;
		$paginate = $paginate - 1;
		$first = ($this->current > ($paginate/2)) ? @ceil($this->current - ($paginate/2)) : 1;
		return ($this->last > ($first + $paginate)) ? ($first + $paginate) : $this->last;
	}
	/**
	 * ページとして有効な範囲のページ番号を有する配列を作成する
	 * @param integer $counter ページ数
	 * @return integer[]
	 */
	public function range($counter=10){
		if($this->which_last($counter) > 0) return range((int)$this->which_first($counter),(int)$this->which_last($counter));
		return array(1);
	}
	/**
	 * rangeが存在するか
	 * @return boolean
	 */
	public function has_range(){
		return ($this->last > 1);
		
	}
	/**
	 * limit分のコンテンツがあるか
	 * @return boolean
	 */
	public function is_filled(){
		if($this->contents_length >= $this->limit) return true;
		return false;
	}
	public function add($mixed){
		$this->contents($mixed);
		return $this;
	}
	protected function __set_contents__($mixed){
		if($this->dynamic){
			if($this->contents_length <= $this->limit){
				$this->contents_length++;
	
				if($this->contents_length > $this->limit){
					$this->finish_c();
				}else{
					if($this->asc){
						array_push($this->contents,$mixed);
					}else{
						array_unshift($this->contents,$mixed);
					}
				}
			}
		}else{
			$this->total($this->total+1);
			if($this->page_first() <= $this->total && $this->total <= ($this->offset + $this->limit)){
				$this->contents_length++;
				array_push($this->contents,$mixed);
			}
		}
	}
	/**
	 * order by asc
	 * @return boolean
	 */
	public function is_asc(){
		return $this->asc;
	}
	/**
	 * order by desc
	 * @return boolean
	 */
	public function is_desc(){
		return !$this->asc;
	}
	/**
	 * n > marker 
	 * @return boolean
	 */
	public function is_gt(){
		return $this->asc;		
	}
	/**
	 * n < marker
	 * @return boolean
	 */
	public function is_lt(){
		return !$this->asc;
	}
	/**
	 * contentsがlimitに達していない場合にさらに要求をするか
	 * @return boolean
	 */
	public function more(){
		if(!$this->dynamic) return false;
		if($this->contents_length > $this->limit) return false;		
		if($this->count_p !== null){
			if($this->count_p === $this->contents_length){
				$this->finish_c();
				return false;
			}
			$this->offset = $this->offset + $this->limit;
		}
		$this->count_p = $this->contents_length;
		return true;
		
	}
	private function finish_c(){
		if(isset($this->contents[$this->limit-1])) $this->next_c = $this->mn($this->contents[$this->limit-1]);		
		if(isset($this->contents[0]) && ((!$this->asc && $this->contents_length > $this->limit) || ($this->asc && $this->is_marker()))) $this->prev_c = $this->mn($this->contents[0]);
	}
	private function mn($v){
		return isset($this->prop) ? 
				(is_array($v) ? $v[$this->prop] : (is_object($v) ? (($v instanceof Object) ? $v->{$this->prop}() : $v->{$this->prop}) : null)) :
				$v;
	}
	
	
	
}
/**
 * リクエストを処理する
 *
 * @author Kazutaka Tokushima
 * @license New BSD License
 * @var mixed{} $vars リクエストされた値 
 * @var mixed{} $sessions セッション値
 * @var File{} $files アップロードされたファイル
 * @var string $args pathinfo または argv
 * @var mixed $user ログインユーザ
 * @var string $scope セッションスコープ名 @{"set":false}
 */
class Request extends Object{
	static private $session_save_path;
	static private $session_start = false;
	static private $port_http = 80;
	static private $port_https = 443;
	protected $scope;
	protected $vars = array();
	protected $sessions = array();
	protected $files = array();
	protected $args;
	protected $user;
	private $expire = 1209600;
	private $login_id;
	static private $session_limiter = 'nocache';
	static private $session_expire = 2592000;
	static private $session_gc_divisor = 100;
	static private $session_name = 'SID';
	/**
	 * セッションに関する設定
	 * @param alnum $name セッション名
	 * @param choice(none,nocache,private,private_no_expire,public) $limiter キャッシュリミッタ
	 * @param integer $expire 有効期間
	 * @param integer $gc_divisor GCの実行タイミング
	 */
	static public function config_session($name,$limiter=null,$expire=null,$gc_divisor=null){
		if(!empty($name)) self::$session_name = $name;
		if(isset($limiter)) self::$session_limiter = $limiter;
		if(isset($expire)) self::$session_expire = $expire;
		if(isset($gc_divisor)) self::$session_gc_divisor = $gc_divisor;
		if(!ctype_alpha(self::$session_name)) throw new InvalidArgumentException('session name is is not a alpha value');
	}
	/**
	 * ポートの設定をする
	 * @param int $http httpのポート番号
	 * @param int $https httpsのポート番号
	 */
	static public function config_port($http,$https){
		self::$port_http = $http;
		self::$port_https = $https;
	}
	protected function __new__(){
		if('' != ($pathinfo = (array_key_exists('PATH_INFO',$_SERVER)) ?
			( (empty($_SERVER['PATH_INFO']) && array_key_exists('ORIG_PATH_INFO',$_SERVER)) ?
					$_SERVER['ORIG_PATH_INFO'] : $_SERVER['PATH_INFO'] ) : (isset($this->vars['pathinfo']) ? $this->vars['pathinfo'] : null))
		){
			if($pathinfo[0] != '/') $pathinfo = '/'.$pathinfo;
			$this->args = preg_replace("/(.*?)\?.*/","\\1",$pathinfo);
		}
		if(isset($_SERVER['REQUEST_METHOD'])){
			$args = func_get_args();
			if(empty($args) || strpos($args[0],'_request_=false') === false){
				$this->scope = (!empty($args) && preg_match("/scope=([\w_]+?)/",$args[0],$m)) ? trim($m[1]) : get_class($this);
				if(isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'POST'){					
					if(isset($_POST) && is_array($_POST)){
						foreach($_POST as $key => $value) $this->vars[$key] = $this->set_var_value($value);
					}
					if(isset($_FILES) && is_array($_FILES)){
						foreach($_FILES as $key => $files) $this->files($key,$files);
					}
				}else if(isset($_GET) && is_array($_GET)){
					foreach($_GET as $key => $value) $this->vars[$key] = $this->set_var_value($value);
				}
				if(isset($_COOKIE) && is_array($_COOKIE)){
					foreach($_COOKIE as $key => $value) $this->vars[$key] = $this->set_var_value($value);
				}
				if(!self::$session_start){
					ini_set('session.gc_probability','1');
					ini_set('session.gc_divisor',self::$session_gc_divisor);
					session_cache_limiter(self::$session_limiter);
					session_cache_expire(self::$session_expire);
					session_name(self::$session_name);
					if(Object::C(__CLASS__)->has_module('session_read')){
						ini_set('session.save_handler','user');
						session_set_save_handler(
							array($this,'__session_open__'),array($this,'__session_close__'),array($this,'__session_read__'),
							array($this,'__session_write__'),array($this,'__session_destroy__'),array($this,'__session_gc__')
						);
						if(isset($this->vars[self::$session_name])){
							list($session_name,$id,$path) = array(self::$session_name,$this->vars[self::$session_name],session_save_path());
							/**
							 * セッションの検証
							 * @param string $session_name セッション名
							 * @param string $id セッションID
							 * @param string $path セッションを保存するパス
							 * @return boolean
							 */
							if(Object::C(__CLASS__)->call_module('session_verify',$session_name,$id,$path) !== true){
								session_regenerate_id(true);
							}
						}
					}else{
						if(isset($this->vars[self::$session_name]) 
							&& is_dir(session_save_path())
							&& !is_file(File::absolute(session_save_path(),'sess_'.$this->vars[self::$session_name]))
						){
							session_regenerate_id(true);
						}
					}
					session_start();
					self::$session_start = true;
				}
				$this->session_init();
			}
		}else if(isset($_SERVER['argv'])){
			$argv = $_SERVER['argv'];
			array_shift($argv);
			if(isset($argv[0]) && $argv[0][0] != '-'){
				$this->args = implode(' ',$argv);
			}else{
				$size = sizeof($argv);
				for($i=0;$i<$size;$i++){
					if($argv[$i][0] == '-'){
						if(isset($argv[$i+1]) && $argv[$i+1][0] != '-'){
							$this->vars[substr($argv[$i],1)] = $argv[$i+1];
							$i++;
						}else{
							$this->vars[substr($argv[$i],1)] = '';
						}
					}
				}
			}
		}
	}
	final protected function __set_files__($key,$req){
		$file = new File($req['name']);
		$file->tmp(isset($req['tmp_name']) ? $req['tmp_name'] : '');
		$file->size(isset($req['size']) ? $req['size'] : '');
		$file->error($req['error']);
		$this->files[$key] = $file;
	}
	final protected function __is_files__($key){
		return (isset($this->files[$key]) && !$this->files[$key]->is_error());
	}
	/**
	 * クッキーへの書き出し
	 * @param string $name 書き込む変数名
	 * @param int $expire 有効期限 (+ time)
	 * @param string $path パスの有効範囲
	 * @param boolean $subdomain サブドメインでも有効とするか
	 * @param boolean $secure httpsの場合のみ書き出しを行うか
	 */
	public function write_cookie($name,$expire=null,$path=null,$subdomain=false,$secure=false){
		if($expire === null) $expire = 1209600;
		$server = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '';
		if($subdomain && substr_count($server,'.') >= 2) $server = preg_replace("/.+(\.[^\.]+\.[^\.]+)$/","\\1",$server);
		if(empty($path)) $path = (App::url() == null) ? (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '') : App::url();
		setcookie($name,$this->in_vars($name),time() + $expire,$path,$server,$secure);
	}
	/**
	 * クッキーから削除
	 * 登録時と同条件のものが削除される
	 * @param string $name クッキー名
	 */
	public function delete_cookie($name,$path=null,$subdomain=false,$secure=false){
		$server = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '';
		if($subdomain && substr_count($server,'.') >= 2) $server = preg_replace("/.+(\.[^\.]+\.[^\.]+)$/","\\1",$server);
		if(empty($path)) $path = (App::url() == null) ? (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '') : App::url();
		setcookie($name,null,time() - 1209600,$path,$server,$secure);
		$this->rm_vars($name);
	}
	/**
	 * クッキーから呼び出された値か
	 * @param string $name
	 * @return boolean
	 */
	public function is_cookie($name){
		return (isset($_COOKIE[$name]));
	}
	static public function __shutdown__(){
		if(self::$session_start) session_write_close();
	}
	protected function __cp__($obj){
		if(!empty($obj)){
			if($obj instanceof Object){
				foreach($obj->prop_values() as $name => $value) $this->vars[$name] = $obj->{$name}();
			}else if(is_array($obj)){
				foreach($obj as $name => $value) $this->vars[$name] = $value;
			}else{
				throw new InvalidArgumentException('cp');
			}
		}
	}
	/**
	 * 現在のURLを返す
	 * @return string
	 */
	static public function current_url(){
		$port = isset($_SERVER['HTTPS']) ? (($_SERVER['HTTPS'] === 'on') ? self::$port_https : self::$port_http) : null;
		if(!isset($port)){
			if(isset($_SERVER['HTTP_X_FORWARDED_PORT'])){
				$port = $_SERVER['HTTP_X_FORWARDED_PORT'];
			}else if(isset($_SERVER['HTTP_X_FORWARDED_PROTO'])){
				$port = ($_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? self::$port_https : self::$port_http;
			}else if(isset($_SERVER['SERVER_PORT']) && !isset($_SERVER['HTTP_X_FORWARDED_HOST'])){
				$port = $_SERVER['SERVER_PORT'];
			}else{
				$port = self::$port_http;
			}
		}
		$server = isset($_SERVER['HTTP_X_FORWARDED_HOST']) ?
					$_SERVER['HTTP_X_FORWARDED_HOST'] :
					(
						isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 
						(isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '')
					);
		$path = isset($_SERVER['REQUEST_URI']) ? 
					preg_replace("/^(.+)\?.*$/","\\1",$_SERVER['REQUEST_URI']) : 
					(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'].(isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '') : '');
		if($port != self::$port_http && $port != self::$port_https) $server = $server.':'.$port;
		return (($port == self::$port_https) ? 'https' : 'http').'://'.preg_replace("/^(.+?)\?.*/","\\1",$server).$path;
	}
	/**
	 * 現在のQUERY_STRINGを返す
	 * @return string
	 */
	static public function query_string(){
		return isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : null;
	}
	/**
	 * 現在のリクエストクエリを返す
	 * @return string
	 */
	static public function request_string(){
		$str = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : null;
		return (isset($str) ? '&' : '').file_get_contents("php://input");
	}
	/**
	 * POSTされたか
	 * @return boolean
	 */
	public function is_post(){
		return (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'POST');
	}
	/**
	 * CLIで実行されたか
	 * @return boolean
	 */
	public function is_cli(){
		return (php_sapi_name() == 'cli' || !isset($_SERVER['REQUEST_METHOD']));
	}
	private function set_var_value($value){
		return (get_magic_quotes_gpc() && is_string($value)) ? stripslashes($value) : $value;
	}
	private function sess_name($name){
		return $this->scope.'__'.$name;
	}
	protected function __rm_sessions__(){
		$args = func_get_args();
		$result = array();
		if(!empty($args)){
			foreach($args as $arg){
				if($arg instanceof self) $arg = $arg->str();
				if(isset($this->sessions[$this->sess_name($arg)])){
					$result[$arg] = $this->sessions[$this->sess_name($arg)];
					unset($this->sessions[$this->sess_name($arg)]);
					if(isset($_SESSION[$this->sess_name($arg)])) unset($_SESSION[$this->sess_name($arg)]);
				}
			}
			if(sizeof($args) == 1) $result = array_shift($result);
		}
		return $result;
	}
	protected function __set_sessions__($key,$value){
		if(is_object($value)){
			$ref = new ReflectionClass(get_class($value));
			if(substr($ref->getFileName(),-4) !== ".php") throw new InvalidArgumentException($key.' is not permitted');
		}
		return $this->sessions[$this->sess_name($key)] = $value;
	}
	protected function __in_sessions__($key,$default=null){
		return isset($this->sessions[$this->sess_name($key)]) ? $this->sessions[$this->sess_name($key)] : $default;
	}
	protected function __is_sessions__($key){
		return isset($this->sessions[$this->sess_name($key)]);
	}
	private function session_init(){
		$this->login_id = __CLASS__.'_LOGIN_';
		if(!isset($_SESSION)) throw new LogicException('no session');
		$this->sessions = &$_SESSION;
		$vars = $this->in_sessions('_saved_vars_');
		if(is_array($vars)){
			foreach($vars as $key => $value) $this->vars($key,$value);
		}
		$this->rm_sessions('_saved_vars_');
		$exceptions = $this->in_sessions('_saved_exceptions_');
		if(is_array($exceptions)){
			foreach($exceptions as $e) Exceptions::add($e[0],$e[1]);
		}
		$this->rm_sessions('_saved_exceptions_');
		if(isset($this->sessions[$this->sess_name($this->login_id.'USER')])){
			$this->user($this->sessions[$this->sess_name($this->login_id.'USER')]);
		}
	}
	/**
	 * Exceptionを保存する
	 * @param Exception $exception
	 * @param string $name
	 */
	protected function save_exception(Exception $exception,$name=null){
		$exceptions = $this->in_sessions('_saved_exceptions_');
		if(!is_array($exceptions)) $exceptions = array();
		$exceptions[] = array($exception,$name);
		$this->sessions('_saved_exceptions_',$exceptions);
	}
	/**
	 * 現在のvarsを保存する
	 */
	protected function save_current_vars(){
		foreach($this->vars() as $k => $v){
			if(is_object($v)){
				$ref = new ReflectionClass(get_class($v));
				if(substr($ref->getFileName(),-4) !== ".php") throw new InvalidArgumentException($k.' is not permitted');
			}
		}
		$this->sessions('_saved_vars_',$this->vars());
	}
	/**
	 * ユーザエージェント
	 * @return string
	 */
	public function user_agent(){
		return isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null;
	}
	protected function __set_user__($user){
		if(!isset($_SESSION)) throw LogicException('no session');
		$type = $this->a('user','type');
		if($type !== 'mixed' && !($user instanceof $type)){
			$this->logout();
			throw new InvalidArgumentException('user is not a '.$type.' value');
		}
		$this->sessions($this->login_id.'USER',$user);
		$this->sessions($this->login_id,$this->login_id);
		$this->user = &$this->sessions[$this->sess_name($this->login_id.'USER')];
		return $this->user;
	}
	/**
	 * ログインする
	 * POSTの場合のみ処理される
	 * @return boolean
	 */
	public function login(){
		if(!isset($_SESSION)) throw new LogicException('no session');
		if($this->is_login()) return true;
		/**
		 * ログイン条件
		 * @param self $this
		 * @return boolean
		 */
		if(!$this->is_post() || !$this->has_module('login_condition') || $this->call_module('login_condition',$this) === false){
			/**
			 * ログイン失敗
			 * @param self $this
			 */
			$this->call_module('login_invalid',$this);
			return false;
		}
		$this->sessions($this->login_id,$this->login_id);
		session_regenerate_id(true);
		/**
		 * ログインの後処理
		 * @param self $this
		 */
		$this->call_module('after_login',$this);
		return true;
	}
	/**
	 * ログイン済みか
	 * @return boolean
	 */
	public function is_login(){
		return $this->is_sessions($this->login_id);
	}
	/**
	 * 後処理、失敗処理の無いログイン
	 * GETの場合のみ処理される
	 * クッキーをもちいた自動ログイン等に利用する
	 * @return boolean
	 */
	public function silent(){
		if($this->is_login()) return true;
		/**
		 * ログイン条件
		 * @param self $this
		 * @return boolean
		 */
		if($this->is_post() || !$this->has_module('silent_login_condition') || $this->call_module('silent_login_condition',$this) === false){
			return false;
		}
		$this->sessions($this->login_id,$this->login_id);
		return true;
	}
	/**
	 * ログアウトする
	 */
	public function logout(){
		if(!isset($_SESSION)) throw LogicException('no session');
		/**
		 * ログアウト前処理
		 * @param self $this
		 */
		$this->call_module('before_logout',$this);
		$this->rm_sessions($this->login_id.'USER');
		$this->rm_sessions($this->login_id);
		session_regenerate_id(true);
	}
	final public function __session_open__($save_path,$session_name){
		/**
		 * セッションの初期処理
		 * @param string $save_path セッションを保存するパス
		 * @param string $session_name セッション名
		 * @return boolean
		 */
		if(Object::C(__CLASS__)->has_module('session_open')) return Object::C(__CLASS__)->call_module('session_open',$save_path,$session_name);
		return true;
	}
	final public function __session_close__(){
		/**
		 * セッションの終了処理
		 * @return boolean
		 */
		if(Object::C(__CLASS__)->has_module('session_close')) return Object::C(__CLASS__)->call_module('session_close');
		return true;
	}
	final public function __session_read__($id){
		/**
		 * セッション情報読み込み処理
		 * @param string $id セッションID
		 * @return mixed
		 */
		return Object::C(__CLASS__)->call_module('session_read',$id);
	}
	final public function __session_write__($id,$sess_data){
		/**
		 * セッション情報書き込み処理
		 * @param string $id セッションID
		 * @param mixed $sess_data セッションに保存する情報
		 * @return boolean
		 */
		if(Object::C(__CLASS__)->has_module('session_write')) return Object::C(__CLASS__)->call_module('session_write',$id,$sess_data);
		return true;
	}
	final public function __session_destroy__($id){
		/**
		 * セッション情報破棄処理
		 * @param string $id セッションID
		 * @return boolean
		 */
		if(Object::C(__CLASS__)->has_module('session_destroy')) return Object::C(__CLASS__)->call_module('session_destroy',$id);
		return true;
	}
	final public function __session_gc__($maxlifetime){
		/**
		 * ガーベージコレクト処理
		 * @param $maxlifetime セッションの最大有効期間
		 * @return boolean
		 */
		if(Object::C(__CLASS__)->has_module('session_gc')) return Object::C(__CLASS__)->call_module('session_gc',$maxlifetime);
		return true;
	}
}
/**
 * データストア
 * @author tokushima
 */
class Store extends Object{
	/**
	 * $keyが存在するか
	 * @param string $key キー名
	 * @param boolean $ignore_time 有効期限
	 * @return boolean
	 */
	static public function has($key,$ignore_time=false){
		$id = self::id($key);
		/**
		 * 存在確認処理
		 * @param string $id 
		 * @param boolean $ignore_time 有効期限
		 * @return boolean
		 */
		if(Object::C(__CLASS__)->has_module("store_has")) return Object::C(__CLASS__)->call_module("store_has",$id,$ignore_time);
		$path = File::absolute(App::work("store"),$id);
		$path = (is_file($path)) ? $path : ((is_file($path."_s") ? $path."_s" : null));
		if($ignore_time && $path !== null) return true;
		return (isset($path) && File::last_update($path) > time());
	}
	/**
	 * Storeへセットする
	 * @param string $key キー名
	 * @param mixed $value 内容
	 * @param integer $expiry_time 有効期限
	 * @return mixed 内容を返す
	 */
	static public function set($key,$value,$expiry_time=2592000){
		$id = self::id($key);
		/**
		 * 保存処理
		 * @param string $id
		 * @param mixed $value 内容
		 * @param integer $expiry_time 有効期限
		 * @return mixed 内容を返す
		 */
		if(Object::C(__CLASS__)->has_module("store_set")) return Object::C(__CLASS__)->call_module("store_set",$id,$value,$expiry_time);
		$path = File::absolute(App::work("store"),$id);
		if(!is_string($value)) list($value,$path) = array(serialize($value),$path."_s");
		File::gzwrite($path,$value);
		touch($path,time()+$expiry_time,time()+$expiry_time);
		return $value;
	}
	/**
	 * セット時にセット時間も記録する
	 * lt_getと対で利用する
	 * set record creation time
	 * 
	 * @param string $key キー名
	 * @param mixed $value 内容
	 * @param integer $expiry_time 有効期限
	 * @return mixed 内容を返す
	 */
	static public function set_rct($key,$value,$expiry_time=2592000){
		self::set($key,$value,$expiry_time);
		self::set(__CLASS__.'_settime_'.$key,time(),$expiry_time);
		return $value;
	}
	/**
	 * Storeから取得する
	 * @param string $key キー名
	 * @return mixed
	 */
	static public function get($key){
		$id = self::id($key);
		/**
		 * 取得処理
		 * @param string $id
		 * @return mixed
		 */
		if(Object::C(__CLASS__)->has_module("store_get")) return Object::C(__CLASS__)->call_module("store_get",$id);
		$path = File::absolute(App::work("store"),$id);
		if(is_file($path)) return File::gzread($path);
		if(is_file($path."_s")) return unserialize(File::gzread($path."_s"));
		return null;
	}
	/**
	 * set時間より$timeが小さければ取得
	 * set_rctと対で利用する
	 * @param mixed $value 取得された値のはいる変数
	 * @param string $key キー名
	 * @param integer $time 指定時間
	 * @return boolean 取得できたか
	 */
	static public function lt_get(&$value,$key,$time){
		$args = func_get_args();
		$args[0] = $args[1] = 0;
		$max = call_user_func_array('max',$args);
		if(((int)self::get(__CLASS__.'_settime_'.$key)) < $max) return false;
		$value = self::get($key);
		return true;
	}
	/**
	 * Storeから削除する
	 * @param string $key キー名
	 */
	static public function delete($key=null){
		$id = self::id($key);
		/**
		 * 削除処理
		 * @param string $id
		 * @return boolean
		 */
		if(Object::C(__CLASS__)->has_module("store_delete")) return Object::C(__CLASS__)->call_module("store_delete",$id);
		if(!is_dir(App::work("store"))) return true;
		if(empty($key)) return File::rm(App::work("store"),false);
		$path = File::absolute(App::work("store"),$id);
		if(is_file($path)) File::rm($path);
		if(is_file($path.'_s')) File::rm($path.'_s');
	}
	static private function id($key){
		return md5(implode("",(is_array($key)) ? $key : array($key)));
	}
}
/**
 * Tagイテレータ
 * @author Kazutaka Tokushima
 * @license New BSD License
 */
class TagIterator implements Iterator{
	private $name = null;
	private $plain = null;
	private $tag = null;
	private $offset = 0;
	private $length = 0;
	private $count = 0;
	public function __construct($tag_name,$value,$offset,$length){
		$this->name = $tag_name;
		$this->plain = $value;
		$this->offset = $offset;
		$this->length = $length;
		$this->count = 0;
	}
	/**
	 * @see Iterator
	 */
	public function key(){
		$this->tag->name();
	}
	/**
	 * @see Iterator
	 */
	public function current(){
		$this->plain = substr($this->plain,0,$this->tag->pos()).substr($this->plain,$this->tag->pos() + strlen($this->tag->plain()));
		$this->count++;
		return $this->tag;
	}
	/**
	 * @see Iterator
	 */
	public function valid(){
		if($this->length > 0 && ($this->offset + $this->length) <= $this->count) return false;
		if(is_array($this->name)){
			$tags = array();
			foreach($this->name as $name){
				if(Tag::setof($get_tag,$this->plain,$name)) $tags[$get_tag->pos()] = $get_tag;
			}
			if(empty($tags)) return false;
			ksort($tags,SORT_NUMERIC);
			foreach($tags as $this->tag) return true;
		}
		return Tag::setof($this->tag,$this->plain,$this->name);
	}
	/**
	 * @see Iterator
	 */
	public function next(){
	}
	/**
	 * @see Iterator
	 */
	public function rewind(){
		for($i=0;$i<$this->offset;$i++){
			$this->valid();
			$this->current();
		}
	}
}
/**
 * Tagを処理する
 *
 * @author Kazutaka Tokushima
 * @license New BSD License
 * @var string{} $attr アトリビュート
 * @var string{} $param パラメータ
 * @var string $plain 実際の文字列 @{"set":false}
 * @var number $pos 見つかった位置 @{"set":false}
 * @var boolean $close_empty 内容が無い場合に<tag />とするか
 * @var boolean $cdata_value 内容をCDATAとして表現するか
 * @var string $name タグ名
 * @var string $value 内容
 */
class Tag extends Object{
	protected $param;
	protected $attr;
	protected $name;
	protected $value;
	protected $plain;
	protected $pos;
	protected $close_empty = true;
	protected $cdata_value = false;
	final protected function __str__(){
		return $this->get();
	}
	final protected function __new__($name=null,$value=null){
		if($value === null && ($name instanceof Object)){
			$this->name(get_class($name));
			$this->extract($name);
		}else{
			$this->name(trim($name));
			$this->value($value);
		}
		
	}
	final protected function __set_value__($value){
		if(is_array($value) || (is_object($value) && !($value instanceof self))){
			$this->extract($value);
		}else{
			if(is_bool($value)) $value = ($value) ? "true" : "false";
			$this->value = ($value === '' || $value === null) ? null : (($this->cdata_value) ? self::xmltext($value) : $value);
		}
	}
	final public function add($arg){
		$args = func_get_args();
		if(!empty($args)){
			if(sizeof($args) == 2){
				$this->param($args[0],$args[1]);
			}else if($args[0] instanceof self){
				$this->value = $this->value().$args[0]->get();
			}else if($args[0] instanceof Object){
				$this->value($this->value().$args[0]->str());
			}else{
				$this->value($this->value().Text::str($args[0]));
			}
		}
		return $this;
		
	}
	final protected function __hash__(){
		$list = array();
		$src = $this->value();
		foreach($this->ar_param() as $name => $param) $list[$name] = $param[1];
		while(self::setof($ctag,$src)){
			$result = $ctag->hash();
			if(isset($list[$ctag->name()])){
				if(!is_array($list[$ctag->name()]) || !array_key_exists(0,$list[$ctag->name()])) $list[$ctag->name()] = array($list[$ctag->name()]);
				$list[$ctag->name()][] = $result;
			}else{
				$list[$ctag->name()] = $result;
			}
			$src = substr($src,strpos($src,$ctag->plain()) + strlen($ctag->plain()));
		}
		return (!empty($list)) ? $list : $src;
		
	}
	final protected function __set_param__($name,$value){
		$this->param[strtolower($name)] = array($name,(is_bool($value) ? (($value) ? 'true' : 'false') : (($this->cdata_value) ? Text::htmlencode($value) : $value)));
	}
	/**
	 * パラメータを取得
	 *
	 * @param string $name
	 * @param mixed $default
	 * @return string
	 */
	final protected function __in_param__($name,$default=null){
		$name = strtolower($name);
		$result = (isset($this->param[$name])) ? $this->param[$name] : null;
		$result = ($result === null) ? $default : $result[1];
		return ($this->cdata_value) ? Text::htmldecode($result) : $result;
		
	}
	/**
	 * 開始タグを取得
	 * @return string
	 */
	public function start(){
		$param = $attr = '';
		foreach($this->ar_param() as $p) $param .= ' '.$p[0].'="'.$p[1].'"';
		foreach($this->ar_attr() as $value) $attr .= (($value[0] == '<') ? '' : ' ').$value;
		return '<'.$this->name().$param.$attr.(($this->is_close_empty() && !$this->is_value()) ? ' /' : '').'>';
		
	}
	/**
	 * 終了タグを取得
	 * @return string
	 */
	public function end(){
		return (!$this->is_close_empty() || $this->is_value()) ? sprintf("</%s>",$this->name()) : '';
		
	}
	/**
	 * xmlとして取得
	 * @param string $encoding エンコード名
	 * @return string
	 */
	public function get($encoding=null){
		if(!$this->is_name()) throw new LogicException("undef name");
		return ((empty($encoding)) ? '' : '<?xml version="1.0" encoding="'.$encoding.'" ?>'."\n").$this->start().$this->value().$this->end();
		
		
	}
	/**
	 * xmlとし出力する
	 * @param string $encoding エンコード名
	 * @param string $name ファイル名
	 */
	public function output($encoding=null,$name=null){
		Log::disable_display();
		Http::send_header(sprintf('Content-Type: application/xml%s',(empty($name) ? '' : sprintf('; name=%s',$name))));
		print($this->get($encoding));
		exit;
	}
	/**
	 * attachmentとして出力する
	 * @param string $encoding エンコード名
	 * @param string $name ファイル名
	 */
	public function attach($encoding=null,$name=null){
		Http::send_header(sprintf('Content-Disposition: attachment%s',(empty($name) ? '' : sprintf('; filename=%s',$name))));
		$this->output($encoding,$name);
	}
	
	/**
	 * 指定のタグを探索する
	 * @param string $tag_name タグ名
	 * @param integer $offset 開始位置
	 * @param integer $length 取得する最大数
	 * @return TagIterator
	 */
	public function in($tag_name,$offset=0,$length=0){
		return new TagIterator($tag_name,$this->value(),$offset,$length);
		
		
		
	}
	/**
	 * 指定のタグをすべて返す
	 * @param string $tag_name タグ名
	 * @param integer $offset 開始位置
	 * @param integer $length 取得する最大数
	 * @return self[]
	 */
	public function in_all($tag_name,$offset=0,$length=0){
		$result = array();
		foreach($this->in($tag_name,$offset,$length) as $tag) $result[] = $tag;
		return $result;
		
	}
	/**
	 * パスで検索する
	 * @param string $path 検索文字列
	 * @return mixed
	 */
	public function f($path){
		$arg = (func_num_args() == 2) ? func_get_arg(1) : null;
		$paths = explode('.',$path);
		$last = (strpos($path,'(') === false) ? null : array_pop($paths);
		$tag = clone($this);
		$route = array();
		if($arg !== null) $arg = (is_bool($arg)) ? (($arg) ? 'true' : 'false') : strval($arg);
		foreach($paths as $p){
			$pos = 0;
			if(preg_match("/^(.+)\[([\d]+?)\]$/",$p,$matchs)) list($tmp,$p,$pos) = $matchs;
			$tags = $tag->in_all($p,$pos,1);
			if(!isset($tags[0]) || !($tags[0] instanceof self)){
				$tag = null;
				break;
			}
			$route[] = $tag = $tags[0];
		}
		if($tag instanceof self){
			if($arg === null){
				switch($last){
					case '': return $tag;
					case 'plain()': return $tag->plain();
					case 'value()': return $tag->value();
					default:
						if(preg_match("/^(param|attr|in_all|in)\((.+?)\)$/",$last,$matchs)){
							list($null,$type,$name) = $matchs;
							switch($type){
								case 'in_all': return $tag->in_all(trim($name));
								case 'in': return $tag->in(trim($name));
								case 'param': return $tag->in_param($name);
								case 'attr': return $tag->is_attr($name);
							}
						}
						return null;
				}
			}
			if($arg instanceof self) $arg = $arg->get();
			if(is_bool($arg)) $arg = ($arg) ? 'true' : 'false';
			krsort($route,SORT_NUMERIC);
			$ltag = $rtag = null;
			$f = true;
			foreach($route as $r){
				$ltag = clone($r);
				if($f){
					switch($last){
						case 'value()':
							$replace = $arg;
							break;
						default:
							if(preg_match("/^(param|attr|in_all|in)\((.+?)\)$/",$last,$matchs)){
								list($null,$type,$name) = $matchs;
								switch($type){
									case 'param':
										$r->param($name,$arg);
										$replace = $r->get();
										break;
									case 'attr':
										($arg === 'true') ? $r->attr($name) :$r->rm_attr($name);
										$replace = $r->get();
										break;
									default:
										return null;
								}
							}
					}
					$f = false;
				}
				$r->value(empty($rtag) ? $replace : str_replace($rtag->plain(),$replace,$r->value()));
				$replace = $r->get();
				$rtag = clone($ltag);
			}
			$this->value(str_replace($ltag->plain(),$replace,$this->value()));
			return null;
		}
		return (!empty($last) && substr($last,0,2) == 'in') ? array() : null;
		
	}
	/**
	 * idで検索する
	 *
	 * @param string $name 指定のID
	 * @return self
	 */
	public function id($name){
		if(preg_match("/<.+[\s]*id[\s]*=[\s]*([\"\'])".preg_quote($name)."\\1/",$this->value(),$match,PREG_OFFSET_CAPTURE)){
			if(self::setof($tag,substr($this->value(),$match[0][1]))) return $tag;
		}
		return null;
		
	}
	/**
	 * value値がcdataとなるTagを返す
	 * @param $name タグ名
	 * @param $value 内容
	 * @return self
	 */
	final static public function xml($name,$value=null){
		$self = new self($name);
		$self->cdata_value(true);
		$self->value($value);
		return $self;
	}
	/**
	 * ユニークな名前でTagとして作成する
	 * @param string $plain 内容
	 * @return self
	 */
	final static public function anyhow($plain){
		$uniq = uniqid('Anyhow_');
		if(self::setof($tag,'<'.$uniq.'>'.$plain.'</'.$uniq.'>',$uniq)) return $tag;
		
	}
	/**
	 * Tagとして正しければTagインスタンスを作成する
	 * @param mixed $var
	 * @param string $plain
	 * @param string $name
	 * @return boolean
	 */
	final static public function setof(&$var,$plain,$name=null){
		return self::parse_tag($var,$plain,$name);
		
		
	}
	static private function parse_tag(&$var,$plain,$name=null,$vtag=null){
		$plain = Text::str($plain);
		$name = Text::str($name);
		if(empty($name) && preg_match("/<([\w\:\-]+)[\s][^>]*?>|<([\w\:\-]+)>/is",$plain,$parse)){
			$name = str_replace(array("\r\n","\r","\n"),"",(empty($parse[1]) ? $parse[2] : $parse[1]));
		}
		$qname = preg_quote($name,'/');
		if(!preg_match("/<(".$qname.")([\s][^>]*?)>|<(".$qname.")>/is",$plain,$parse,PREG_OFFSET_CAPTURE)) return false;
		$var = new self();
		$var->pos = $parse[0][1];
		$balance = 0;
		$params = '';
		if(substr($parse[0][0],-2) == '/>'){
			$var->name = $parse[1][0];
			$var->plain = empty($vtag) ? $parse[0][0] : preg_replace('/'.preg_quote(substr($vtag,0,-1).' />','/').'/',$vtag,$parse[0][0],1);
			$params = $parse[2][0];
		}else if(preg_match_all("/<[\/]{0,1}".$qname."[\s][^>]*[^\/]>|<[\/]{0,1}".$qname."[\s]*>/is",$plain,$list,PREG_OFFSET_CAPTURE,$var->pos)){
			foreach($list[0] as $arg){
				if(($balance += (($arg[0][1] == '/') ? -1 : 1)) <= 0 &&
						preg_match("/^(<(".$qname.")([\s]*[^>]*)>)(.*)(<\/\\2[\s]*>)$/is",
							substr($plain,$var->pos,($arg[1] + strlen($arg[0]) - $var->pos)),
							$match
						)
				){
					$var->plain = $match[0];
					$var->name = $match[2];
					$var->value = (empty($match[4])) ? null : $match[4];
					$params = $match[3];
					break;
				}
			}
			if(!isset($var->plain)){
				return self::parse_tag($var,preg_replace('/'.preg_quote($list[0][0][0],'/').'/',substr($list[0][0][0],0,-1).' />',$plain,1),$name,$list[0][0][0]);
			}
		}
		if(!isset($var->plain)) return false;
		if(!empty($params)){
			if(preg_match_all("/[\s]+([\w\-\:]+)[\s]*=[\s]*([\"\'])([^\\2]*?)\\2/ms",$params,$param)){
				foreach($param[0] as $id => $value){
					$var->param($param[1][$id],$param[3][$id]);
					$params = str_replace($value,"",$params);
				}
			}
			if(preg_match_all("/([\w\-]+)/",$params,$attr)){
				foreach($attr[1] as $value) $var->attr($value);
			}
		}
		return true;
	}
	/**
	 * 指定のタグで閉じていないものを閉じる
	 * @param string $src XML文字列
	 * @param string $name 閉じたいタグ名
	 * @return string
	 */
	static public function xhtmlnize($src,$name){
		
		$args = func_get_args();
		array_shift($args);
		foreach($args as $name){
			if(preg_match_all(sprintf("/(<%s>)|(<%s[\s][^>]*[^\/]>)/is",$name,$name),$src,$link)){
				foreach($link[0] as $value) $src = str_replace($value,substr($value,0,-1).' />',$src);
			}
		}
		return $src;
	}
	/**
	 * CDATA形式にして返す
	 * @param string $value CDATA形式にしたい文字列
	 * @return string
	 */
	static public function xmltext($value){
		if(is_string($value) && strpos($value,'<![CDATA[') === false && (strpos($value,'<') !== false || strpos($value,'>') !== false || preg_match("/\&[^#\da-zA-Z]/",$value))) return '<![CDATA['.$value.']]>';
		return $value;
		
	}
	/**
	 * CDATA形式から値を取り出す
	 * @param string $value 内容
	 * @return string
	 */
	static public function cdata($value){
		if(preg_match_all("/<\!\[CDATA\[(.+?)\]\]>/ims",$value,$match)){
			foreach($match[1] as $key => $v) $value = str_replace($match[0][$key],$v,$value);
		}
		return $value;
		
	}
	/**
	 * XMLコメントを削除する
	 * @param string $src コメントを含む文字列
	 * @return string
	 */
	static public function uncomment($src){
		return preg_replace('/<!--.+?-->/s','',$src);
		
	}
	/**
	 * ファイルから読み込みTagとして正しければTagインスタンスを作成する
	 * @param mixied $var Tagを格納する変数
	 * @param string $xml_file ファイルパス
	 * @param string $name ルートの要素名
	 * @return boolean
	 */
	static public function load(&$var,$xml_file,$name=null){
		return self::setof($var,((strpos($xml_file,'://') === false) ? File::read($xml_file) : Http::read($xml_file)),$name);
	}
	/**
	 * 配列またはObjectから抽出して追加する
	 * @param mixed $var 展開する内容
	 * @return $this
	 */
	public function extract($var){
		if($var instanceof self) $var = $var->value();
		return $this->extract_get($var);
		
		
		
		
	}
	private function extract_get($var){
		if(is_object($var)){
			if($var instanceof Object){
				$var = ($var instanceof self) ? $var->get() : $var->hash();
			}else{
				$var = get_object_vars($var);				
			}
		}
		if(is_array($var)){
			foreach($var as $key => $value){
				if(is_bool($value)) $value = ($value === true) ? 'true' : 'false';
				if(is_numeric($key) && is_object($value)) $key = get_class($value);
				if(!is_numeric($key)){
					$tag = new self($key);
					$tag->cdata_value($this->cdata_value());
					$this->add($tag->extract_get($value));
				}
			}
		}else{
			$this->add($var);
		}
		return $this;
	}
}
/**
 * テンプレートを処理する
 *
 * @author Kazutaka Tokushima
 * @license New BSD License
 * @var mixed{} $vars コンテキストとなる値
 * @var string{} $statics スタティックでアクセスするコンテキストとなる値
 * @var string $base_path テンプレートファイルのベースパス
 * @var string $media_url メディアファイルのベースパス
 * @var string $filename テンプレートファイル名
 * @var string $put_block 強制ブロック
 * @var boolean $secure セキュアURLを使用するか
 */
class Template extends Object{
	static private $base_media_url;
	static private $base_template_path;
	static private $exception_str;
	static private $is_cache = false;
	protected $base_path;
	protected $media_url;
	protected $statics = array();
	protected $vars = array();
	protected $filename;
	protected $put_block;
	protected $secure = false;
	private $selected_template;
	/**
	 * ベースパスの定義
	 * @param string $template_path テンプレートファイルのベースパス
	 * @param string $media_url メディアURLのベースパス
	 */
	static public function config_path($template_path,$media_url=null){
		if(!empty($template_path)) self::$base_template_path = preg_replace("/^(.+)\/$/","\\1",str_replace("\\","/",$template_path))."/";
		if(!empty($media_url)) self::$base_media_url = preg_replace("/^(.+)\/$/","\\1",$media_url)."/";
	}
	/**
	 * 例外時に表示する文字列の定義
	 * @param string $str 例外時に表示する文字列
	 */
	static public function config_exception($str){
		self::$exception_str = $str;
	}
	/**
	 * キャッシュするかの定義
	 * @param boolean $bool キャッシュするか
	 */
	static public function config_cache($bool){
		self::$is_cache = (boolean)$bool;
	}
	/**
	 * キャッシュが有効か
	 * @return boolean
	 */
	static public function is_cache(){
		return self::$is_cache;
	}
	/**
	 * テンプレートのパス
	 * @return string
	 */
	static public function base_template_path(){
		return isset(self::$base_template_path) ? self::$base_template_path : App::path('resources/templates').'/';
	}
	/**
	 * メディアURL
	 * @return string
	 */
	static public function base_media_url(){
		return isset(self::$base_media_url) ? self::$base_media_url : App::url('resources/media',false).'/';
	}
	protected function __set_statics__($name,$class){
		$this->statics['$'.$var.'->'] = Lib::import($class).'::';
	}
	protected function __new__($media_url=null,$base_path=null){
		$this->media_url($media_url);
		$this->base_path($base_path);
	}
	protected function __cp__($obj){
		if(!empty($obj)){
			if($obj instanceof Object){
				foreach($obj->prop_values() as $name => $value) $this->vars[$name] = $obj->{'fm_'.$name}();
			}else if(is_array($obj)){
				foreach($obj as $name => $value) $this->vars[$name] = $value;
			}else{
				throw new InvalidArgumentException('cp');
			}
		}
	}
	protected function __set_base_path__($path){
		$this->base_path = File::absolute(self::base_template_path(),File::path_slash($path,null,true));
	}
	protected function __set_media_url__($url){
		$this->media_url = File::absolute(self::base_media_url(),File::path_slash($url,null,true));
	}
	protected function __get_filename__(){
		return empty($this->filename) ? null : File::absolute($this->base_path,$this->filename);
	}
	protected function __fm_filename__($path=null){
		return ($path === null) ? $this->filename() : File::absolute($this->base_path,$path);
	}
	protected function __is_filename__($path=null){
		$path = ($path === null) ? $this->filename() : File::absolute($this->base_path,$path);
		return (!empty($path) && is_file($path));
	}
	/**
	 * 指定したテンプレートが存在するか
	 * @return boolean
	 */
	public function has(){
		if(empty($this->put_block)) return is_file($this->filename($filename));
		return is_file(File::absolute($this->base_path,$this->put_block));
	}
	/**
	 * ファイルから生成する
	 * @param string $filename テンプレートファイルパス
	 * @param string $template_name 対象となるテンプレート名
	 * @return string
	 */
	public function read($filename=null,$template_name=null){
		if(!empty($filename)) $this->filename($filename);
		$this->selected_template = $template_name;
		$cfilename = $this->put_block.$this->filename().$this->selected_template;
		if(!self::$is_cache || !Store::has($cfilename,true)){
			$filename = $this->filename();
			if(!empty($this->put_block)){
				$src = $this->read_src(File::absolute($this->base_path,$this->put_block));
				if(strpos($src,'rt:extends') !== false){
					$tag = Tag::anyhow($src);
					foreach($tag->in('rt:extends') as $ext) $src = str_replace($ext->plain(),'',$src);
				}
				$src = sprintf('<rt:extends href="%s" />\n',$filename).$src;
			}else{
				$src = $this->read_src($filename);
			}
			$src = $this->parse($src);
			if(self::$is_cache) Store::set($cfilename,$src);
		}else{
			$src = Store::get($cfilename);
		}
		$src = $this->html_reform($this->exec($src));
		return $this->replace_ptag($src);
	}
	private function read_src($filename){
		if(strpos($filename,'://') !== false){
			if(empty($this->media_url)) $this->media_url($filename);
			return Http::read($filename);
		}
		return File::read($filename);
	}
	
	/**
	 * 出力して終了する
	 * @param string $filename テンプレートファイルパス
	 */
	public function output($filename=null){
		print($this->read($filename));
		exit;
	}
	/**
	 * 文字列から生成する
	 * @param string $src テンプレート文字列
	 * @param string $template_name 対象となるテンプレート名
	 * @return string
	 */
	public function execute($src,$template_name=null){
		$this->selected_template = $template_name;
		$src = $this->replace_ptag($this->html_reform($this->exec($this->parse($src))));
		return $src;
		
		
	}
	private function replace_ptag($src){
		return str_replace(array('__PHP_TAG_ESCAPE_START__','__PHP_TAG_ESCAPE_END__'),array('<?','?>'),$src);
	}
	private function replace_xtag($src){
		if(preg_match_all("/<\?(?!php[\s\n])[\w]+ .*?\?>/s",$src,$null)){
			foreach($null[0] as $value) $src = str_replace($value,'__PHP_TAG_ESCAPE_START__'.substr($value,2,-2).'__PHP_TAG_ESCAPE_END__',$src);
		}
		return $src;
	}
	/**
	 * rt:**タグをパースする
	 * @param string $src
	 * @return string
	 */
	public function parse_tags($src){
		return $this->rtif($this->rtloop($this->rtunit($this->rtpager($this->rtinvalid($this->html_form($this->html_list($src)))))));		
	}
	/**
	 * {$xxx}変数をパースする
	 * @param string $src
	 * @return string
	 */
	public function parse_vars($src){
		return str_replace(array_keys($this->statics),array_values($this->statics),$this->parse_print_variable($src));
	}
	private function parse($src){
		$src = preg_replace("/([\w])\->/","\\1__PHP_ARROW__",$src);
		$src = str_replace(array("\\\\","\\\"","\\'"),array('__ESC_DESC__','__ESC_DQ__','__ESC_SQ__'),$src);
		$src = $this->replace_xtag($src);
		/**
		 * 初期処理
		 * @param string $src
		 * @param self $this
		 */
		$this->call_module('init_template',$src,$this);
		$src = $this->rtcomment($this->rtblock($this->rttemplate($src),$this->filename()));
		/**
		 * 前処理
		 * @param string $src
		 * @param self $this
		 */
		$this->call_module('before_template',$src,$this);
		$src = $this->parse_tags($src);
		/**
		 * 後処理
		 * @param string $src
		 * @param self $this
		 */
		$this->call_module('after_template',$src,$this);
		$src = str_replace('__PHP_ARROW__','->',$src);
		$src = $this->parse_vars($src);
		$php = array(' ?>','<?php ','->');
		$str = array('PHP_TAG_END','PHP_TAG_START','PHP_ARROW');
		$src = str_replace($str,$php,$this->parse_url(str_replace($php,$str,$src),$this->media_url));
		$src = str_replace(array('__ESC_DQ__','__ESC_SQ__','__ESC_DESC__'),array("\\\"","\\'","\\\\"),$src);
		return $src;		
		
	}
	final private function parse_url($src,$base){
		if(substr($base,-1) !== '/') $base = $base.'/';
		$secure_base = ($this->secure) ? str_replace('http://','https://',$base) : null;
		if(preg_match_all("/<([^<\n]+?[\s])(src|href|background)[\s]*=[\s]*([\"\'])([^\\3\n]+?)\\3[^>]*?>/i",$src,$match)){
			foreach($match[2] as $k => $p){
				$t = null;
				if(strtolower($p) === 'href') list($t) = (preg_split("/[\s]/",strtolower($match[1][$k])));
				$src = $this->replace_parse_url($src,(($this->secure && $t !== 'a') ? $secure_base : $base),$match[0][$k],$match[4][$k]);
			}
		}
		if(preg_match_all("/[^:]:[\040]*url\(([^\n]+?)\)/",$src,$match)){
			if($this->secure) $base = $secure_base;
			foreach($match[1] as $key => $param) $src = $this->replace_parse_url($src,$base,$match[0][$key],$match[1][$key]);
		}
		return $src;
	}
	final private function replace_parse_url($src,$base,$dep,$rep){
		if(!preg_match("/(^[\w]+:\/\/)|(^PHP_TAG_START)|(^\{\\$)|(^\w+:)|(^[#\?])/",$rep)){
			$src = str_replace($dep,str_replace($rep,File::absolute($base,$rep),$dep),$src);
		}
		return $src;
	}
	final private function rttemplate($src){
		$values = array();
		$bool = false;
		while(Tag::setof($tag,$src,'rt:template')){
			$src = str_replace($tag->plain(),'',$src);
			$values[$tag->in_param('name')] = $tag->value();
			$src = str_replace($tag->plain(),'',$src);
			$bool = true;
		}
		if(!empty($this->selected_template)){
			if(!array_key_exists($this->selected_template,$values)) throw new LogicException('undef rt:template '.$this->selected_template);
			return $values[$this->selected_template];
		}
		return ($bool) ? implode($values) : $src;
		
	}
	final private function rtblock($src,$filename){
		if(strpos($src,'rt:block') !== false || strpos($src,'rt:extends') !== false){
			$blocks = $paths = array();
			while(Tag::setof($xml,$this->rtcomment($src),'rt:extends')){
				$bxml = Tag::anyhow($src);
				foreach($bxml->in('rt:block') as $block){
					if(strtolower($block->name()) == 'rt:block'){
						$name = $block->in_param('name');
						if(!empty($name) && !array_key_exists($name,$blocks)){
							$blocks[$name] = $block->value();
							$paths[$name] = $filename;
						}
					}
				}
				if($xml->is_param('href')){
					$src = $this->read_src($filename = File::absolute(dirname($filename),$xml->in_param('href')));
					$this->filename = $filename;
				}else{
					$src = File::read($this->filename());
				}
				$this->selected_template = $xml->in_param('name');
				$src = $this->rttemplate($this->replace_xtag($src));
			}
			if(empty($blocks)){
				$bxml = Tag::anyhow($src);
				foreach($bxml->in('rt:block') as $block) $src = str_replace($block->plain(),$block->value(),$src);
			}else{
				while(Tag::setof($xml,$src,'rt:block')){
					$xml = Tag::anyhow($src);
					foreach($xml->in('rt:block') as $block){
						$name = $block->in_param('name');
						$src = str_replace($block->plain(),(array_key_exists($name,$blocks) ? $blocks[$name] : $block->value()),$src);
					}
				}
			}
		}
		return $src;
		
		
		
	}
	final private function rtcomment($src){
		while(Tag::setof($tag,$src,'rt:comment')) $src = str_replace($tag->plain(),'',$src);
		return $src;
	}
	final private function rtunit($src){
		if(strpos($src,'rt:unit') !== false){
			while(Tag::setof($tag,$src,'rt:unit')){
				$uniq = uniqid('');
				$param = $tag->in_param('param');
				$var = '$'.$tag->in_param('var','_var_'.$uniq);
				$offset = $tag->in_param('offset',1);
				$total = $tag->in_param('total','_total_'.$uniq);
				$cols = ($tag->is_param('cols')) ? (ctype_digit($tag->in_param('cols')) ? $tag->in_param('cols') : $this->variable_string($this->parse_plain_variable($tag->in_param('cols')))) : 1;
				$rows = ($tag->is_param('rows')) ? (ctype_digit($tag->in_param('rows')) ? $tag->in_param('rows') : $this->variable_string($this->parse_plain_variable($tag->in_param('rows')))) : 0;
				$value = $tag->value();
				$cols_count = '$_ucount_'.$uniq;
				$cols_total = '$'.$tag->in_param('cols_total','_cols_total_'.$uniq);
				$rows_count = '$'.$tag->in_param('counter','_counter_'.$uniq);
				$rows_total = '$'.$tag->in_param('rows_total','_rows_total_'.$uniq);
				$ucols = '$_ucols_'.$uniq;
				$urows = '$_urows_'.$uniq;
				$ulimit = '$_ulimit_'.$uniq;
				$ufirst = '$_ufirst_'.$uniq;				
				$ufirstnm = '_ufirstnm_'.$uniq;
				$ukey = '_ukey_'.$uniq;
				$uvar = '_uvar_'.$uniq;
				$src = str_replace(
							$tag->plain(),
							sprintf('<?php %s=%s; %s=%s; %s=%s=1; %s=null; %s=%s*%s; %s=array(); ?>'
									.'<rt:loop param="%s" var="%s" key="%s" total="%s" offset="%s" first="%s">'
										.'<?php if(%s <= %s){ %s[$%s]=$%s; } ?>'
										.'<rt:first><?php %s=$%s; ?></rt:first>'
										.'<rt:last><?php %s=%s; ?></rt:last>'
										.'<?php if(%s===%s){ ?>'
											.'<?php if(isset(%s)){ $%s=""; } ?>'
											.'<?php %s=sizeof(%s); ?>'
											.'<?php %s=ceil($%s/%s); ?>'
											.'%s'
											.'<?php %s=array(); %s=null; %s=1; %s++; ?>'
										.'<?php }else{ %s++; } ?>'
									.'</rt:loop>'
									,$ucols,$cols,$urows,$rows,$cols_count,$rows_count,$ufirst,$ulimit,$ucols,$urows,$var
									,$param,$uvar,$ukey,$total,$offset,$ufirstnm
										,$cols_count,$ucols,$var,$ukey,$uvar
										,$ufirst,$ufirstnm
										,$cols_count,$ucols
										,$cols_count,$ucols
											,$ufirst,$ufirstnm
											,$cols_total,$var
											,$rows_total,$total,$ucols
											,$value
											,$var,$ufirst,$cols_count,$rows_count
										,$cols_count
							)
							.($tag->is_param('rows') ? 
								sprintf('<?php for(;%s<=%s;%s++){ %s=array(); ?>%s<?php } ?>',$rows_count,$rows,$rows_count,$var,$value) : ''
							)
							,$src
						);
			}
		}
		return $src;
		
		
	}
	final private function rtloop($src){
		if(strpos($src,'rt:loop') !== false){
			while(Tag::setof($tag,$src,'rt:loop')){
				$param = ($tag->is_param('param')) ? $this->variable_string($this->parse_plain_variable($tag->in_param('param'))) : null;
				$offset = ($tag->is_param('offset')) ? (ctype_digit($tag->in_param('offset')) ? $tag->in_param('offset') : $this->variable_string($this->parse_plain_variable($tag->in_param('offset')))) : 1;
				$limit = ($tag->is_param('limit')) ? (ctype_digit($tag->in_param('limit')) ? $tag->in_param('limit') : $this->variable_string($this->parse_plain_variable($tag->in_param('limit')))) : 0;
				if(empty($param) && $tag->is_param('range')){
					list($range_start,$range_end) = explode(',',$tag->in_param('range'),2);
					$range = ($tag->is_param('range_step')) ? sprintf('range(%d,%d,%d)',$range_start,$range_end,$tag->in_param('range_step')) :
																sprintf('range("%s","%s")',$range_start,$range_end);
					$param = sprintf('array_combine(%s,%s)',$range,$range);
				}
				$is_fill = false;
				$uniq = uniqid('');
				$even = $tag->in_param('even_value','even');
				$odd = $tag->in_param('odd_value','odd');
				$evenodd = '$'.$tag->in_param('evenodd','loop_evenodd');
				$first_value = $tag->in_param('first_value','first');
				$first = '$'.$tag->in_param('first','_first_'.$uniq);
				$first_flg = '$__isfirst__'.$uniq;
				$last_value = $tag->in_param('last_value','last');
				$last = '$'.$tag->in_param('last','_last_'.$uniq);
				$last_flg = '$__islast__'.$uniq;
				$shortfall = '$'.$tag->in_param('shortfall','_DEFI_'.$uniq);
				$var = '$'.$tag->in_param('var','_var_'.$uniq);
				$key = '$'.$tag->in_param('key','_key_'.$uniq);
				$total = '$'.$tag->in_param('total','_total_'.$uniq);
				$vtotal = '$__vtotal__'.$uniq;				
				$counter = '$'.$tag->in_param('counter','_counter_'.$uniq);
				$loop_counter = '$'.$tag->in_param('loop_counter','_loop_counter_'.$uniq);
				$reverse = (strtolower($tag->in_param('reverse') === 'true'));
				$varname = '$_'.$uniq;
				$countname = '$__count__'.$uniq;
				$lcountname = '$__vcount__'.$uniq;
				$offsetname	= '$__offset__'.$uniq;
				$limitname = '$__limit__'.$uniq;
				$value = $tag->value();
				$empty_value = null;
				while(Tag::setof($subtag,$value,'rt:loop')){
					$value = $this->rtloop($value);
				}
				while(Tag::setof($subtag,$value,'rt:first')){
					$value = str_replace($subtag->plain(),sprintf('<?php if(isset(%s)%s){ ?>%s<?php } ?>',$first
					,(($subtag->in_param('last') === 'false') ? sprintf(' && (%s !== 1) ',$total) : '')
					,preg_replace("/<rt\:else[\s]*\/>/i","<?php }else{ ?>",$this->rtloop($subtag->value()))),$value);
				}
				while(Tag::setof($subtag,$value,'rt:middle')){
					$value = str_replace($subtag->plain(),sprintf('<?php if(!isset(%s) && !isset(%s)){ ?>%s<?php } ?>',$first,$last
					,preg_replace("/<rt\:else[\s]*\/>/i","<?php }else{ ?>",$this->rtloop($subtag->value()))),$value);
				}
				while(Tag::setof($subtag,$value,'rt:last')){
					$value = str_replace($subtag->plain(),sprintf('<?php if(isset(%s)%s){ ?>%s<?php } ?>',$last
					,(($subtag->in_param('first') === 'false') ? sprintf(' && (%s !== 1) ',$vtotal) : '')
					,preg_replace("/<rt\:else[\s]*\/>/i","<?php }else{ ?>",$this->rtloop($subtag->value()))),$value);
				}
				while(Tag::setof($subtag,$value,'rt:fill')){
					$is_fill = true;
					$value = str_replace($subtag->plain(),sprintf('<?php if(%s > %s){ ?>%s<?php } ?>',$lcountname,$total
					,preg_replace("/<rt\:else[\s]*\/>/i","<?php }else{ ?>",$this->rtloop($subtag->value()))),$value);
				}				
				$value = $this->rtif($value);
				if(preg_match("/^(.+)<rt\:else[\s]*\/>(.+)$/ims",$value,$match)){
					list(,$value,$empty_value) = $match;
				}
				$src = str_replace(
							$tag->plain(),
							sprintf("<?php try{ ?>"
									."<?php "
										." %s=%s;"
										." if(is_array(%s)){"
											." if(%s){ krsort(%s); }"
											." %s=%s=sizeof(%s); %s=%s=1; %s=%s; %s=((%s>0) ? (%s + %s) : 0); "
											." %s=%s=false; %s=0; %s=%s=null;"
											." if(%s){ for(\$i=0;\$i<(%s+%s-%s);\$i++){ %s[] = null; } %s=sizeof(%s); }"
											." foreach(%s as %s => %s){"
												." if(%s <= %s){"
													." if(!%s){ %s=true; %s='%s'; }"
													." if((%s > 0 && (%s+1) == %s) || %s===%s){ %s=true; %s='%s'; %s=(%s-%s+1) * -1;}"													
													." %s=((%s %% 2) === 0) ? '%s' : '%s';"
													." %s=%s; %s=%s;"
													." ?>%s<?php "
													." %s=%s=null;"
													." %s++;"
												." }"
												." %s++;"
												." if(%s > 0 && %s >= %s){ break; }"
											." }"
											." if(!%s){ ?>%s<?php } "
											." unset(%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s);"
										." }"
									." ?>"
									."<?php }catch(Exception \$e){ if(!isset(\$_nes_)){print('".self::$exception_str."');} } ?>"
									,$varname,$param
									,$varname
										,(($reverse) ? 'true' : 'false'),$varname
										,$vtotal,$total,$varname,$countname,$lcountname,$offsetname,$offset,$limitname,$limit,$offset,$limit
										,$first_flg,$last_flg,$shortfall,$first,$last
										,($is_fill ? 'true' : 'false'),$offsetname,$limitname,$total,$varname,$vtotal,$varname
										,$varname,$key,$var
											,$offsetname,$lcountname
											
												,$first_flg,$first_flg,$first,str_replace("'","\\'",$first_value)
												,$limitname,$lcountname,$limitname,$lcountname,$vtotal,$last_flg,$last,str_replace("'","\\'",$last_value),$shortfall,$lcountname,$limitname
												,$evenodd,$countname,$even,$odd
												,$counter,$countname,$loop_counter,$lcountname
												,$value
												,$first,$last
												,$countname
											,$lcountname
											,$limitname,$lcountname,$limitname
									,$first_flg,$empty_value
									,$var,$counter,$key,$countname,$lcountname,$offsetname,$limitname,$varname,$first,$first_flg,$last,$last_flg
							)
							,$src
						);
			}
		}
		return $src;
		
		
		
		
		
		
		
		
		
		
		
		
		
		
		
		
		
		
		
	}
	final private function rtif($src){
		if(strpos($src,'rt:if') !== false){
			while(Tag::setof($tag,$src,'rt:if')){
				if(!$tag->is_param('param')) throw new LogicException('if');
				$arg1 = $this->variable_string($this->parse_plain_variable($tag->in_param('param')));
				if($tag->is_param('value')){
					$arg2 = $this->parse_plain_variable($tag->in_param('value'));
					if($arg2 == 'true' || $arg2 == 'false' || ctype_digit(Text::str($arg2))){
						$cond = sprintf('<?php if(%s === %s || %s === "%s"){ ?>',$arg1,$arg2,$arg1,$arg2);
					}else{
						if($arg2 === '' || $arg2[0] != '$') $arg2 = '"'.$arg2.'"';
						$cond = sprintf('<?php if(%s === %s){ ?>',$arg1,$arg2);
					}
				}else{
					$uniq = uniqid('$I');
					$cond = sprintf('<?php try{ %s=%s; }catch(\\Exception $e){ %s=null; } ?>',$uniq,$arg1,$uniq)
								.sprintf('<?php if(%s !== null && %s !== false && ( (!is_string(%s) && !is_array(%s)) || (is_string(%s) && %s !== "") || (is_array(%s) && !empty(%s)) ) ){ ?>',$uniq,$uniq,$uniq,$uniq,$uniq,$uniq,$uniq,$uniq);
				}
				$src = str_replace(
							$tag->plain()
							,'<?php try{ ?>'.$cond
								.preg_replace("/<rt\:else[\s]*\/>/i","<?php }else{ ?>",$tag->value())
							."<?php } ?>"."<?php }catch(Exception \$e){ if(!isset(\$_nes_)){print('".self::$exception_str."');} } ?>"
							,$src
						);
			}
		}
		return $src;
		
	}
	final private function rtpager($src){
		if(strpos($src,'rt:pager') !== false){
			while(Tag::setof($tag,$src,'rt:pager')){
				$param = $this->variable_string($this->parse_plain_variable($tag->in_param('param','paginator')));
				$func = sprintf('<?php try{ ?><?php if(%s instanceof Paginator){ ?>',$param);
				if($tag->is_value()){
					$func .= $tag->value();
				}else{
					$uniq = uniqid('');
					$name = '$__pager__'.$uniq;
					$counter_var = '$__counter__'.$uniq;
					$tagtype = $tag->in_param('tag');
					$href = $tag->in_param('href','?');
					$stag = (empty($tagtype)) ? '' : '<'.$tagtype.' class="%s">';
					$etag = (empty($tagtype)) ? '' : '</'.$tagtype.'>';
					$navi = array_change_key_case(array_flip(explode(',',$tag->in_param('navi','prev,next,first,last,counter'))));
					$counter = $tag->in_param('counter',50);
					$total = '$__pagertotal__'.$uniq;
					if(isset($navi['prev'])) $func .= sprintf('<?php if(%s->is_prev()){ ?>%s<a href="%s{%s.query_prev()}">%s</a>%s<?php } ?>',$param,sprintf($stag,'prev'),$href,$param,Gettext::trans('prev'),$etag);
					if(isset($navi['first'])) $func .= sprintf('<?php if(!%s->is_dynamic() && %s->is_first(%d)){ ?>%s<a href="%s{%s.query(%s.first())}">{%s.first()}</a>%s%s...%s<?php } ?>',$param,$param,$counter,sprintf($stag,'first'),$href,$param,$param,$param,$etag,sprintf($stag,'first_gt'),$etag);
					if(isset($navi['counter'])){
						$func .= sprintf('<?php if(!%s->is_dynamic()){ ?>',$param);
						$func .= sprintf('<?php %s = %s; if(!empty(%s)){ ?>',$total,$param,$total);
						$func .= sprintf('<?php for(%s=%s->which_first(%d);%s<=%s->which_last(%d);%s++){ ?>',$counter_var,$param,$counter,$counter_var,$param,$counter,$counter_var);
						$func .= sprintf('%s<?php if(%s == %s->current()){ ?><strong>{%s}</strong><?php }else{ ?><a href="%s{%s.query(%s)}">{%s}</a><?php } ?>%s',sprintf($stag,'count'),$counter_var,$param,$counter_var,$href,$param,$counter_var,$counter_var,$etag);
						$func .= '<?php } ?>';
						$func .= '<?php } ?>';
						$func .= '<?php } ?>';
					}
					if(isset($navi['last'])) $func .= sprintf('<?php if(!%s->is_dynamic() && %s->is_last(%d)){ ?>%s...%s%s<a href="%s{%s.query(%s.last())}">{%s.last()}</a>%s<?php } ?>',$param,$param,$counter,sprintf($stag,'last_lt'),$etag,sprintf($stag,'last'),$href,$param,$param,$param,$etag);
					if(isset($navi['next'])) $func .= sprintf('<?php if(%s->is_next()){ ?>%s<a href="%s{%s.query_next()}">%s</a>%s<?php } ?>',$param,sprintf($stag,'next'),$href,$param,Gettext::trans('next'),$etag);
				}
				$func .= "<?php } ?><?php }catch(Exception \$e){ if(!isset(\$_nes_)){print('".self::$exception_str."');} } ?>";
				$src = str_replace($tag->plain(),$func,$src);
			}
		}
		return $this->rtloop($src);
		
	}
	final private function rtinvalid($src){
		if(strpos($src,'rt:invalid') !== false){
			while(Tag::setof($tag,$src,'rt:invalid')){
				$param = $this->parse_plain_variable($tag->in_param('param'));
				$var = $this->parse_plain_variable($tag->in_param('var','rtinvalid_var'.uniqid('')));
				$messages = $this->parse_plain_variable($tag->in_param('messages','rtinvalid_mes'.uniqid('')));
				if(!isset($param[0]) || $param[0] !== '$') $param = '"'.$param.'"';
				$value = $tag->value();
				$tagtype = $tag->in_param('tag');
				$stag = (empty($tagtype)) ? '' : '<'.$tagtype.' class="%s">';
				$etag = (empty($tagtype)) ? '' : '</'.$tagtype.'>';
				if(empty($value)){
					$varnm = 'rtinvalid_varnm'.uniqid('');
					$value = sprintf("<rt:loop param=\"%s\" var=\"%s\">\n"
										."%s{\$%s}%s"
									."</rt:loop>\n",$messages,$varnm,sprintf($stag,'exception'),$varnm,$etag);
				}
				$src = str_replace(
							$tag->plain(),
							sprintf("<?php if(Exceptions::has(%s)){ ?>"
										."<?php \$%s = Exceptions::gets(%s); ?>"
										."<?php \$%s = Exceptions::messages(%s); ?>"
										."%s"
									."<?php } ?>",$param,$var,$param,$messages,$param,$value),
							$src);
			}
		}
		return $src;
	}
	final private function parse_print_variable($src){
		foreach($this->match_variable($src) as $variable){
			$name = $this->parse_plain_variable($variable);
			$value = "<?php try{ ?>"."<?php @print(".$name."); ?>"."<?php }catch(Exception \$e){ if(!isset(\$_nes_)){print('".self::$exception_str."');} } ?>";
			$src = str_replace(array($variable."\n",$variable),array($value."\n\n",$value),$src);
		}
		return $src;
	}
	final private function match_variable($src){
		$hash = array();
		while(preg_match("/({(\\$[\$\w][^\t]*)})/s",$src,$vars,PREG_OFFSET_CAPTURE)){
			list($value,$pos) = $vars[1];
			if($value == "") break;
			if(substr_count($value,'}') > 1){
				for($i=0,$start=0,$end=0;$i<strlen($value);$i++){
					if($value[$i] == '{'){
						$start++;
					}else if($value[$i] == '}'){
						if($start == ++$end){
							$value = substr($value,0,$i+1);
							break;
						}
					}
				}
			}
			$length	= strlen($value);
			$src = substr($src,$pos + $length);
			$hash[sprintf('%03d_%s',$length,$value)] = $value;
		}
		krsort($hash,SORT_STRING);
		return $hash;
	}
	final private function parse_plain_variable($src){
		while(true){
			$array = $this->match_variable($src);
			if(sizeof($array) <= 0)	break;
			foreach($array as $v){
				$tmp = $v;
				if(preg_match_all("/([\"\'])([^\\1]+?)\\1/",$v,$match)){
					foreach($match[2] as $value) $tmp = str_replace($value,str_replace('.','__PERIOD__',$value),$tmp);
				}
				$src = str_replace($v,str_replace('.','->',substr($tmp,1,-1)),$src);
			}
		}
		return str_replace('[]','',str_replace('__PERIOD__','.',$src));
	}
	final private function variable_string($src){
		return (empty($src) || isset($src[0]) && $src[0] == '$') ? $src : '$'.$src;
	}
	final private function html_reform($src){
		$bool = false;
		foreach(Tag::anyhow($src)->in('form') as $obj){
			if(($obj->in_param('rt:aref') === 'true')){
				$form = $obj->value();
				foreach($obj->in(array('input','select')) as $tag){
					if($tag->is_param('name') || $tag->is_param('id')){
						$name = $this->parse_plain_variable($this->form_variable_name($tag->in_param('name',$tag->in_param('id'))));
						switch(strtolower($tag->name())){
							case 'input':
								switch(strtolower($tag->in_param('type'))){
									case 'radio':
									case 'checkbox':
										$tag->attr($this->check_selected($name,sprintf("'%s'",$this->parse_plain_variable($tag->in_param('value','true'))),'checked'));
										$form = str_replace($tag->plain(),$tag->get(),$form);
										$bool = true;
								}
								break;
							case 'select':
								$select = $tag->value();
								foreach($tag->in('option') as $option){
									$option->attr($this->check_selected($name,sprintf("'%s'",$this->parse_plain_variable($option->in_param('value'))),'selected'));
									$select = str_replace($option->plain(),$option->get(),$select);
								}
								$tag->value($select);
								$form = str_replace($tag->plain(),$tag->get(),$form);
								$bool = true;
						}
					}
				}
				$obj->rm_param('rt:aref');
				$obj->value($form);
				$src = str_replace($obj->plain(),$obj->get(),$src);
			}
		}
		return ($bool) ? $this->exec($src) : $src;
	}
	final private function html_form($src){
		$tag = Tag::anyhow($src);
		foreach($tag->in('form') as $obj){
			if($this->is_reference($obj)){
				foreach($obj->in(array('input','select','textarea')) as $tag){
					if(!$tag->is_param('rt:ref') && ($tag->is_param('name') || $tag->is_param('id'))){
						switch(strtolower($tag->in_param('type','text'))){
							case 'button':
							case 'submit':
								break;
							case 'file':
								$obj->param('enctype','multipart/form-data');
								$obj->param('method','post');
								break;
							default:
								$tag->param('rt:ref','true');
								$obj->value(str_replace($tag->plain(),$tag->get(),$obj->value()));
						}
					}
				}
			}
			$src = str_replace($obj->plain(),$obj->get(),$src);
		}
		return $this->html_input($src);
	}
	final private function no_exception_str($value){
		return '<?php $_nes_=1; ?>'.$value.'<?php $_nes_=null; ?>';
	}
	final private function html_input($src){
		$tag = Tag::anyhow($src);
		foreach($tag->in(array('input','textarea','select')) as $obj){
			if('' != ($originalName = $obj->in_param('name',$obj->in_param('id','')))){
				$type = strtolower($obj->in_param('type','text'));
				$name = $this->parse_plain_variable($this->form_variable_name($originalName));
				$lname = strtolower($obj->name());
				$change = false;
				$uid = uniqid();
				if(substr($originalName,-2) !== '[]'){
					if($type == 'checkbox'){
						if($obj->in_param('rt:multiple','true') === 'true') $obj->param('name',$originalName.'[]');
						$obj->rm_param('rt:multiple');
						$change = true;
					}else if($obj->is_attr('multiple') || $obj->in_param('multiple') === 'multiple'){
						$obj->param('name',$originalName.'[]');
						$obj->rm_attr('multiple');
						$obj->param('multiple','multiple');
						$change = true;
					}
				}else if($obj->in_param('name') !== $originalName){
					$obj->param('name',$originalName);
					$change = true;
				}
				if($obj->is_param('rt:param') || $obj->is_param('rt:range')){
					switch($lname){
						case 'select':
							$value = sprintf('<rt:loop param="%s" var="%s" counter="%s" key="%s" offset="%s" limit="%s" reverse="%s" evenodd="%s" even_value="%s" odd_value="%s" range="%s" range_step="%s">'
											.'<option value="{$_t_.primary($%s,$%s)}">{$%s}</option>'
											.'</rt:loop>'
											,$obj->in_param('rt:param'),$obj->in_param('rt:var','loop_var'.$uid),$obj->in_param('rt:counter','loop_counter'.$uid)
											,$obj->in_param('rt:key','loop_key'.$uid),$obj->in_param('rt:offset','0'),$obj->in_param('rt:limit','0')
											,$obj->in_param('rt:reverse','false')
											,$obj->in_param('rt:evenodd','loop_evenodd'.$uid),$obj->in_param('rt:even_value','even'),$obj->in_param('rt:odd_value','odd')
											,$obj->in_param('rt:range'),$obj->in_param('rt:range_step',1)
											,$obj->in_param('rt:var','loop_var'.$uid),$obj->in_param('rt:key','loop_key'.$uid),$obj->in_param('rt:var','loop_var'.$uid)
							);
							$obj->value($this->rtloop($value));
							if($obj->is_param('rt:null')) $obj->value('<option value="">'.$obj->in_param('rt:null').'</option>'.$obj->value());
					}
					$obj->rm_param('rt:param','rt:key','rt:var','rt:counter','rt:offset','rt:limit','rt:null','rt:evenodd'
									,'rt:range','rt:range_step','rt:even_value','rt:odd_value');
					$change = true;
				}
				if($this->is_reference($obj)){
					switch($lname){
						case 'textarea':
							$obj->value($this->no_exception_str(sprintf('{$_t_.htmlencode(%s)}',((preg_match("/^{\$(.+)}$/",$originalName,$match)) ? '{$$'.$match[1].'}' : '{$'.$originalName.'}'))));
							break;
						case 'select':
							$select = $obj->value();
							foreach($obj->in('option') as $option){
								$value = $this->parse_plain_variable($option->in_param('value'));
								if(empty($value) || $value[0] != '$') $value = sprintf("'%s'",$value);
								$option->rm_attr('selected');
								$option->rm_param('selected');
								$option->attr($this->check_selected($name,$value,'selected'));
								$select = str_replace($option->plain(),$option->get(),$select);
							}
							$obj->value($select);
							break;
						case 'input':
							switch($type){
								case 'checkbox':
								case 'radio':
									$value = $this->parse_plain_variable($obj->in_param('value','true'));
									$value = (substr($value,0,1) != '$') ? sprintf("'%s'",$value) : $value;
									$obj->rm_attr('checked');
									$obj->rm_param('checked');
									$obj->attr($this->check_selected($name,$value,'checked'));
									break;
								case 'text':
								case 'hidden':
								case 'password':
								case 'search':
								case 'url':
								case 'email':
								case 'tel':
								case 'datetime':
								case 'date':
								case 'month':
								case 'week':
								case 'time':
								case 'datetime-local':
								case 'number':
								case 'range':
								case 'color':
									$obj->param('value',$this->no_exception_str(sprintf('{$_t_.htmlencode(%s)}',
																((preg_match("/^\{\$(.+)\}$/",$originalName,$match)) ?
																	'{$$'.$match[1].'}' :
																	'{$'.$originalName.'}'))));
									break;
							}
							break;
					}
					$change = true;
				}else if($obj->is_param('rt:ref')){
					$obj->rm_param('rt:ref');
					$change = true;
				}
				if($change){
					switch($lname){
						case 'textarea':
						case 'select':
							$obj->close_empty(false);
					}
					$src = str_replace($obj->plain(),$obj->get(),$src);
				}
			}
		}
		return $src;
		
		
		
		
		
		
		
	}
	final private function check_selected($name,$value,$selected){
		return sprintf('<?php if('
					.'isset(%s) && (%s === %s '
										.' || (ctype_digit(Text::str(%s)) && %s == %s)'
										.' || ((%s == "true" || %s == "false") ? (%s === (%s == "true")) : false)'
										.' || in_array(%s,((is_array(%s)) ? %s : (is_null(%s) ? array() : array(%s))),true) '
									.') '
					.'){print(" %s=\"%s\"");} ?>'
					,$name,$name,$value
					,$name,$name,$value
					,$value,$value,$name,$value
					,$value,$name,$name,$name,$name
					,$selected,$selected
				);
	}
	final private function html_list($src){
		if(preg_match_all('/<(table|ul|ol)\s[^>]*rt\:/i',$src,$m,PREG_OFFSET_CAPTURE)){
			$tags = array();
			foreach($m[1] as $k => $v){
				if(Tag::setof($tag,substr($src,$v[1]-1),$v[0])) $tags[] = $tag;
			}
			foreach($tags as $obj){
				$name = strtolower($obj->name());
				$param = $obj->in_param('rt:param');
				$null = strtolower($obj->in_param('rt:null'));
				$value = sprintf('<rt:loop param="%s" var="%s" counter="%s" '
									.'key="%s" offset="%s" limit="%s" '
									.'reverse="%s" '
									.'evenodd="%s" even_value="%s" odd_value="%s" '
									.'range="%s" range_step="%s" '
									.'shortfall="%s">'
								,$param,$obj->in_param('rt:var','loop_var'),$obj->in_param('rt:counter','loop_counter')
								,$obj->in_param('rt:key','loop_key'),$obj->in_param('rt:offset','0'),$obj->in_param('rt:limit','0')
								,$obj->in_param('rt:reverse','false')
								,$obj->in_param('rt:evenodd','loop_evenodd'),$obj->in_param('rt:even_value','even'),$obj->in_param('rt:odd_value','odd')
								,$obj->in_param('rt:range'),$obj->in_param('rt:range_step',1)
								,$tag->in_param('rt:shortfall','_DEFI_'.uniqid())
							);
				$rawvalue = $obj->value();
				if($name == 'table' && Tag::setof($t,$rawvalue,'tbody')){
					$t->value($value.$this->table_tr_even_odd($t->value(),(($name == 'table') ? 'tr' : 'li'),$obj->in_param('rt:evenodd','loop_evenodd')).'</rt:loop>');
					$value = str_replace($t->plain(),$t->get(),$rawvalue);
				}else{
					$value = $value.$this->table_tr_even_odd($rawvalue,(($name == 'table') ? 'tr' : 'li'),$obj->in_param('rt:evenodd','loop_evenodd')).'</rt:loop>';
				}
				$obj->value($this->html_list($value));
				$obj->rm_param('rt:param','rt:key','rt:var','rt:counter','rt:offset','rt:limit','rt:null','rt:evenodd','rt:range'
								,'rt:range_step','rt:even_value','rt:odd_value','rt:shortfall');
				$src = str_replace($obj->plain(),
						($null === 'true') ? $this->rtif(sprintf('<rt:if param="%s">',$param).$obj->get().'</rt:if>') : $obj->get(),
						$src);
			}
		}
		return $src;
		
		
		
		
		
		
		
		
		
		
		
		
		
		
		
		
	}
	final private function table_tr_even_odd($src,$name,$even_odd){
		$tag = Tag::anyhow($src);
		foreach($tag->in($name) as $tr){
			$class = ' '.$tr->in_param('class').' ';
			if(preg_match('/[\s](even|odd)[\s]/',$class,$match)){
				$tr->param('class',trim(str_replace($match[0],' {$'.$even_odd.'} ',$class)));
				$src = str_replace($tr->plain(),$tr->get(),$src);				
			}
		}
		return $src;
	}
	final private function form_variable_name($name){
		return (strpos($name,'[') && preg_match("/^(.+)\[([^\"\']+)\]$/",$name,$match)) ?
			'{$'.$match[1].'["'.$match[2].'"]'.'}' : '{$'.$name.'}';
	}
	final private function is_reference(&$tag){
		$bool = ($tag->in_param('rt:ref') === 'true');
		$tag->rm_param('rt:ref');
		return $bool;
	}
	private function exec($src){
		/**
		 * 実行前処理
		 * @param string $src
		 * @param self $this
		 */
		$this->call_module('before_exec_template',$src,$this);
		$this->vars('_t_',new Templf());
		$__template_eval_src__ = $src;
		ob_start();
			if(is_array($this->vars) && !empty($this->vars)) extract($this->vars);
			eval('?>'.$__template_eval_src__);
			unset($__template_eval_src__);
		$src = ob_get_clean();
		/**
		 * 実行後処理
		 * @param string $src
		 * @param self $this
		 */
		$this->call_module('after_exec_template',$src,$this);
		return $src;
	}
		
}
/**
 * テンプレートで利用するフォーマットツール
 *
 * @author Kazutaka Tokushima
 * @license New BSD License
 */
class Templf{
	private $counter = array();
	private $flow;
	public function __construct($flow=null){
		if($flow instanceof Flow) $this->flow = $flow;
	}
	/**
	 * handlerのマップ名を呼び出しているURLを生成する
	 * 引数を与える事も可能
	 * @param string $name マップ名
	 * @return string
	 */
	public function map_url($name){
		if($this->flow instanceof Flow){
			$args = func_get_args();
			return call_user_func_array(array($this->flow,'map_url'),$args);
		}
		return null;
	}
	/**
	 * handlerでpackageを呼び出してる場合にメソッド名でURLを生成する
	 * 引数を与える事も可能
	 * @param string $name メソッド名
	 * @return string
	 */
	public function package_method_url($name){
		if($this->flow instanceof Flow){
			$args = func_get_args();
			return call_user_func_array(array($this->flow,'package_method_url'),$args);
		}
		return null;
	}
	/**
	 * マッチしたパターン（名）を返す
	 * @return string
	 */
	public function match_pattern(){
		return ($this->flow instanceof Flow) ? ($this->flow->is_name() ?  $this->flow->name() : $this->flow->pattern()) : null;
	}
	/**
	 * マッチしたパターンと$patternが同じなら$trueを、違うなら$falseを返す
	 * @param string $pattern 比較する文字列
	 * @param string $true 一致した場合に返す文字列
	 * @param string $false 一致しなかった場合に返す文字列
	 * @return string
	 */
	public function match_pattern_switch($pattern,$true='on',$false=''){
		return ($this->match_pattern() == $pattern) ? $true : $false;
	}
	/**
	 * 真偽値により$trueまたは$falseを返す
	 * @param boolean $cond 真偽値
	 * @param string $true 真の場合に返す文字列
	 * @param string $false 偽の場合に返す文字列
	 * @return string
	 */
	public function cond_switch($cond,$true='on',$false=''){
		return ($cond === true) ? $true : $false;
		
	}
	/**
	 * リクエストされたURLを返す
	 * @return string
	 */
	public function request_url($query=true){
		return ($this->flow instanceof Flow) ? $this->flow->request_url($query) : null;
	}
	/**
	 * refererを返す
	 * @return string
	 */
	public function referer(){
		return Http::referer();
	}
	/**
	 * ログイン済みか
	 * @return boolean
	 */
	public function is_login(){
		return ($this->flow instanceof Flow) ? $this->flow->is_login() : false;
	}
	/**
	 * ログインユーザを返す
	 * @return mixed
	 */
	public function user(){
		return ($this->flow instanceof Flow) ? $this->flow->user() : null;
	}
	/**
	 * アプリケーションのメディアのURLを返す
	 * @param string $url ベースのURLに続く相対パス
	 * @return string
	 */
	public function media($url=null){
		return ($this->flow instanceof Flow) ? File::absolute($this->flow->media_url(),$url) : null;
	}	
	/**
	 * アプリケーションのURLを返す
	 * @param string $path ベースのURLに続く相対パス
	 * @return string
	 */
	public function url($path=null){
		return App::url($path);
	}
	/**
	 * アプリケーションのURLをhttpsで返す
	 * @param string $path ベースのURLに続く相対パス
	 * @return string
	 */
	public function surl($path=null){
		return App::surl($path);
	}
	/**
	 * query文字列に変換する
	 * Http::queryのエイリアス
	 *
	 * @param mixed $var query文字列化する変数
	 * @param string $name ベースとなる名前
	 * @param boolean $null nullの値を表現するか
	 * @return string
	 */
	public function query($var,$name=null,$null=true){
		return Http::query($var,$name,$null);
		
	}
	/**
	 * ゼロを桁数分前に埋める
	 * @param integer $int 対象の値
	 * @param $dig 0埋めする桁数
	 * @return string
	 */
	public function zerofill($int,$dig=0){
		return sprintf("%0".$dig."d",$int);
		
	}
	/**
	 * 数字を千位毎にグループ化してフォーマットする
	 * @param number $number 対象の値
	 * @param integer $dec 小数点以下の桁数
	 * @return string
	 */
	public function number_format($number,$dec=0){
		return number_format($number,$dec,".",",");
		
	}
	/**
	 * カウンタ
	 * @param string $name カウンタ名
	 * @param integer $increment 増加値
	 * @return integer
	 */
	public function counter($name,$increment=1){
		if(!isset($this->counter[$name])) $this->counter[$name] = 0;
		$this->counter[$name] = $this->counter[$name] + $increment;
		return $this->counter[$name];
		
	}
	/**
	 * カウント
	 * @param mixed $var 対象の値
	 * @return integer
	 */
	public function count($var){
		return sizeof($var);
		
	}
	/**
	 * フォーマットした日付を返す
	 * @param integer $value 時間
	 * @param string $format フォーマット文字列 ( http://jp2.php.net/manual/ja/function.date.php )
	 * @return string
	 */
	public function df($value,$format="Y/m/d H:i:s"){
		return date($format,$value);
		
	}
	/**
	 * HTMLエスケープされた文字列を返す
	 * @param string $value 対象の文字列
	 * @param integer $length 取得する文字列の最大長
	 * @param integer $lines 取得する文字列の最大行数
	 * @param string $postfix 文字列が最大長または最大行数を超えた場合に末尾に接続される文字列
	 * @return string
	 */
	public function html($value,$length=0,$lines=0,$postfix=null){
		$value = Tag::cdata(str_replace(array("\r\n","\r"),"\n",$value));
		if($length > 0){
			$det = mb_detect_encoding($value);
			$value = mb_substr($value,0,$length,$det).((mb_strlen($value,$det) > $length) ? $postfix : null);
		}
		if($lines > 0){
			$ln = array();
			$l = explode("\n",$value);
			for($i=0;$i<$lines;$i++) $ln[] = $l[$i];
			$value = implode("\n",$ln).((sizeof($l) > $lines) ? $postfix : null);
		}
		return nl2br(str_replace(array("<",">","'","\""),array("&lt;","&gt;","&#039;","&quot;"),$value));
		
	}
	/**
	 * 全てのタグを削除した文字列を返す
	 * @param string $value 対象の文字列
	 * @param integer $length 取得する文字列の最大長
	 * @param integer $lines 取得する文字列の最大行数
	 * @param string $postfix 文字列が最大長または最大行数を超えた場合に末尾に接続される文字列
	 * @return string
	 */
	public function text($value,$length=0,$lines=0,$postfix=null){
		return self::html(preg_replace("/<.+?>/","",$value),$length,$lines,$postfix);
		
	}
	/**
	 * brタグを改行コードに変換
	 * @param $src 変換する文字列
	 * @return string
	 */
	public function br2nl($src){
		foreach(Tag::anyhow($src)->in("br") as $t){
			$src = str_replace($t->get(),"\n",$src);
		}
		return $src;
		
	}
	/**
	 * 改行を削除(置換)する
	 *
	 * @param string $value 対象の文字列
	 * @param string $glue 置換後の文字列
	 * @return string
	 */
	public function one_liner($value,$glue=" "){
		return str_replace(array("\r\n","\r","\n","<br>","<br />"),$glue,$value);
		
	}
	/**
	 * 文字列を丸める
	 * @param string $str 対象の文字列
	 * @param integer $width 指定の幅
	 * @param string $postfix 文字列がまるめられた場合に末尾に接続される文字列
	 * @return string
	 */
	public function trim_width($str,$width,$postfix=''){
		$rtn = "";
		$cnt = 0;
		$len = mb_strlen($str);
		for($i=0;$i<$len;$i++){
			$c = mb_substr($str,$i,1);
			$cnt += (mb_strwidth($c) > 1) ? 2 : 1;
			if($width < $cnt) break;
			$rtn .= $c;
		}
		if($len > mb_strlen($rtn)) $rtn .= $postfix;
		return $rtn;
		
	}
	/**
	 * htmlエンコードをする
	 * @param string $value 対象の文字列
	 * @return string
	 */
	public function htmlencode($value){
		return Text::htmlencode(Tag::cdata($value));
		
	}
	/**
	 * htmlデコードをする
	 * @param string $value 対象の文字列
	 * @return string
	 */
	public function htmldecode($value){
		return Text::htmldecode(self::cdata($value));
		
	}
	/**
	 * CDATA形式から値を取り出す
	 * @param string $value 対象の文字列
	 * @return string
	 */
	public function cdata($value){
		return Tag::cdata($value);
		
	}
	/**
	 * 何もしない
	 * @param mixed $var そのまま返す値
	 * @return mixed
	 */
	public function noop($var){
		return $var;
		
	}
	/**
	 * primary型の値を返す
	 * @param Object $obj 対象のObject
	 * @param string $default デフォルト値
	 * @return string
	 */
	public function primary($obj,$default=null){
		if($obj instanceof Object){
			$primarys = array();
			foreach($obj->props() as $prop){
				if($obj->a($prop,'primary') === true) $primarys[] = $obj->{$prop}();
			}
			if(!empty($primarys)) return implode('_',$primarys);
		}
		return (isset($default) ? $default : Text::str($obj));
		
	}
	/**
	 * 文字列の構文ハイライト表示
	 * @param string $src 対象の文字列
	 * @return string
	 */
	public function highlight($src){
		return highlight_string($src,true);
		
	}
	/**
	 * 国際化文字列を返す
	 * @param string $message
	 * @return string
	 */
	public function trans($message){
		$args = func_get_args();
		return call_user_func_array(array('Gettext','trans'),$args);
		
	}	
}
/**
 * テキスト処理
 *
 * @author Kazutaka Tokushima
 * @license New BSD License
 */
class Text{
	static private $detect_order = "JIS,UTF-8,eucjp-win,sjis-win,EUC-JP,SJIS";
	/**
	 * ヒアドキュメントのようなテキストを生成する
	 * １行目のインデントに合わせてインデントが消去される
	 * @param string $text 対象の文字列
	 * @return string
	 */
	final public static function plain($text){
		if(!empty($text)){
			$lines = explode("\n",$text);
			if(sizeof($lines) > 2){
				if(trim($lines[0]) == '') array_shift($lines);
				if(trim($lines[sizeof($lines)-1]) == '') array_pop($lines);
				return preg_match("/^([\040\t]+)/",$lines[0],$match) ? preg_replace("/^".$match[1]."/m","",implode("\n",$lines)) : implode("\n",$lines);
			}
		}
		return $text;
		
		
		
	}
	/**
	 * Jsonに変換して取得
	 * @param mixed $variable  対象の値
	 * @return string
	 */
	static public function to_json($variable){
		
		
		switch(gettype($variable)){
			case "boolean": return ($variable) ? "true" : "false";
			case "integer": return intval(sprintf("%d",$variable));
			case "double": return floatval(sprintf("%f",$variable));
			case "array":
				$list = array();
				$i = 0;
				foreach(array_keys($variable) as $key){
					if($i !== $key){
						foreach($variable as $key => $value) $list[] = sprintf("\"%s\":%s",$key,self::to_json($value));
						return sprintf("{%s}",implode(",",$list));
					}
					$i++;
				}
				foreach($variable as $key => $value) $list[] = self::to_json($value);
				return sprintf("[%s]",implode(",",$list));
			case "object":
				$list = array();
				foreach((($variable instanceof Object) ? $variable->hash() : get_object_vars($variable)) as $key => $value){
					$list[] = sprintf("\"%s\":%s",$key,self::to_json($value));
				}
				return sprintf("{%s}",implode(",",$list));
			case "string":
				return sprintf("\"%s\"",addslashes($variable));
			default:
		}
		return "null";
	}
	/**
	 * JSONPとして出力
	 * @param mixied $var 対象の値
	 * @param string $callback コールバック名
	 * @param string $encode 文字エンコード
	 */
	static public function output_jsonp($var,$callback=null,$encode="UTF-8"){
		Log::disable_display();
		Http::send_header("Content-Type: application/json; charset=".$encode);
		print(str_replace(array("\r\n","\r","\n"),array("\\n"),(empty($callback) ? Text::to_json($var) : ($callback."(".Text::to_json($var).");"))));
		exit;
	}
	/**
	 * JsonからPHPの変数に変換
	 * @param string $json JSON文字列
	 * @return mixed
	 */
	static public function parse_json($json){
		if(!is_string($json)) return $json;
		$json = self::seem($json);
		if(!is_string($json)) return $json;
		$json = preg_replace("/[\s]*([,\:\{\}\[\]])[\s]*/","\\1",
						preg_replace("/[\"].*?[\"]/esm",'str_replace(array(",",":","{","}","[","]"),array("#B#","#C#","#D#","#E#","#F#","#G#"),"\\0")',
							str_replace(array('\\\\','\\"','$',"\\'"),array('#J#','#A#','#H#','#I#'),trim($json))));
		if(preg_match("/^\"([^\"]*?)\"$/",$json)){
			return str_replace('#J#','\\',stripcslashes(str_replace(array('#A#','#B#','#C#','#D#','#E#','#F#','#G#','#H#','#I#'),array('\\"',',',':','{','}','[',']','$',"\\'"),substr($json,1,-1))));
		}
		$start = substr($json,0,1);
		$end = substr($json,-1);
		if(($start == '[' && $end == ']') || ($start == '{' && $end == '}')){
			$hash = ($start == '{');
			$src = substr($json,1,-1);
			$list = array();
			while(strpos($src,'[') !== false){
				list($value,$start,$end) = self::block($src,'[',']');
				if($value === null) return null;
				$src = str_replace("[".$value."]",str_replace(array('[',']',','),array('#AA#','#AB','#AC'),'['.$value.']'),$src);
			}
			while(strpos($src,'{') !== false){
				list($value,$start,$end) = self::block($src,'{','}');
				if($value === null) return null;
				$src = str_replace('{'.$value.'}',str_replace(array('{','}',','),array('#BA#','#BB','#AC'),'{'.$value.'}'),$src);
			}
			foreach(explode(',',$src) as $value){
				if($value === '') return null;
				$value = str_replace(array('#AA#','#AB','#BA#','#BB','#AC'),array('[',']','{','}',','),$value);
				if($hash){
					$exp = explode(':',$value,2);
					if(sizeof($exp) != 2) throw new InvalidArgumentException('value error'); 
					list($key,$var) = $exp;
					$index = self::parse_json($key);
					if($index === null) $index = $key;
					$list[$index] = self::parse_json($var);
				}else{
					$list[] = self::parse_json($value);
				}
			}
			return $list;
		}
		return null;
		
		
		
		
	}
	/**
	 * 指定の開始文字／終了文字でくくられた部分を取得
	 * ブロックの中身,ブロックの開始位置,ブロックの終了位置を返す
	 * @param string $src 対象の文字列
	 * @param string $start ブロックの開始位置
	 * @param string $end ブロックの終了位置
	 * @return mixed[]
	 */
	static public function block($src,$start,$end){
		
		$eq = ($start == $end);
		if(preg_match_all("/".(($end == null || $eq) ? preg_quote($start,"/") : "(".preg_quote($start,"/").")|(".preg_quote($end,"/").")")."/sm",$src,$match,PREG_OFFSET_CAPTURE)){
			$count = 0;
			$pos = null;
			foreach($match[0] as $key => $value){
				if($value[0] == $start){
					$count++;
					if($pos === null) $pos = $value[1];
				}else if($pos !== null){
					$count--;
				}
				if($count == 0 || ($eq && ($count % 2 == 0))) return array(substr($src,$pos + strlen($start),($value[1] - $pos - strlen($start))),$pos,$value[1] + strlen($end));
			}
		}
		return array(null,0,strlen($src));
	}
	/**
	 * シンプルなyamlからphpに変換
	 * @param string $src YAML文字列
	 * @return mixed[]
	 */
	static public function parse_yaml($src){
		$src = preg_replace("/([\"\'])(.+)\\1/me",'str_replace(array("#",":"),array("__SHAPE__","__COLON__"),"\\0")',$src);
		$src = preg_replace("/^([\t]+)/me",'str_replace("\t"," ","\\1")',str_replace(array("\r\n","\r","\n"),"\n",$src));
		$src = preg_replace("/#.+$/m","",$src);
		$stream = array();
		if(!preg_match("/^[\040]*---(.*)$/m",$src)) $src = "---\n".$src;
		if(preg_match_all("/^[\040]*---(.*)$/m",$src,$match,PREG_OFFSET_CAPTURE | PREG_SET_ORDER)){
			$blocks = array();
			$size = sizeof($match) - 1;
			foreach($match as $c => $m){
				$obj = new stdClass();
				$obj->header = ltrim($m[1][0]);
				$obj->nodes = array();
				$node = array();
				$offset = $m[0][1] + mb_strlen($m[0][0]);
				$block = ($size == $c) ? mb_substr($src,$offset) :
											mb_substr($src,$offset,$match[$c+1][0][1] - $offset);
				foreach(explode("\n",$block) as $key => $line){
					if(!empty($line)){
						if($line[0] == " "){
							$node[] = $line;
						}else{
							self::yamlnodes($obj,$node);
							$result = self::yamlnode($node);
							$node = array($line);
						}
					}
				}
				self::yamlnodes($obj,$node);
				array_shift($obj->nodes);
				$stream[] = $obj;
			}
		}
		return $stream;
		
	}
	static private function yamlnodes(&$obj,$node){
		$result = self::yamlnode($node);
		if(is_array($result) && sizeof($result) == 1){
			if(isset($result[1])){
				$obj->nodes[] = array_shift($result);
			}else{
				$obj->nodes[key($result)] = current($result);
			}
		}else{
			$obj->nodes[] = $result;
		}
	}
	static private function yamlnode($node){
		$result = $child = $sequence = array();
		$line = $indent = 0;
		$isseq = $isblock = $onblock = $ischild = $onlabel = false;
		$name = "";
		$node[] = null;
		foreach($node as $value){
			if(!empty($value) && $value[0] == " ") $value = substr($value,$indent);
			switch($value[0]){
				case "[":
				case "{":
					return $value;
					break;
				case " ":
					if($indent == 0 && preg_match("/^[\040]+/",$value,$match)){
						$indent = strlen($match[0]) - 1;
						$value = substr($value,$indent);
					}
					if($isseq){
						if($onlabel){
							$result[$name] .= (($onblock) ? (($isblock) ? "\n" : " ") : "").ltrim(substr($value,1));
						}else{
							$sequence[$line] .= (($onblock) ? (($isblock) ? "\n" : " ") : "").ltrim(substr($value,1));
						}
						$onblock = true;
					}else{
						$child[] = substr($value,1);
					}
					break;
				case "-":
					$line++;
					$value = ltrim(substr($value,1));
					$isseq = $isblock = false;
					switch(trim($value)){
						case "": $ischild = true;
						case "|": $isblock = true; $onblock = false;
						case ">": $value = ""; $isseq = true;
					}
					$sequence[$line] = self::yamlunescape($value);
					break;
				default:
					if(empty($value) && !empty($sequence)){
						if($ischild){
							foreach($sequence as $key => $seq) $sequence[$key] = self::yamlnode(explode("\n",$seq));
							return $sequence;
						}
						return (sizeof($sequence) == 1) ? $sequence[1] : array_merge($sequence);
					}else if($name != "" && !empty($child)){
						$result[$name] = self::yamlnode($child);
					}
					$onlabel = false;
					if(substr(rtrim($value),-1) == ":"){
						$name = ltrim(self::yamlunescape(substr(trim($value),0,-1)));
						$result[$name] = null;
					}else if(strpos($value,":") !== false){
						list($tmp,$value) = explode(":",$value);
						$tmp = self::yamlunescape(trim($tmp));
						switch(trim($value)){
							case "|": $isblock = true; $onblock = false;
							case ">": $isseq = $onlabel = true; $result[$name = $tmp] = ""; break;
							default: $result[$tmp] = self::yamlunescape(ltrim($value));
						}
					}
					$child = array();
					$indent = 0;
			}
		}
		return $result;
	}
	static private function yamlunescape($value){
		return self::seem(preg_replace("/^(['\"])(.+)\\1.*$/","\\2",str_replace(array("__SHAPE__","__COLON__"),array("#",":"),$value)));
	}
	/**
	 * 文字列をそれっぽい型にして返す
	 * @param string $value 対象の文字列
	 * @return mixed
	 */
	static public function seem($value){
		if(!is_string($value)) throw new InvalidArgumentException("not string");
		if(is_numeric(trim($value))) return (strpos($value,".") !== false) ? floatval($value) : intval($value);
		switch(strtolower($value)){
			case "null": return null;
			case "true": return true;
			case "false": return false;
			default: return $value;
		}
		
	}
	/**
	 * 文字列中に指定した文字列がすべて存在するか
	 *
	 * @param string $str 対象の文字列
	 * @param string $query 検索する文字列
	 * @param string $delimiter 検索する文字列を分割する文字列
	 * @return boolean
	 */
	static public function match($str,$query,$delimiter=" "){
		foreach(explode($delimiter,$query) as $q){
			if(mb_strpos($str,$q) === false) return false;
		}
		return true;
		
	}
	/**
	 * 大文字小文字を区別せず、文字列中に指定した文字列がすべて存在するか
	 *
	 * @param string $str 対象の文字列
	 * @param string $query 検索する文字列
	 * @param string $delimiter 検索する文字列を分割する文字列
	 * @return boolean
	 */
	static public function imatch($str,$query,$delimiter=" "){
		foreach(explode($delimiter,$query) as $q){
			if(
				(function_exists("mb_stripos") && mb_stripos($str,$q) === false)
				|| mb_strpos(strtolower($str),strtolower($q)) === false
				) return false;
		}
		return true;
		
	}
	/**
	 * 文字列配列をtrimする
	 * @param string $value 対象の文字列
	 *
	 * @return string[]
	 */
	static public function trim(){
		
		$result = array();
		$args = (func_num_args() === 1 && is_array(func_get_arg(0))) ? func_get_arg(0) : func_get_args();
		foreach($args as $arg) $result[] = trim($arg);
		return $result;
	}
	/**
	 * 改行コードをLFに統一する
	 * @param string $src 対象の文字列
	 * @return string
	 */
	static public function uld($src){
		
		return str_replace(array("\r\n","\r"),"\n",$src);
	}
	/**
	 * コメント部分を除去
	 * @param string $src 対象の文字列
	 * @return string
	 */
	static public function uncomment($src){
		return preg_replace("/\/\*.+?\*\//s","",$src);
		
	}
	/**
	 * HTMLデコードした文字列を返す
	 * @param string $value 対象の文字列
	 * @return string
	 */
	static public function htmldecode($value){
		if(!empty($value) && is_string($value)){
			$value = mb_convert_encoding($value,"UTF-8",mb_detect_encoding($value));
			$value = preg_replace("/&#[xX]([0-9a-fA-F]+);/eu","'&#'.hexdec('\\1').';'",$value);
			$value = mb_decode_numericentity($value,array(0x0,0x10000,0,0xfffff),"UTF-8");
			$value = html_entity_decode($value,ENT_QUOTES,"UTF-8");
			$value = str_replace(array("\\\"","\\'","\\\\"),array("\"","\'","\\"),$value);
		}
		return $value;
		
	}
	/**
	 * htmlエンコードをする
	 * @param string $value 対象の文字列
	 * @return string
	 */
	final static public function htmlencode($value){
		if(!empty($value) && is_string($value)){
			$value = mb_convert_encoding($value,"UTF-8",mb_detect_encoding($value));
			return htmlentities($value,ENT_QUOTES,"UTF-8");
		}
		return $value;
		
	}
	/**
	 * 文字エンコード
	 *
	 * @param string $value 対象の文字列
	 * @param string $enc 変換後の文字エンコード
	 * @param string $from 元の文字エンコード
	 * @return string
	 */
	final static public function encode($value,$enc="UTF-8",$from=null){
		if(is_string($value)) return mb_convert_encoding($value,$enc,(empty($from) ? self::$detect_order : $from));
		if(is_array($value)){
			foreach($value as $k => $v){
				$value[self::encode($k,$enc,$from)] = self::encode($v,$enc,$from);
			}
		}
		return $value;
		
	}
	/**
	 * フォーマット文字列 $str に基づき生成された文字列を返します。
	 *
	 * @param string $str 対象の文字列
	 * @param mixed[] $params フォーマット中に現れた置換文字列{1},{2}...を置換する値
	 * @return string
	 */
	final static public function fstring($str,$params){
		if(preg_match_all("/\{([\d]+)\}/",$str,$match)){
			$params = func_get_args();
			array_shift($params);
			if(is_array($params[0])) $params = $params[0];
			foreach($match[1] as $key => $value){
				$i = ((int)$value) - 1;
				$str = str_replace($match[0][$key],isset($params[$i]) ? $params[$i] : "",$str);
			}
		}
		return $str;
		
	}
	/**
	 * 文字数を返す
	 * @param string $str 対象の文字列
	 * @param string $enc 文字エンコード
	 * @return integer
	 */
	final static public function length($str,$enc=null){
		if(is_array($str)){
			$length = 0;
			foreach($str as $value){
				if($length < self::length($value,$enc)) $length = self::length($value,$enc);
			}
			return $length;
		}
		return mb_strlen($str,empty($enc) ? mb_detect_encoding($str,self::$detect_order,true) : $enc);
		
	}
	/**
	 * 文字列の部分を返す
	 * @param string $str 対象の文字列
	 * @param integer $start 開始位置
	 * @param integer $length 最大長
	 * @param string $enc 文字コード
	 * @return string
	 */
	final static public function substring($str,$start,$length=null,$enc=null){
		return mb_substr($str,$start,empty($length) ? self::len($str) : $length,empty($enc) ? mb_detect_encoding($str,self::$detect_order,true) : $enc);
		
	}
	/**
	 * 文字列から配列にする
	 * @param string $dict 対象の文字列
	 * @return mixed{}
	 */
	final static public function dict($dict){
		$result = array();
		if(is_string($dict) && strpos($dict,'=') !== false){
			$dict = preg_replace("/(\(.+\))|(([\"\']).+?\\3)/e",'stripcslashes(str_replace(",","__ANNON_COMMA__","\\0"))',$dict);			
			foreach(explode(',',$dict) as $arg){
				if($arg != ''){
					$exp = explode('=',$arg,2);
					if(sizeof($exp) !== 2) throw new InvalidArgumentException('syntax error `'.$arg.'`');
					if(substr($exp[1],-1) == ',') $exp[1] = substr($exp[1],0,-1);
					$value = ($exp[1] === '') ? null : str_replace('__ANNON_COMMA__',',',$exp[1]);
					$result[trim($exp[0])] = ($value === 'true') ? true : (($value === 'false') ? false : $value);
				}
			}
		}
		return $result;
		
	}
	/**
	 * 文字列表現を返す
	 * @param Object $obj 対象の値
	 * @return string
	 */
	final static public function str($obj){
		if(is_bool($obj)) return ($obj) ? "true" : "false";
		if(!is_object($obj)) return (string)$obj;
		return (string)$obj;
		
	}
}
/**
 * setup制御
 * @author Kazutaka Tokushima
 * @license New BSD License
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
	}
	/**
	 * po,moファイルを生成する
	 * @param string $value パッケージ名、未指定の場合はアプリケーションが対象
	 */
	static public function __setup_gettext__(Request $req,$value){
		if(empty($value)){
			$base = File::absolute(App::path(),'resources/locale/messages');
			$gettext = new Gettext();
			$gettext->search(App::core_path());
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
		if(empty($value)){
			Test::verifies();
		}else{
			Test::verify($value,$req->in_vars("m"),$req->in_vars("b"));
		}
		Test::flush();
		self::println(sprintf("memory_get_usage: %s Mbyte ( %s sec )\n",number_format((memory_get_usage() / 1024 / 1024),3),number_format((microtime(true)-$start_time),3)));
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
					if(preg_match_all("/[^\w](import|R|C)\(([\"\'])([\w\.]+?)\\2\)/",$src,$match)){
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
					if($f->is_ext('php') && preg_match_all("/[^\w](import|R|C)\(([\"\'])([\w\.]+?)\\2\)/",file_get_contents($f->fullname()),$match)){
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
		if(isset($apps['index'])){
			unset($apps['index']);
			$rules = "RewriteEngine On\n"
						."RewriteBase ".$value."\n\n";
			
			foreach($apps as $app => $v){
				$rules .= "RewriteCond %{REQUEST_FILENAME} !-f\n"
							."RewriteCond %{REQUEST_FILENAME} !-d\n"
							."RewriteRule ^".$app."[/]{0,1}(.*)\$ ".$app.".php/\$1?%{QUERY_STRING} [L]\n\n";
			}
			$rules .= "RewriteCond %{REQUEST_FILENAME} !-f\n"
						."RewriteCond %{REQUEST_FILENAME} !-d\n"
						."RewriteRule ^(.+)\$ index.php/\$1?%{QUERY_STRING} [L]\n\n";
			$ex = is_file($path);
			File::write($path,$rules);
			self::println(($ex ? 'rewrite ' : 'create ').$path,true);
		}
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
						."<app>\n"
							."\t<handler>\n"
							."\t\t<map url=\"\" template=\"index.html\" />\n"
							."\t</handler>\n"
						."</app>\n"
					);
		self::println('Create '.$path,true);
		
		foreach(array('resources/templates','resources/media','libs') as $p){
			if(!is_dir(App::path($p))){
				File::mkdir(App::path($p));
				self::println('Create '.App::path($p),true);
			}
		}
		$path = App::path('resources/templates/index.html');
		if(!is_file($path)){
			File::write($path
							,"<html>\n"
							."<body>\n"
							."<h1>Enjoy</h1>\n"
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
			if(preg_match("/App::config_path\(.+/",$src,$match)){
				$src = str_replace($match[0],sprintf('App::config_path(__FILE__,"%s","%s","%s");',$url,$work,$mode),$src);
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
/**
 * テスト処理
 *
 * @author Kazutaka Tokushima
 * @license New BSD License
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
	static private $current_method;
	static private $current_file;
	static private $in_test = false;
	static private $maps = array();
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
	/**
	 * doctestを取得する
	 * @return array
	 */
	final static public function get_doctest($path){
		$result = array();
		$type = $class_name = null;
		$filename = is_file($path) ? $path : App::path($path.".php");
		if(is_file($filename)){
			$type = 'app';
			$read = Text::uld(File::read($filename));
			if(Tag::setof($app,$read,"app")){
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
		}else{
			$type = 'class';
			$class_name = (!class_exists($path) && !interface_exists($path)) ? Lib::import($path) : $path;
			$rc = new ReflectionClass($class_name);
			$filename = $rc->getFileName();
			$class_src_lines = file($filename);
			$class_src = implode("",$class_src_lines);
			
			foreach($rc->getMethods() as $method){
				if($method->getDeclaringClass()->getName() == $rc->getName()){
					$method_src = implode('',array_slice($class_src_lines,$method->getStartLine()-1,$method->getEndLine()-$method->getStartLine(),true));				
					$result = array_merge($result,self::get_method_doctest($rc->getName(),$method->getName(),$method->getStartLine(),$method->isPublic(),$method_src));
					$class_src = str_replace($method_src,str_repeat("\n",self::space_line_count($method_src)),$class_src);
				}
			}
			$result = array_merge($result,self::get_method_doctest($rc->getName(),'@',1,false,$class_src));
			self::merge_setup_teardown($result);
		}
		return array('type'=>$type,'filename'=>$filename,'class_name'=>$class_name,'tests'=>$result);
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
			$f = " Testing.. ".$path;
			$l = strlen($f);
			print($f);
			self::verify($path);
			print("\033[".$l."D");
			print(str_repeat(" ",$l));
			print("\033[".$l."D");
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
		if(is_dir(App::path())){
			foreach(File::ls(App::path()) as $f){
				if($f->is_ext('php') && !$f->is_private()){
					self::verify_format($f->oname());
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
		$doctest = self::get_doctest($class_path);
		self::$current_file = $doctest['filename'];
		self::$current_class = $doctest['class_name'];		
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
							if($doctest['type'] == 'app'){
								$pre_branch = App::branch();
								App::branch(new File($doctest['filename']));
								self::$current_map_test_file = $doctest['filename'];
							}
							try{
								ob_start();
								if(isset($tests['__setup__'])) eval($tests['__setup__'][1]);
								eval($block);
								if(isset($tests['__teardown__'])) eval($tests['__teardown__'][1]);
								Exceptions::clear();
								if($doctest['type'] == 'app'){
									App::branch($pre_branch);
								}
								$result = ob_get_clean();
								if(preg_match("/(Parse|Fatal) error:.+/",$result,$match)) throw new ErrorException($match[0]);
							}catch(Exception $e){
								if(ob_get_level() > 0) $result = ob_get_clean();
								list($message,$file,$line) = array($e->getMessage(),$e->getFile(),$e->getLine());
								if($e instanceof Exceptions){
									$last = $e->last();
									list($message,$file,$line) = array(((string)$e),$last->getFile(),$last->getLine());
								}
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
								Log::warn("[".$line.":".$file."] ".$message);
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
		$result = "";
		$tab = "  ";
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
	/**
	 * xmlのmapのnameからurlを返す
	 * @param string $test_file テストファイルパス
	 * @param string $map_name テストファイルにひも付くアプリケーションXMLのMAP名
	 * @return string
	 */
	static public function map_url($test_file,$map_name){
		$args = func_get_args();
		array_shift($args);
		array_shift($args);
		if(!empty(self::$current_map_test_file)) $test_file = self::$current_map_test_file;
		if(!isset(self::$maps[$test_file]) && is_file($test_file)){
			$parse_app = Flow::parse_app($test_file);
			foreach($parse_app['apps'] as $app){
				if($app['type'] == 'handle'){
					foreach($app['maps'] as $p => $c){
						$count = 0;
						if(!empty($p)) $p = substr(preg_replace_callback("/([^\\\\])(\(.*?[^\\\\]\))/",create_function('$m','return $m[1]."%s";')," ".$p,-1,$count),1);
						if(!empty($c['name'])) self::$maps[$test_file][$c['name']][$count] = $p;
					}
				}
			}
		}
		if(!isset(self::$maps[$test_file])) throw new InvalidArgumentException($test_file." is not app");
		if(!isset(self::$maps[$test_file][$map_name]) || !isset(self::$maps[$test_file][$map_name][sizeof($args)])){
			throw new InvalidArgumentException($test_file."[".$map_name."](".sizeof($args).") not found");
		}
		return App::url(vsprintf(self::$maps[$test_file][$map_name][sizeof($args)],$args));
	}
	final static private function space_line_count($value){
		return sizeof(explode("\n",$value)) - 1;
	}
}
/**
 * コマンドを実行する
 *
 * @author Kazutaka Tokushima
 * @license New BSD License
 * @var string $stdout
 * @var string $stderr
 */
class Command extends Object{
	protected $resource; #リソース
	protected $stdout; # 実行結果
	protected $stderr; # 実行時のエラー
	protected $end_code; # 実行していたプロセスの終了状態
	private $proc;
	private $close = true;
	protected function __new__($command=null){
		if(!empty($command)){
			$this->open($command);
			$this->close();
		}
	}
	/**
	 * コマンドを実行しプロセスをオープする
	 * @param string $command 実行するコマンド
	 * @param string $out_file 結果を保存するファイルパス
	 * @param string $error_file エラー結果を保存するファイルパス
	 */
	public function open($command,$out_file=null,$error_file=null){
		Log::debug($command);
		$this->close();
		if(!empty($out_file)) File::write($out_file);
		if(!empty($error_file)) File::write($error_file);
		$out = (empty($out_file)) ? array("pipe","w") : array("file",$out_file,"w");
		$err = (empty($error_file)) ? array("pipe","w") : array("file",$error_file,"w");
		$this->proc = proc_open($command,array(array("pipe","r"),$out,$err),$this->resource);
		$this->close = false;
	}
	/**
	 * コマンドを実行し出力する
	 * @param string $command 実行するコマンド
	 */
	public function write($command){
		Log::debug($command);
		fwrite($this->resource[0],$command."\n");
	}
	/**
	 * 結果を取得する
	 * @return string
	 */
	public function gets(){
		if(isset($this->resource[1])){
			$value = fgets($this->resource[1]);
			$this->stdout .= $value;
			return $value;
		}
	}
	/**
	 * 結果から１文字取得する
	 * @return string
	 */
	public function getc(){
		if(isset($this->resource[1])){
			$value = fgetc($this->resource[1]);
			$this->stdout .= $value;
			return $value;
		}
	}
	/**
	 * 閉じる
	 */
	public function close(){
		if(!$this->close){
			if(isset($this->resource[0])) fclose($this->resource[0]);
			if(isset($this->resource[1])){
				while(!feof($this->resource[1])) $this->stdout .= fgets($this->resource[1]);
				fclose($this->resource[1]);
			}
			if(isset($this->resource[2])){
				while(!feof($this->resource[2])) $this->stderr .= fgets($this->resource[2]);
				fclose($this->resource[2]);
			}
			$this->end_code = proc_close($this->proc);
			$this->close = true;
		}
	}
	protected function __del__(){
		$this->close();
	}
	protected function __str__(){
		return $this->out;
	}
	/**
	 * コマンドを実行し結果を取得
	 * @param string $command
	 * @return unknown_type
	 */
	static public function out($command){
		$self = new self($command);
		return $self->stdout();
	}
	/**
	 * コマンドを実行してエラー結果を取得
	 * @param string $command 実行するコマンド
	 * @return string
	 */
	static public function error($command){
		$self = new self($command);
		return $self->stderr();
	}
	/**
	 * 標準入力からの入力を取得する
	 * @param string $msg 入力待ちのメッセージ
	 * @param string $default 入力が空だった場合のデフォルト値
	 * @param string[] $choice 入力を選択式で求める
	 * @param boolean $multiline 複数行の入力をまつ、終了は行頭.(ドット)
	 * @return string
	 */
	static public function stdin($msg,$default=null,$choice=array(),$multiline=false){
		$result = null;
		print($msg.(empty($choice) ? "" : " (".implode(" / ",$choice).")").(empty($default) ? "" : " [".$default."]").": ");
		while(true){
			fscanf(STDIN,"%s",$b);
			if($multiline && $b == ".") break;
			$result .= $b."\n";
			if(!$multiline) break;
		}
		$result = substr(str_replace(array("\r\n","\r","\n"),"\n",$result),0,-1);
		if(empty($result)) $result = $default;
		if(empty($choice) || in_array($result,$choice)) return $result;
	}
}
/**
 * ファイル処理
 * @author Kazutaka Tokushima
 * @license New BSD License
 * @var integer $error エラーコード
 * @var string $directory フォルダパス
 * @var string $fullname ファイルパス
 * @var string $name ファイル名
 * @var string $oname 拡張子がつかないファイル名
 * @var string $ext 拡張子
 * @var string $mime ファイルのコンテントタイプ
 * @var string $tmp 一時ファイルパス
 * @var text $value 内容
 */
class File extends Object{
	protected $fullname;
	protected $value;
	protected $mime;
	protected $tmp;
	protected $error;
	protected $directory;
	protected $name;
	protected $oname;
	protected $ext;
	static private $dir_permission = 0755;
	static private $file_permission = 0644;
	static private $lock = true;
	/**
	 * デフォルトの権限のを定義する
	 * @param integer $file_permission モード　8進数(0644)
	 * @param integer $dir_permission モード　8進数(0644)
	 */
	final static public function config_permission($file_permission,$dir_permission=null){
		if($file_permission !== null) self::$file_permission = $file_permission;
		if($dir_permission !== null) self::$dir_permission = $dir_permission;
	}
	/**
	 * ロックの動作を定義する
	 * @param boolean $boolean nfs等ネットワークドライブでロックが出来ない場合にfalseにする
	 */
	final static public function config_lock($boolean){
		self::$lock = (boolean)$boolean;
	}
	final protected function __new__($fullname=null,$value=null){
		$this->fullname	= str_replace("\\",'/',$fullname);
		$this->value = $value;
		$this->parse_fullname();
	}
	final protected function __cp__($dest,$file_permission=null,$dir_permission=null){
		return self::copy($this,$dest,$file_permission,$dir_permission);
	}
	final protected function __str__(){
		return $this->fullname;
	}
	final protected function __is_ext__($ext){
		return ('.'.strtolower($ext) === strtolower($this->ext()));
	}
	final protected function __is_fullname__(){
		return is_file($this->fullname);
	}
	final protected function __is_tmp__(){
		return is_file($this->tmp);
	}
	final protected function __is_error__(){
		return (intval($this->error) > 0);
	}
	final protected function __set_value__($value){
		$this->value = $value;
		$this->size = sizeof($value);
	}
	/**
	 * 一時ファイルから移動する
	 * HTMLでのファイル添付の場合に使用
	 * @param string $filename ファイルパス
	 * @param integer $file_permission モード　8進数(0644)
	 * @param integer $dir_permission モード　8進数(0644)
	 * @return $this
	 */
	public function generate($filename,$file_permission=null,$dir_permission=null){
		if(self::copy($this->tmp,$filename,$file_permission,$dir_permission)){
			if(unlink($this->tmp)){
				$this->fullname = $filename;
				$this->parse_fullname();
				return $this;
			}
		}
		throw new InvalidArgumentException(sprintf('permission denied `%s`',$filename));
	}
	/**
	 * 標準出力に出力する
	 */
	public function output(){
		if(empty($this->value) && @is_file($this->fullname)){
			readfile($this->fullname);
		}else{
			print($this->value);
		}
		exit;
	}
	/**
	 * 内容を取得する
	 * @return string
	 */
	public function get(){
		if($this->value !== null) return $this->value;
		if(is_file($this->fullname)) return file_get_contents($this->fullname);
		if(is_file($this->tmp)) return file_get_contents($this->tmp);
		throw new InvalidArgumentException(sprintf('permission denied `%s`',$this->fullname));
	}
	public function update(){
		return (@is_file($this->fullname)) ? @filemtime($this->fullname) : time();
	}
	public function size(){
		return (@is_file($this->fullname)) ? @filesize($this->fullname) : strlen($this->value);
	}
	private function parse_fullname(){
		$fullname = str_replace("\\",'/',$this->fullname);
		if(preg_match("/^(.+[\/]){0,1}([^\/]+)$/",$fullname,$match)){
			$this->directory = empty($match[1]) ? "./" : $match[1];
			$this->name = $match[2];
		}
		if(false !== ($p = strrpos($this->name,'.'))){
			$this->ext = '.'.substr($this->name,$p+1);
			$filename = substr($this->name,0,$p);
		}
		$this->oname = @basename($this->name,$this->ext);
		if(empty($this->mime)){
			$ext = strtolower(substr($this->ext,1));
			switch($ext){
				case 'jpg':
				case 'jpeg': $ext = 'jpeg';
				case 'png':
				case 'gif':
				case 'bmp':
				case 'tiff': $this->mime = 'image/'.$ext; break;
				case 'css': $this->mime = 'text/css'; break;
				case 'txt': $this->mime = 'text/plain'; break;
				case 'html': $this->mime = 'text/html'; break;
				case 'xml': $this->mime = 'application/xml'; break;
				case 'js': $this->mime = 'text/javascript'; break;
				case 'flv':
				case 'swf': $this->mime = 'application/x-shockwave-flash'; break;
				case '3gp': $this->mime = 'video/3gpp'; break;
				case 'gz':
				case 'tgz':
				case 'tar':
				case 'gz':  $this->mime = 'application/x-compress'; break;
				default:
					/**
					 * MIMEタイプを設定する
					 * @param self $this
					 * @return string mime-type
					 */
					$this->mime = (string)(Object::C(__CLASS__)->call_module('parse_mime_type',$this));
					if(empty($this->mime)) $this->mime = 'application/octet-stream';
			}
		}
	}
	/**
	 * クラスファイルか
	 * @return boolean
	 */
	final public function is_class(){
		return (!empty($this->oname) && $this->is_ext('php') && ctype_upper($this->oname[0]));
	}
	/**
	 * 不過視ファイルか
	 * @return boolean
	 */
	final public function is_invisible(){
		return (!empty($this->oname) && ($this->oname[0] == '.' || strpos($this->fullname,'/.') !== false));
	}
	/**
	 * privateファイルか
	 * @return boolean
	 */
	final public function is_private(){
		return (!empty($this->oname) && $this->oname[0] == '_');
	}
	
	/**
	 * ファイルパスを生成する
	 * @param string $base ベースとなるファイルパス
	 * @param string $path ファイルパス
	 * @return string
	 */
	static public function path($base,$path=''){
		
		if(!empty($path)){
			$path = self::parse_filename($path);
			if(preg_match("/^[\/]/",$path,$null)) $path = substr($path,1);
		}
		return self::absolute(self::parse_filename($base),self::parse_filename($path));
	}
	/**
	 * フォルダを作成する
	 * @param string $source 作成するフォルダパス
	 * @param integer $dir_permission モード　8進数(0644)
	 */
	static public function mkdir($source,$dir_permission=null){
		if(!is_dir($source)){
			try{
				mkdir($source,(($dir_permission === null) ? self::$dir_permission : $dir_permission),true);
			}catch(ErrorException $e){
				throw new InvalidArgumentException(sprintf('permission denied `%s`',$source));
			}
		}
	}
	/**
	 * ファイル、またはフォルダが存在しているか
	 * @param string $filename ファイルパス
	 * @return boolean
	 */
	static public function exist($filename){
		return (is_readable($filename) && (is_file($filename) || is_dir($filename) || is_link($filename)));
	}
	/**
	 * 移動
	 * @param string $source 移動もとのファイルパス
	 * @param string $dest 移動後のファイルパス
	 * @param integer $dir_permission モード　8進数(0644)
	 * @return boolean 移動に成功すればtrue
	 */
	static public function mv($source,$dest,$dir_permission=null){
		$source = self::parse_filename($source);
		$dest = self::parse_filename($dest);
		if(self::exist($source)){
			self::mkdir(dirname($dest),$dir_permission);
			return rename($source,$dest);
		}
		throw new InvalidArgumentException(sprintf('permission denied `%s`',$source));
	}
	/**
	 * 最終更新時間を取得
	 * @param string $filename ファイルパス
	 * @param boolean $clearstatcache ファイルのステータスのキャッシュをクリアするか
	 * @return integer
	 */
	static public function last_update($filename,$clearstatcache=false){
		if($clearstatcache) clearstatcache();
		if(is_dir($filename)){
			$last_update = null;
			foreach(File::ls($filename,true) as $file){
				if($last_update < $file->update()) $last_update = $file->update();
			}
			return $last_update;
		}
		return (is_readable($filename) && is_file($filename)) ? filemtime($filename) : null;
	}
	/**
	 * 削除
	 * $sourceがフォルダで$inc_selfがfalseの場合は$sourceフォルダ以下のみ削除
	 * @param string $source 削除するパス
	 * @param boolean $inc_self $sourceも削除するか
	 * @return boolean
	 */
	static public function rm($source,$inc_self=true){
		if($source instanceof self) $source = $source->fullname();
		$source	= self::parse_filename($source);
		if(!$inc_self){
			foreach(self::dir($source) as $d) self::rm($d);
			foreach(self::ls($source) as $f) self::rm($f);
			return true;
		}
		if(!self::exist($source)) return true;
		if(is_writable($source)){
			if(is_dir($source)){
				if($handle = opendir($source)){
					$list = array();
					while($pointer = readdir($handle)){
						if($pointer != '.' && $pointer != '..'){
							$list[] = sprintf('%s/%s',$source,$pointer);
						}
					}
					closedir($handle);
					foreach($list as $path){
						if(!self::rm($path)) return false;
					}
				}
				if(rmdir($source)){
					clearstatcache();
					return true;
				}
			}else if(is_file($source) && unlink($source)){
				clearstatcache();
				return true;
			}
		}
		throw new InvalidArgumentException(sprintf('permission denied `%s`',$source));
	}
	/**
	 * コピー
	 * $sourceがフォルダの場合はそれ以下もコピーする
	 * @param string $source コピー元のファイルパス
	 * @param string $dest コピー先のファイルパス
	 * @param integer $file_permission モード　8進数(0644)
	 * @param integer $dir_permission モード　8進数(0644)
	 * @return boolean 成功時true
	 */
	static public function copy($source,$dest,$file_permission=null,$dir_permission=null){
		$source	= self::parse_filename($source);
		$dest = self::parse_filename($dest);
		$dir = (preg_match("/^(.+)\/[^\/]+$/",$dest,$tmp)) ? $tmp[1] : $dest;
		if(!self::exist($source)) throw new InvalidArgumentException(sprintf('permission denied `%s`',$source));
		self::mkdir($dir,$dir_permission);
		if(is_dir($source)){
			$bool = true;
			if($handle = opendir($source)){
				while($pointer = readdir($handle)){
					if($pointer != '.' && $pointer != '..'){
						$srcname = sprintf('%s/%s',$source,$pointer);
						$destname = sprintf('%s/%s',$dest,$pointer);
						if(false === ($bool = self::copy($srcname,$destname,$file_permission,$dir_permission))) break;
					}
				}
				closedir($handle);
			}
			return $bool;
		}else{
			$filename = (preg_match("/^.+(\/[^\/]+)$/",$source,$tmp)) ? $tmp[1] : '';
			$dest = (is_dir($dest))	? $dest.$filename : $dest;
			if(is_writable(dirname($dest))){
				copy($source,$dest);
				chmod($dest,(($file_permission === null) ? self::$file_permission : $file_permission));
			}
			return self::exist($dest);
		}
	}
	/**
	 * ファイルから取得する
	 * @param string $filename ファイルパス
	 * @return string
	 */
	static public function read($filename){
		if($filename instanceof self) $filename = ($filename->is_fullname()) ? $filename->fullname() : $filename->tmp();
		if(!is_readable($filename) || !is_file($filename)) throw new InvalidArgumentException(sprintf('permission denied `%s`',$filename));
		return file_get_contents($filename);
	}
	/**
	 * ファイルから行分割して配列で返す
	 *
	 * @param string $filename ファイルパス
	 * @return string
	 */
	static public function lines($filename){
		return explode("\n",str_replace(array("\r\n","\r"),"\n",self::read($filename)));
	}
	/**
	 * ファイルに書き出す
	 * @param string $filename ファイルパス
	 * @param string $src 内容
	 * @param integer $file_permission モード　8進数(0644)
	 * @param integer $dir_permission モード　8進数(0644)
	 */
	static public function write($filename,$src=null,$file_permission=null,$dir_permission=null){
		if($filename instanceof self) $filename = $filename->fullname;
		if(empty($filename)) throw new InvalidArgumentException(sprintf('permission denied `%s`',$filename));
		self::mkdir(dirname($filename),$dir_permission);
		if(false === file_put_contents($filename,Text::str($src),((self::$lock) ? LOCK_EX : 0))) throw new InvalidArgumentException(sprintf('permission denied `%s`',$filename));
		chmod($filename,(($file_permission === null) ? self::$file_permission : $file_permission));
	}
	/**
	 * ファイルに追記する
	 * @param string $filename ファイルパス
	 * @param string $src 追加する内容
	 * @param integer $dir_permission モード　8進数(0644)
	 */
	static public function append($filename,$src=null,$dir_permission=null){
		if($filename instanceof self) $filename = $filename->fullname;
		self::mkdir(dirname($filename),$dir_permission);
		if(false === file_put_contents($filename,Text::str($src),FILE_APPEND|((self::$lock) ? LOCK_EX : 0))) throw new InvalidArgumentException(sprintf('permission denied `%s`',$filename));
	}
	/**
	 * ファイルから取得する
	 * @param string $filename ファイルパス
	 * @return string
	 */
	static public function gzread($filename){
		if($filename instanceof self) $filename = ($filename->is_fullname()) ? $filename->fullname() : $filename->tmp();
		if(strpos($filename,'://') === false && (!is_readable($filename) || !is_file($filename))) throw new InvalidArgumentException(sprintf('permission denied `%s`',$filename));
		try{
			$fp = gzopen($filename,'rb');
			$buf = null;
			while(!gzeof($fp)) $buf .= gzread($fp,4096);
			gzclose($fp);
			return $buf;
		}catch(Exception $e){
			throw new InvalidArgumentException(sprintf('permission denied `%s`',$filename));
		}
	}
	/**
	 * gz圧縮でファイルに書き出す
	 * @param string $filename ファイルパス
	 * @param string $src 内容
	 * @param integer $file_permission モード　8進数(0644)
	 * @param integer $dir_permission モード　8進数(0644)
	 */
	static public function gzwrite($filename,$src,$file_permission=null,$dir_permission=null){
		if($filename instanceof self) $filename = $filename->fullname;
		self::mkdir(dirname($filename),$dir_permission);
		try{
			$fp = gzopen($filename,'wb9');
			gzwrite($fp,$src);
			gzclose($fp);
			chmod($filename,(($file_permission === null) ? self::$file_permission : $file_permission));
		}catch(Exception $e){
			throw new InvalidArgumentException(sprintf('permission denied `%s`',$filename));
		}
	}
	/**
	 * ファイル、またはディレクトリからtar圧縮のデータを作成する
	 * @param string $path 圧縮するファイルパス
	 * @param string $base_dir tarのヘッダ情報をこのファイルパスを除く相対パスとして作成する
	 * @param string $ignore_pattern 除外パターン
	 * @param boolean $endpoint エンドポイントとするか
	 * @return string
	 */
	static public function tar($path,$base_dir=null,$ignore_pattern=null,$endpoint=true){
		$result = null;
		$files = array();
		$path = self::parse_filename($path);
		$base_dir = self::parse_filename(empty($base_dir) ? (is_dir($path) ? $path : dirname($path)) : $base_dir);
		$ignore = (!empty($ignore_pattern));
		if(substr($base_dir,0,-1) != '/') $base_dir .= '/';
		$filepath = self::absolute($base_dir,$path);
		if(is_dir($filepath)){
			foreach(self::dir($filepath,true) as $dir) $files[$dir] = 5;
			foreach(self::ls($filepath,true) as $file) $files[$file->fullname()] = 0;
		}else{
			$files[$filepath] = 0;
		}
		foreach($files as $filename => $type){
			$target_filename = str_replace($base_dir,'',$filename);
			$bool = true;
			if($ignore){
				$ignore_pattern = (is_array($ignore_pattern)) ? $ignore_pattern : array($ignore_pattern);
				foreach($ignore_pattern as $p){
					if(preg_match('/'.str_replace(array("\/",'/','__SLASH__'),array('__SLASH__',"\/","\/"),$p).'/',$target_filename)){
						$bool = false;
						break;
					}
				}
			}
			if(!$ignore || $bool){
				switch($type){
					case 0:
						$info = stat($filename);
						$rp = fopen($filename,'rb');
							$result .= self::tar_head($type,$target_filename,filesize($filename),fileperms($filename),$info[4],$info[5],filemtime($filename));
							while(!feof($rp)){
								$buf = fread($rp,512);
								if($buf !== '') $result .= pack('a512',$buf);
							}
						fclose($rp);
						break;
					case 5:
						$result .= self::tar_head($type,$target_filename);
						break;
				}
			}
		}
		if($endpoint) $result .= pack("a1024",null);
		return $result;
	}
	static private function tar_head($type,$filename,$filesize=0,$fileperms=0644,$uid=0,$gid=0,$update_date=null){
		if(strlen($filename) > 99) throw new InvalidArgumentException('invalid filename (max length 100) `'.$filename.'`');
		if($update_date === null) $update_date = time();
		$checksum = 256;
		$first = pack('a100a8a8a8a12A12',$filename,
						sprintf('%06s ',decoct($fileperms)),sprintf('%06s ',decoct($uid)),sprintf('%06s ',decoct($gid)),
						sprintf('%011s ',decoct(($type === 0) ? $filesize : 0)),sprintf('%11s',decoct($update_date)));
		$last = pack('a1a100a6a2a32a32a8a8a155a12',$type,null,null,null,null,null,null,null,null,null);
		for($i=0;$i<strlen($first);$i++) $checksum += ord($first[$i]);
		for($i=0;$i<strlen($last);$i++) $checksum += ord($last[$i]);
		return $first.pack('a8',sprintf('%6s ',decoct($checksum))).$last;
	}
	/**
	 * tarを解凍する
	 * @param string $src tar文字列
	 * @param string $outpath 展開先のファイルパス
	 * @param integer $file_permission モード　8進数(0644)
	 * @param integer $dir_permission モード　8進数(0644)
	 * @return string{} 展開されたファイル情報
	 */
	static public function untar($src,$outpath=null,$file_permission=null,$dir_permission=null){
		$result = array();
		$isout = !empty($outpath);
		for($pos=0,$vsize=0,$cur='';;){
			$buf = substr($src,$pos,512);
			if(strlen($buf) < 512) break;
			$data = unpack('a100name/a8mode/a8uid/a8gid/a12size/a12mtime/'
							.'a8chksum/'
							.'a1typeflg/a100linkname/a6magic/a2version/a32uname/a32gname/a8devmajor/a8devminor/a155prefix',
							 $buf);
			$pos += 512;
			if(!empty($data['name'])){
				$obj = new stdClass();
				$obj->type = (int)$data['typeflg'];
				$obj->path = $data['name'];
				$obj->update = base_convert($data['mtime'],8,10);
				switch($obj->type){
					case 0:
						$obj->size = base_convert($data['size'],8,10);
						$obj->content = substr($src,$pos,$obj->size);
						$pos += (ceil($obj->size / 512) * 512);
						if($isout){
							$p = self::absolute($outpath,$obj->path);
							self::write($p,$obj->content,$file_permission,$dir_permission);
							touch($p,$obj->update);
						}
						break;
					case 5:
						if($isout) self::mkdir(self::absolute($outpath,$obj->path),$dir_permission);
						break;
				}
				if(!$isout) $result[$obj->path] = $obj;
			}
		}
		return $result;
	}
	/**
	 * tar.gz(tgz)圧縮してファイル書き出しを行う
	 *
	 * @param string $tgz_filename
	 * @param string $path
	 * @param string $base_dir
	 * @param string $ignore_pattern
	 * @param integer $file_permission モード　8進数(0644)
	 * @param integer $dir_permission モード　8進数(0644)
	 */
	static public function tgz($tgz_filename,$path,$base_dir=null,$ignore_pattern=null,$file_permission=null,$dir_permission=null){
		self::gzwrite($tgz_filename,self::tar($path,$base_dir,$ignore_pattern),$file_permission,$dir_permission);
	}
	/**
	 * tar.gz(tgz)を解凍してファイル書き出しを行う
	 * @param string $inpath 解凍するファイルパス
	 * @param string $outpath 解凍先のファイルパス
	 * @param integer $file_permission モード　8進数(0644)
	 * @param integer $dir_permission モード　8進数(0644)
	 */
	static public function untgz($inpath,$outpath,$file_permission=null,$dir_permission=null){
		$tmp = false;
		if(strpos($inpath,'://') !== false && (boolean)ini_get('allow_url_fopen')){
			$tmpname = self::absolute($outpath,self::temp_path($outpath));
			$http = new Http();
			try{
				$http->do_download($inpath,$tmpname);
				if($http->status() !== 200) throw new InvalidArgumentException(sprintf('permission denied `%s`',$inpath));
			}catch(ErrorException $e){
				 throw new InvalidArgumentException(sprintf('permission denied `%s`',$tmpname));
			}
			$inpath = $tmpname;
			$tmp = true;
		}
		self::untar(self::gzread($inpath),$outpath,$file_permission,$dir_permission);
		if($tmp) self::rm($inpath);
	}
	static private function parse_filename($filename){
		$filename = preg_replace("/[\/]+/",'/',str_replace("\\",'/',trim($filename)));
		return (substr($filename,-1) == '/') ? substr($filename,0,-1) : $filename;
	}
	/**
	 * 絶対パスを取得
	 * @param string $baseUrl ベースとなるパス
	 * @param string $targetUrl 対象となる相対パス
	 * @return string
	 */
	static public function absolute($bu,$tu){
		
		$tu = str_replace("\\",'/',$tu);
		if(empty($tu)) return $bu;
		$bu = str_replace("\\",'/',$bu);
		if(preg_match("/^[\w]+\:\/\/[^\/]+/",$tu)) return $tu;
		$isnet = preg_match("/^[\w]+\:\/\/[^\/]+/",$bu,$basehost);
		$isroot = (substr($tu,0,1) == '/');
		if($isnet){
			if(strpos($tu,'javascript:') === 0 || strpos($tu,'mailto:') === 0) return $bu;
			$preg_cond = ($tu[0] === '#') ? '#' : "#\?";
			$bu = preg_replace("/^(.+?)[".$preg_cond."].*$/","\\1",$bu);
			if($tu[0] === '#' || $tu[0] === "?") return $bu.$tu;
			if(substr($bu,-1) !== '/'){
				if(substr($tu,0,2) === "./"){
					$tu = '.'.$tu;
				}else if($tu[0] !== '.' && $tu[0] !== '/'){
					$tu = "../".$tu;
				}
			}
		}
		if(empty($bu) || preg_match("/^[a-zA-Z]\:/",$tu) || (!$isnet && $isroot) || preg_match("/^[\w]+\:\/\/[^\/]+/",$tu)) return $tu;
		if($isnet && $isroot && isset($basehost[0])) return $basehost[0].$tu;
		$rlist = array(array('://','/./','//'),array('#REMOTEPATH#','/','/')
					,array("/^\/(.+)$/","/^(\w):\/(.+)$/"),array("#ROOT#\\1","\\1#WINPATH#\\2",'')
					,array('#REMOTEPATH#','#ROOT#','#WINPATH#'),array('://','/',':/'));
		$bu = preg_replace($rlist[2],$rlist[3],str_replace($rlist[0],$rlist[1],$bu));
		$tu = preg_replace($rlist[2],$rlist[3],str_replace($rlist[0],$rlist[1],$tu));
		$basedir = $targetdir = $rootpath = '';
		if(strpos($bu,'#REMOTEPATH#')){
			list($rootpath)	= explode('/',$bu);
			$bu = substr($bu,strlen($rootpath));
			$tu = str_replace('#ROOT#','',$tu);
		}
		$baseList = preg_split("/\//",$bu,-1,PREG_SPLIT_NO_EMPTY);
		$targetList = preg_split("/\//",$tu,-1,PREG_SPLIT_NO_EMPTY);
		for($i=0;$i<sizeof($baseList)-substr_count($tu,"../");$i++){
			if($baseList[$i] != '.' && $baseList[$i] != '..') $basedir .= $baseList[$i].'/';
		}
		for($i=0;$i<sizeof($targetList);$i++){
			if($targetList[$i] != '.' && $targetList[$i] != '..') $targetdir .= '/'.$targetList[$i];
		}
		$targetdir = (!empty($basedir)) ? substr($targetdir,1) : $targetdir;
		$basedir = (!empty($basedir) && substr($basedir,0,1) != '/' && substr($basedir,0,6) != '#ROOT#' && !strpos($basedir,'#WINPATH#')) ? '/'.$basedir : $basedir;
		return str_replace($rlist[4],$rlist[5],$rootpath.$basedir.$targetdir);
	}
	/**
	 * 相対パスを取得
	 * @param string $baseUrl ベースのファイルパス
	 * @param string $targetUrl ファイルパス
	 * @return string
	 */
	static public function relative($baseUrl,$targetUrl){
		
		$rlist = array(array('://','/./','//'),array('#REMOTEPATH#','/','/')
					,array("/^\/(.+)$/","/^(\w):\/(.+)$/"),array("#ROOT#\\1","\\1#WINPATH#\\2",'')
					,array('#REMOTEPATH#','#ROOT#','#WINPATH#'),array('://','/',':/'));
		$baseUrl = preg_replace($rlist[2],$rlist[3],str_replace($rlist[0],$rlist[1],str_replace("\\",'/',$baseUrl)));
		$targetUrl = preg_replace($rlist[2],$rlist[3],str_replace($rlist[0],$rlist[1],str_replace("\\",'/',$targetUrl)));
		$filename = $url = '';
		$counter = 0;
		if(preg_match("/^(.+\/)[^\/]+\.[^\/]+$/",$baseUrl,$null)) $baseUrl = $null[1];
		if(preg_match("/^(.+\/)([^\/]+\.[^\/]+)$/",$targetUrl,$null)) list($tmp,$targetUrl,$filename) = $null;
		if(substr($baseUrl,-1) == '/') $baseUrl = substr($baseUrl,0,-1);
		if(substr($targetUrl,-1) == '/') $targetUrl = substr($targetUrl,0,-1);
		$baseList = explode('/',$baseUrl);
		$targetList = explode('/',$targetUrl);
		$baseSize = sizeof($baseList);
		if($baseList[0] != $targetList[0]) return str_replace($rlist[4],$rlist[5],$targetUrl);
		foreach($baseList as $key => $value){
			if(!isset($targetList[$key]) || $targetList[$key] != $value) break;
			$counter++;
		}
		for($i=sizeof($targetList)-1;$i>=$counter;$i--) $filename = $targetList[$i].'/'.$filename;
		if($counter == $baseSize) return sprintf('./%s',$filename);
		return sprintf('%s%s',str_repeat('../',$baseSize - $counter),$filename);
	}
	/**
	 * フォルダ名の配列を取得
	 * @param string $directory  検索対象のファイルパス
	 * @param boolean $recursive 階層を潜って取得するか
	 * @param boolean $a 隠しファイルも参照するか
	 * @return string[]
	 */
	static public function dir($directory,$recursive=false,$a=false){
		$directory = self::parse_filename($directory);
		if(is_file($directory)) $directory = dirname($directory);
		if(is_readable($directory) && is_dir($directory)) return new FileIterator($directory,0,$recursive,$a);
		throw new InvalidArgumentException(sprintf('permission denied `%s`',$directory));
	}
	/**
	 * 指定された$directory内のファイル情報をFileとして配列で取得
	 * @param string $directory  検索対象のファイルパス 
	 * @param boolean $recursive 階層を潜って取得するか
	 * @param boolean $a 隠しファイルも参照するか
	 * @return File[]
	 */
	static public function ls($directory,$recursive=false,$a=false){
		$directory = self::parse_filename($directory);
		if(is_file($directory)) $directory = dirname($directory);
		if(is_readable($directory) && is_dir($directory)){
			return new FileIterator($directory,1,$recursive,$a);
		}
		throw new InvalidArgumentException(sprintf('permission denied `%s`',$directory));
	}
	/**
	 * ファイルパスからディレクトリ名部分を取得
	 * @param string $path ファイルパス
	 * @return string
	 */
	static public function dirname($path){
		$dir_name = dirname(str_replace("\\",'/',$path));
		$len = strlen($dir_name);
		return ($len === 1 || ($len === 2 && $dir_name[1] === ':')) ? null : $dir_name;
	}
	/**
	 * フルパスからファイル名部分を取得
	 * @param string $path ファイルパス
	 * @return string
	 */
	static public function basename($path){
		$basename = basename($path);
		$len = strlen($basename);
		return ($len === 1 || ($len === 2 && $basename[1] === ':')) ? null : $basename;
	}
	/**
	 * ディレクトリでユニークなファイル名を返す
	 * @param $dir
	 * @param $prefix
	 * @return string
	 */
	static public function temp_path($dir,$prefix=null){
		if(is_dir($dir)){
			if(substr(str_replace("\\",'/',$dir),-1) != '/') $dir .= '/';
			while(is_file($dir.($path = uniqid($prefix,true))));
			return $path;
		}
		return uniqid($prefix,true);
	}
	/**
	 * パスの前後にスラッシュを追加／削除を行う
	 * @param string $path ファイルパス
	 * @param boolean $prefix 先頭にスラッシュを存在させるか
	 * @param boolean $postfix 末尾にスラッシュを存在させるか
	 * @return string
	 */	
	static public function path_slash($path,$prefix,$postfix){
		if(!empty($path)){
			if($prefix === true){
				if($path[0] != '/') $path = '/'.$path;
			}else if($prefix === false){
				if($path[0] == '/') $path = substr($path,1);
			}
			if($postfix === true){
				if(substr($path,-1) != '/') $path = $path.'/';
			}else if($postfix === false){
				if(substr($path,-1) == '/') $path = substr($path,0,-1);
			}
		}
		return $path;
		
	}
}
/**
 * リクエスト/テンプレートを処理する
 *
 * @author Kazutaka Tokushima
 * @license New BSD License
 * @var string $pattern マッチしたパターン@{"set":false}
 * @var string $name マッチしたマッピング名@{"set":false}
 * @var alnum $theme テーマ
 * @var boolean $secure_map マッチしたマップがセキュアか@{"set":false}
 */
class Flow extends Request{
	protected $pattern;
	protected $name;
	protected $theme;
	protected $secure_map;
	private $redirect;
	private $map_args = array();
	private $url_patterns = array();
	private $match_params = array();
	private $handled_map = array();
	private $request_url;
	private $request_query;
	private $ext_template;
	static private $is_app_cache = false;
	static private $package_media_url = 'package/resources/media';
	static private $vars_xml_base_path;
	static private $secure = true;
	static private $gc_divisor = 100;
	/**
	 * varsを定義したxmlファイルの場所を定義する
	 * @param string $path
	 */
	static public function config_vars_path($path){
		self::$vars_xml_base_path = File::path_slash($path,null,true);
	}
	/**
	 * map[secure]=true時にhttpsにするか
	 * @param boolean $bool httpsにする場合はtrue
	 */
	static public function config_secure($bool){
		self::$secure = $bool;
	}
	/**
	 * キャッシュをするかを定義する
	 * @param boolean $bool キャッシュを作成するか
	 */
	final static public function config_cache($bool){
		self::$is_app_cache = (boolean)$bool;
	}
	/**
	 * gcが実行される確率を定義する
	 * @param integer $gc_divisor gcが実行される確率
	 */
	final static public function config_gc_divisor($gc_divisor){
		self::$gc_divisor = (int)$gc_divisor;
		if(self::$gc_divisor < 1) self::$gc_divisor = 1;
	}
	/**
	 * パッケージのメディアへのURLを定義する
	 * @param string $url パッケージのメディアと認識されるURL
	 */
	final static public function config_package_media_url($url){
		if(substr($url,0,1) == "/") $url = substr($url,1);
		if(substr($url,-1) == "/") $url = substr($url,0,-1);
		self::$package_media_url = $url;
	}
	final protected function __new__(){
		parent::__new__((func_num_args() > 0) ? func_get_arg(0) : null);
		$this->ext_template = new Template();
		$this->request_url = parent::current_url();
		$this->request_query = (parent::query_string() == null) ? null : '?'.parent::query_string();
	}
	final protected function __is_pattern__(){
		return ($this->pattern !== null);
	}
	final protected function __get_theme__(){
		return (empty($this->theme)) ? 'default' : $this->theme;
	}
	/**
	 * クッキーへの書き出し
	 * @param string $name 書き込む変数名
	 * @param int $expire 有効期限 (+ time)
	 * @param string $path パスの有効範囲
	 * @param boolean $subdomain サブドメインでも有効とするか
	 * @param boolean $secure httpsの場合のみ書き出しを行うか
	 */
	public function write_cookie($name,$expire=null,$path=null,$subdomain=false,$secure=false){
		if(empty($path)) $path = preg_replace('/.+:\/\/.+?\//','/',App::url());
		parent::write_cookie($name,$expire,$path,$subdomain,$secure);
	}
	/**
	 * クッキーから削除
	 * 登録時と同条件のものが削除される
	 * @param string $name クッキー名
	 */
	public function delete_cookie($name,$path=null,$subdomain=false,$secure=false){
		if(empty($path)) $path = preg_replace('/.+:\/\/.+?\//','/',App::url());
		parent::delete_cookie($name,$path,$subdomain,$secure);
	}
	/**
	 * mapで定義されたarg値
	 * @param string $name
	 * @param string $default
	 * @return string
	 */
	final protected function map_arg($name,$default=null){
		return (array_key_exists($name,$this->map_args)) ? $this->map_args[$name] : $default;
	}
	/**
	 * 自身のメソッドを呼び出しているURLにリダイレクト
	 * @param string $method_name メソッド名
	 */
	final protected function redirect_method($method_name){
		$args = func_get_args();
		array_unshift($args,'method_url');
		return call_user_func_array(array($this,'redirect_by_map_urls'),$args);
	}
	/**
	 * mapで定義されたarg値の名前をもとにredirectする
	 * @param string $name
	 */
	final protected function redirect_by_map($name){
		$args = func_get_args();
		$name = array_shift($args);
		$arg = $this->map_arg($name,null);
		if($arg === null) $arg = $name;
		if(isset($arg)){
			array_unshift($args,$arg);
			array_unshift($args,'map_url');
			return call_user_func_array(array($this,'redirect_by_map_urls'),$args);
		}
	}
	final private function redirect_by_map_urls($func){
		$args = func_get_args();
		$func = array_shift($args);
		$vars = $params = array();
		foreach($args as $arg){
			if(is_array($arg)){
				$vars = array_merge($vars,$arg);
			}else{
				$params[] = $arg;
			}
		}
		$this->save_current_vars();
		$this->sessions("_redirect_by_map_urls_",true);
		$this->redirect(call_user_func_array(array($this,$func),$params).(empty($vars) ? '' : '?'.Http::query($vars)));
	}
	/**
	 * リダイレクトする
	 * @param stirng $url
	 */
	final public function redirect($url=null){
		if($url === null) $url = $this->redirect;
		$url = File::absolute(App::url(),$url);
		/**
		 * リダイレクトURLを編集する
		 * @param string $url
		 */
		$url = $this->call_module('flow_redirect_url',$url);
		Http::redirect($url);
	}
	/**
	 * テンプレートのメディアURL
	 * @return string
	 */
	final public function media_url(){
		return $this->ext_template->media_url();
	}
	/**
	 * ブロックテンプレートを差し込む、指定されているテンプレートが親となる
	 * @param string $path rt:blockを含むテンプレートのファイルパス
	 */
	protected function put_block($path){
		$this->ext_template->put_block($path);
	}
	/**
	 * phpinfoからattachする
	 * @param string $path
	 */
	final protected function attach_self($path){
		if($this->args() != null){
			Log::disable_display();
			Http::attach(new File($path.$this->args()));
			exit;
		}
	}
	/**
	 * ファイルからattachする
	 * @param string $path
	 */
	final protected function attach_file($path){
		Http::attach(new File($path));
		exit;
	}
	/**
	 * 文字列からattachする
	 * @param string $path
	 * @param string $filename
	 */
	final protected function attach_text($src,$filename=null){
		Http::attach(new File($filename,$src));
		exit;
	}
	/**
	 * リクエストされたURLにリダイレクトする
	 */
	final protected function redirect_self($query=true){
		$this->redirect($this->request_url($query));
	}
	/**
	 * リファラにリダイレクトする
	 */
	final protected function redirect_referer(){
		$this->redirect(Http::referer());
	}
	/**
	 * リクエストされたURLを返す
	 *
	 * @param boolean $query
	 * @return string
	 */
	final public function request_url($query=true){
		return $this->request_url.(($query) ? $this->request_query : '');
	}
	/**
	 * 指定済みのファイルから生成する
	 * @param string $template テンプレートファイルパス
	 * @return string
	 */
	final public function read($template=null){
		if($template !== null) $this->ext_template->filename($template);
		if(!$this->is_vars('t') || !($this->in_vars('t') instanceof Templf)) $this->vars('t',new Templf($this));
		$this->ext_template->cp($this->vars());
		$src = $this->ext_template->read();
		$this->ext_template->rm_vars();
		return $src;
	}
	/**
	 * 出力して終了する
	 * @param string $template テンプレートファイルパス
	 */
	final public function output($template=null){
		print($this->read($template));
		exit;
	}
	/**
	 * モジュールで検証を行う
	 * @return boolean
	 */
	final protected function verify(){
		/**
		 * 検証
		 * @param self $this
		 * @return boolean
		 */
		return ($this->has_module('flow_verify')) ? $this->call_module('flow_verify',$this) : true;
	}
	/**
	 * not found (http status 404)
	 */
	protected function not_found(){
		Http::status_header(404);
		exit;
	}
	/**
	 * ログイン
	 * @arg string $login_redirect ログイン後にリダイレクトされるマップ名
	 */
	public function do_login(){
		if($this->is_login() || $this->silent() || ($this->is_post() && $this->login())){
			$redirect_to = $this->in_sessions('logined_redirect_to');
			$this->rm_sessions('logined_redirect_to');
			/**
			 * ログイン成功時の処理
			 * @param self $this
			 */
			$this->call_module('after_do_login',$this);
			if(!empty($redirect_to)) $this->redirect($redirect_to);
			if($this->map_arg('login_redirect') !== null) $this->redirect_by_map('login_redirect');
		}
		if(!$this->is_login() && $this->is_post()){
			Http::status_header(401);
			if(!Exceptions::has()) Exceptions::add(new LogicException(Gettext::trans('Unauthorized')),'do_login');
		}
	}
	/**
	 * ログアウト
	 * @arg string $logout_redirect ログアウト後にリダイレクトされるマップ名
	 */
	public function do_logout(){
		$this->logout();
		if($this->map_arg('logout_redirect') !== null) $this->redirect_by_map('logout_redirect');
		$this->vars('login',$this->is_login());
	}
	/**
	 * 何もしない
	 * マッピングに利用する
	 */
	final public function noop(){
	}
	/**
	 * 利用不可とする
	 * マッピングに利用する
	 */
	final public function method_not_allowed(){
		Http::status_header(405);
		throw new LogicException(Gettext::trans('Method Not Allowed'));
	}
	/**
	 * 何もしない(内部からのみ呼ばれる)
	 * redirect_by_mapかredirect_methodからのみ呼べる
	 * マッピングに利用する
	 * @arg string $dl_redirect ダイレクトリンクでアクセスされた場合にリダイレクトされるマップ名
	 */
	final public function rg(){
		$bool = $this->in_sessions('_redirect_by_map_urls_');
		$this->rm_sessions('_redirect_by_map_urls_');
		if($bool !== true){
			if($this->map_arg('dl_redirect') !== null) $this->redirect_by_map('dl_redirect');
			throw new LogicException(Gettext::trans('direct link not permitted'));
		}
	}
	/**
	 * テンプレートパスからの絶対パスを返す
	 * @param string $path
	 * @return string
	 */
	final protected function template_path($path=null){
		return $this->ext_template->fm_filename($path);
	}
	/**
	 * 指定したテンプレートが存在するか
	 * @return boolean
	 */
	final protected function has_template(){
		return $this->ext_template->has();
	}
	/**
	 * ハンドリングされたmap
	 * @return string{}
	 */
	final public function handled_map(){
		return $this->handled_map;
	}
	/**
	 * handlerのマップ名を呼び出しているURLを生成する
	 * @param string $map_name マップ名
	 * @return string
	 */
	final public function map_url($map_name){
		$args = func_get_args();
		array_shift($args);
		for($i=sizeof($args);$i>=0;$i--){
			if(isset($this->url_patterns['name'][$map_name][$i])){
				$m = $this->url_patterns['name'][$map_name][$i];
				if($m['secure'] && self::$secure) return App::surl(vsprintf($m['url'],$args));
				return App::url(vsprintf($m['url'],$args));
			}
		}
		throw new LogicException('undef name `'.$map_name.'` url ['.sizeof($args).']');
	}
	/**
	 * handlerでpackageを呼び出してる場合にメソッド名でURLを生成する
	 * 引数を与える事も可能
	 * @param string $method_name メソッド名
	 * @return string
	 */
	final public function package_method_url($method_name){
		$args = func_get_args();
		array_shift($args);
		for($i=sizeof($args);$i>=0;$i--){
			if(isset($this->url_patterns['method'][$method_name][$i])){
				$m = $this->url_patterns['method'][$method_name][$i];
				return App::url(vsprintf($m['url'],$args));
			}
		}
		throw new LogicException('undef name `'.$method_name.'` url ['.sizeof($args).']');
	}
	/**
	 * 指定のメソッド名を利用しているURL
	 * @param string $method_name
	 * @throws LogicException
	 */
	final protected function method_url($method_name){
		$args = func_get_args();
		array_shift($args);
		for($i=sizeof($args);$i>=0;$i--){
			if(isset($this->url_patterns['method'][$method_name][$i])){
				$m = $this->url_patterns['method'][$method_name][$i];
				if($m['secure'] && self::$secure) return App::surl(vsprintf($m['url'],$args));
				return App::url(vsprintf($m['url'],$args));
			}
		}
		throw new LogicException('undef method `'.$method_name.'` url ['.sizeof($args).']');
	}
	private function handler(array $urls=array(),$index=0){
		if(preg_match("/^\/".str_replace("/","\\/",self::$package_media_url)."\/(\d+)\/(\d+)\/(.+)$/",$this->args(),$match)){
			if($match[1] == $index){
				foreach($urls as $args){
					if($match[2] == $args['map_index'] && isset($args['class'])){
						$this->attach_file(File::absolute(Lib::module_root_path(Lib::imported_path(Lib::import($args['class']))).'/resources/media',$match[3]));
					}
				}
				$this->not_found();
			}
			return $this;
		}
		foreach(array_keys($urls) as $pattern){
			if(preg_match("/^".(empty($pattern) ? "" : "\/").str_replace(array("\/","/","__SLASH__"),array("__SLASH__","\/","\/"),$pattern).'[\/]{0,1}$/',$this->args(),$params)){
				Log::debug("match pattern `".$pattern."` ".(empty($urls[$pattern]['name']) ? '' : '['.$urls[$pattern]['name'].']'));
				array_shift($params);
				$map = $urls[$pattern];
				$action = null;
				$this->pattern = $pattern;
				$this->secure_map = ($map['secure'] && self::$secure);
				if($this->secure_map && substr($this->request_url(),0,5) === 'http:' &&
					(!isset($_SERVER['HTTP_X_FORWARDED_HOST']) || (isset($_SERVER['HTTP_X_FORWARDED_PORT']) || isset($_SERVER['HTTP_X_FORWARDED_PROTO'])))
				){
					$this->redirect(preg_replace("/^.+(:\/\/.+)$/","https\\1",$this->request_url()));
				}
				if(!empty($map['redirect']) && empty($map['class'])){
					$this->redirect(($map['redirect'][0] == '/') ? substr($map['redirect'],1) : $map['redirect']);
				}
				if(empty($map['method'])){
					$action = new self('scope='.$map['scope']);
					$action->set($this,$map,$pattern,$params,$urls);
				}else{
					$class = class_exists($map['class']) ? $map['class'] : ((empty($map['class']) && method_exists(__CLASS__,$map['method'])) ? __CLASS__ : null);
					if($class === null && $map['class'] !== null) $class = Lib::import($map['class']);
					if(!method_exists($class,$map['method'])) throw new LogicException($map['class'].'::'.$map['method'].' not found');
					if(!is_subclass_of($class,__CLASS__) && $class !== __CLASS__) throw new LogicException('class is not '.__CLASS__);
					$action = new $class('scope='.$map['scope'].',_init_=false');
					foreach(array('redirect','name') as $k) $action->{$k} = $map[$k];
					$action->set($this,$map,$pattern,$params,$urls,$index,true);
				}
				$this->cp($action->vars());
				$this->cp(self::execute_var($map['vars']));
				if(isset($map['vars_xml'])){
					$vars = array();
					if(!isset(self::$vars_xml_base_path)) self::$vars_xml_base_path = App::path('resources/vars/');
					$varsf = File::absolute(self::$vars_xml_base_path,$map['vars_xml']);					
					if(!self::$is_app_cache || !Store::has($varsf)){
						if(Tag::setof($var_tag,File::read($varsf),'vars')) foreach($var_tag->in('var') as $v) $vars[] = self::parse_var($v);
						if(self::$is_app_cache) Store::set($varsf,$vars);
					}
					if(self::$is_app_cache) $vars = Store::get($varsf);
					$this->cp(self::execute_var($vars));
				}
				if($this->ext_template->is_filename()) $this->vars('t',new Templf($action));
				break;
			}
		}
		return $this;
	}
	private function set(&$module_obj,$map,$pattern,$params,$urls,$index=0,$method_call=false){
		$this->copy_module($module_obj);
		$this->cp($module_obj->vars());
		$this->pattern = $pattern;
		$this->match_params = $params;
		$this->handled_map = $map;
		$this->name = $map['name'];
		$this->map_args = $map['args'];
		foreach($urls as $p => $c){
			$count = 0;
			if(!empty($p)) $p = substr(preg_replace_callback("/([^\\\\])(\(.*?[^\\\\]\))/",create_function('$m','return $m[1]."%s";')," ".$p,-1,$count),1);
			if($c['class'] === $map['class'] && isset($c['method'])) $this->url_patterns['method'][$c['method']][$count] = array('url'=>$p,'secure'=>$c['secure']);
			if(!empty($c['name'])) $this->url_patterns['name'][$c['name']][$count] = array('url'=>$p,'secure'=>$c['secure']);
			if($c['method'] === 'do_login') $this->url_patterns['login'] = array('url'=>$p,'secure'=>$c['secure']);
		}
		foreach($map['modules'] as $module) $this->add_module(self::import_instance($module));
		/**
		 * 初期化
		 * @param self $this
		 */
		$this->call_module('init_flow_handle',$this);
		if(method_exists($this,'__init__')) $this->__init__();
		if(!$this->is_login() && $this->a('user','require') === true && $map['method'] !== 'do_login'){
			/**
			 * user[require=true]で未ログイン時のログイン処理の前処理
			 * @param self $this
			 */
			$this->call_module('before_login_required',$this);
			$this->login_required();
		}
		/**
		 * 前処理
		 * @param self $this
		 */
		$this->call_module('before_flow_handle',$this);
		if($method_call) call_user_func_array(array($this,$map['method']),$params);
		if(!empty($map['template'])){
			$template = $map['template'];
			if(isset($map['theme_path'])){
				$template = File::path_slash($map['theme_path'],false,true).File::path_slash($this->theme(),false,true).$template;
				$this->ext_template->media_url(File::path_slash($map['theme_path'],false,true).File::path_slash($this->theme(),false,false));
			}
			if(isset($map['template_path'])){
				$template = File::path_slash($map['template_path'],false,true).$template;
			}
			$this->ext_template->filename($template);
		}
		if($method_call){
			if($this->ext_template->filename() === null){
				$ref = new ReflectionObject($this);
				$file = dirname($ref->getFileName()).'/resources/templates/'.$map['method'].'.html';
				if(is_file($file)){
					$this->ext_template->filename($file);
					$this->ext_template->media_url(App::url(self::$package_media_url.'/'.$index.'/'.$urls[$pattern]['map_index']));
				}
			}
		}
		/**
		 * 後処理
		 * @param self $this
		 */
		$this->call_module('after_flow_handle',$this);
		$module_obj->ext_template = $this->ext_template;
		$module_obj->ext_template->copy_module($this);
		$module_obj->ext_template->secure($module_obj->secure_map);
	}
	/**
	 * ログインを必須とする
	 * @param string $redirect_to リダイレクト先
	 */
	protected function login_required($redirect_to=null){
		if(!$this->is_login()){
			if(!isset($this->url_patterns['login'])) throw new LogicException('undef map `do_login`');
			if(!isset($redirect_to)) $redirect_to = $this->in_sessions('logined_redirect_to',$this->request_url());
			$this->sessions('logined_redirect_to',$redirect_to);
			$this->save_current_vars();
			$this->redirect(($this->url_patterns['login']['secure'] && self::$secure) ? App::surl($this->url_patterns['login']['url']) : App::url($this->url_patterns['login']['url']));			
		}
	}
	/**
	 * xml定義からhandlerを処理する
	 * @param string $file アプリケーションXMLのファイルパス
	 */
	final static public function load($file=null){
		if(App::branch() === null) App::branch(new File($file));
		if(!self::$is_app_cache || !Store::has($file)){
			$parse_app = self::parse_app($file);
			if(self::$is_app_cache) Store::set($file,$parse_app);
		}
		if(self::$is_app_cache) $parse_app = Store::get($file);
		if(empty($parse_app['apps'])) throw new LogicException('undef app');
		$self = null;
		$app_result = null;
		$app_index = 0;
		$executed = false;
		foreach($parse_app['apps'] as $app){
			switch($app['type']){
				case 'handle':
					$self = new self('_request_=false');
					if(!empty($app['session'])){
						if(Object::C(Request)->has_module('session_read')) throw new LogicException('session module already exists');
						$session_class = Lib::import($app['session']);
						Object::C(Request)->add_module(new $session_class);
					}
					foreach($app['modules'] as $module) $self->add_module(self::import_instance($module));
					try{
						/**
						 * 開始処理
						 * @param self $self
						 */
						$self->call_module('begin_flow_handle',$self);
						if($self->handler($app['maps'],$app_index++)->is_pattern()){
							$executed = true;
							$self->cp(self::execute_var($app['vars']));
							if(Exceptions::has()) $self->handle_exception();
							if(Object::C(__CLASS__)->has_module('flow_handle_check_result')){
								list($result_vars,$result_url) = array($self->ext_template->vars(),App::url($self->args()));
								/**
								 * 結果のチェック
								 * @param mixed[] $result_vars セットされた変数
								 * @param string $result_url リクエストされたURL
								 */
								Object::C(__CLASS__)->call_module('flow_handle_check_result',$result_vars,$result_url);
							}
							if(rand(1,self::$gc_divisor) === self::$gc_divisor){
								/**
								 * ランダムに実行する処理
								 * @param aaa
								 */
								$self->call_module('flow_gc',$this);
							}
							if($self->ext_template->filename() !== null){
								if(!$self->ext_template->is_filename()) throw new LogicException($self->ext_template->filename().' not found');
								$self->print_template($self->read());
							}else if($self->has_module('flow_another_output')){
								/**
								 * Flow処理でtemplateが指定されていない場合に別の出力方法
								 * @param string $src 処理後の展開されたソース
								 * @param self $self
								 */
								$self->call_module('flow_another_output',$self);
							}else{
								if(Exceptions::has()){
									$self->handle_exception_xml();
								}else{
									Log::disable_display();
									Http::send_header('Content-Type: application/xml');
									$tag = Tag::xml('result');
									foreach($self->vars() as $k => $v){
										if(!$self->is_cookie($k)) $tag->add(Tag::xml($k,$v));
									}
									$tag->output();
								}
							}
							exit;
						}
					}catch(Exception $e){
						if($e instanceof RuntimeException) throw $e;
						if(!($e instanceof Exceptions)) Exceptions::add($e);
						$self->handle_exception();
						if(isset($app['on_error']['status'])) Http::status_header((int)$app['on_error']['status']);
						if(isset($app['on_error']['redirect'])){
							$self->redirect(($app['on_error']['redirect'][0] == '/') ? substr($app['on_error']['redirect'],1) : $app['on_error']['redirect']);
						}else if(isset($app['on_error']['template'])){
							$action = $self;
							if($self->is_pattern()){
								$action = new self('scope='.$app['maps'][$self->pattern()]['scope']);
								$action->set($self,$app['maps'][$self->pattern()],$self->pattern(),array(),$app['maps']);
							}
							$action->ext_template->filename($app['on_error']['template']);
							$action->print_template($action->read());
							exit;
						}
						$self->handle_exception_xml();
					}
					break;
				case 'invoke':
					$executed = true;
					if(!isset($app['class']) && !is_object($app_result)) throw new LogicException('undef invoke class');
					$class_name = isset($app['class']) ? Lib::import($app['class']) : get_class($app_result);
					$invoke_obj = isset($app['class']) ? new $class_name() : $app_result;
					foreach($app['modules'] as $module) $invoke_obj->add_module(self::import_instance($module));
					$ref_class = new ReflectionClass($class_name);
					foreach($app['methods'] as $method){
						$invoker = ($ref_class->getMethod($method['method'])->isStatic()) ? $class_name : $invoke_obj;
						$args = array();
						foreach($method['args'] as $arg){
							if($arg['type'] === 'result'){
								$args[] = &$app_result;
							}else{
								$args[] = $arg['value'];
							}
						}
						$app_result = call_user_func_array(array($invoker,$method['method']),$args);
					}
					break;
			}
		}
		if(!$executed){
			if($parse_app["nomatch_redirect"] !== null) Http::redirect(App::url($parse_app["nomatch_redirect"]));
			if($parse_app["nomatch_template"] !== null){
				$self = new self();
				$self->ext_template->filename($parse_app["nomatch_template"]);
				$self->print_template($self->read());
			}
			Http::status_header(404);
		}
		exit;
	}
	private function handle_exception_xml(){
		Log::disable_display();
		Http::send_header('Content-Type: application/xml');
		$tag = Tag::xml('error');
		foreach(Exceptions::groups() as $group){
			foreach(Exceptions::gets($group) as $e){
				$e_xml = new Tag('message',$e->getMessage());
				$e_xml->add('group',$group);
				$e_xml->add('type',get_class($e));
				$tag->add($e_xml);
			}
		}
		$tag->output();
	}
	private function handle_exception($exception=null){
		if($exception === null) $exception = Exceptions::gets();
		/**
		 * Flow処理で例外が発生した場合に実行する処理
		 * @param Exception[] $exception
		 * @param self $this
		 */
		$this->call_module('flow_handle_exception',$exception,$this);
	}
	private function print_template($src){
		/**
		 * テンプレート出力の前処理
		 * @param string $src 処理後の展開されたソース
		 * @param self $this
		 */
		$this->call_module('before_flow_print_template',$src,$this);
		print($src);
	}
	static private function expand_method_map($package,$package_url,$package_name){
		$class = Lib::import($package);
		if($package_url === null) $package_url = $class;
		if($package_name === null) $package_name = $class;
		$maps = array();
		$ref = new ReflectionClass($class);
		foreach($ref->getMethods() as $method){
			if($method->isPublic() && is_subclass_of($method->getDeclaringClass()->getName(),__CLASS__)){
				if(!$method->isStatic()){
					$url = (($method->getName() == 'index') ? '' : $method->getName()).str_repeat("/(.+)",$method->getNumberOfRequiredParameters());
					for($i=0;$i<=$method->getNumberOfParameters()-$method->getNumberOfRequiredParameters();$i++){
						$map = new Tag('map');
						$map->add('class',$package);
						$map->add('method',$method->getName());
						$map->add('url',(empty($package_url) ? '' : $package_url.'/').$url);
						$map->add('name',(empty($package_name) ? '' : $package_name.'/').$method->getName());
						$maps[] = $map;
						$url .= '/(.+)';
					}
				}
			}
		}
		return $maps;
	}
	/**
	 * application アプリケーションXMLをパースする
	 * 
	 * @param string $file アプリケーションXMLのファイルパス
	 * @return string{}
	 */
	static public function parse_app($file){
		$apps = array();
		$app_nomatch_redirect = $app_nomatch_template = null;
		if(Tag::setof($tag,Tag::uncomment(File::read($file)),'app')){
			$app_ns = $tag->in_param('ns');
			$app_nomatch_redirect = File::path_slash($tag->in_param('nomatch_redirect'),false,null);
			$app_nomatch_template = File::path_slash($tag->in_param('nomatch_template'),false,null);
			$handler_count = 0;
			$invoke_count = 0;
			foreach($tag->in(array('invoke','handler')) as $handler){
				switch(strtolower($handler->name())){
					case 'handler':
						if(!$handler->is_param('hide') 
							|| (App::mode() !== 'release' && App::mode() !== 'stage')
							|| (App::mode() === 'release' && $handler->in_param('hide') !== 'release' && $handler->in_param('hide') !== 'both')
							|| (App::mode() === 'stage' && $handler->in_param('hide') !== 'stage' && $handler->in_param('hide') !== 'both')
						){
							if($handler->is_param('class')){
								$hbool = true;
								foreach($handler->in(array('maps','map')) as $tag){
									$hbool = false;
									break;
								}
								if($hbool){
									foreach(self::expand_method_map($handler->in_param('class'),$handler->in_param('url'),$handler->in_param('name')) as $map) $handler->add($map);
									$handler->rm_param('url');
									$handler->rm_param('class');
								}
							}
							$handler_name = (empty($app_ns)) ? str_replace(App::path(),'',$file).$handler_count++ : $app_ns;
							$maps = $modules = $vars = array();
							$handler_url = File::path_slash($handler->in_param('url'),false,true);
							$map_index = 0;
							$template_path = $handler->is_param('template_path') ? File::path_slash($handler->in_param('template_path'),false,false) : null;
							$handler_theme_path = $handler->in_param('theme_path') ? File::path_slash($handler->in_param('theme_path'),false,false) : null;
							foreach($handler->in(array('maps','map','var','module')) as $tag){
								switch(strtolower($tag->name())){
									case 'map':
										$url = File::path_slash($handler_url.File::path_slash($tag->in_param('url'),false,false),false,false);
										$theme_path = ($tag->is_param('theme_path') || isset($handler_theme_path)) ? File::path_slash($handler_theme_path,false,true).File::path_slash($tag->in_param('theme_path'),false,false) : null;
										$map = self::parse_map($tag,$tag->is_param('url'),$url,$template_path,$theme_path,$handler_name,null,null,null,$map_index++);
										$maps[$map['url']] = $map;
										break;
									case 'maps':
										$maps_map = $maps_module = array();
										$maps_template_path = ($tag->is_param('template_path') || isset($template_path)) ? File::path_slash($template_path,false,true).File::path_slash($tag->in_param('template_path'),false,false) : null;
										$maps_theme_path = ($tag->is_param('theme_path') || isset($handler_theme_path)) ? File::path_slash($handler_theme_path,false,true).File::path_slash($tag->in_param('theme_path'),false,false) : null;
										if($tag->is_param('class')){
											$hbool = true;
											foreach($tag->in('map') as $mtag){
												$hbool = false;
												break;
											}
											if($hbool){
												foreach(self::expand_method_map($tag->in_param('class'),$tag->in_param('url'),$tag->in_param('name')) as $m) $tag->add($m);
												$tag->rm_param('url');
												$tag->rm_param('class');
											}
										}
										foreach($tag->in(array('map','module')) as $m){
											if($m->name() == 'map'){
												$url = File::path_slash($handler_url.File::path_slash($tag->in_param('url'),false,true).File::path_slash($m->in_param('url'),false,false),false,false);
												$theme_path = ($m->is_param('theme_path') || isset($maps_theme_path)) ? File::path_slash($maps_theme_path,false,true).File::path_slash($m->in_param('theme_path'),false,false) : null;
												$map = self::parse_map($m,$m->is_param('url'),$url,$maps_template_path,$theme_path,$handler_name,$tag->in_param('class'),$tag->in_param('secure'),$tag->in_param('update'),$map_index++);
												$maps_map[$map['url']] = $map;
											}else{
												$maps_module[] = self::parse_module($m);
											}
										}
										if(!empty($maps_module)){
											foreach($maps_map as $k => $v) $maps_map[$k]['modules'] = array_merge($maps_map[$k]['modules'],$maps_module);
										}
										$maps = array_merge($maps,$maps_map);
										break;
									case 'var': $vars[] = self::parse_var($tag); break;
									case 'module': $modules[] = self::parse_module($tag); break;
								}
							}
							$verify_maps = array();
							foreach($maps as $m){
								if(!empty($m['name'])){
									if(isset($verify_maps[$m['name']])) Exceptions::add(new LogicException("name `".$m['name']."` with this map already exists."));
									$verify_maps[$m['name']] = true;
								}
							}
							Exceptions::throw_over();
							$urls = $maps;
							krsort($urls);
							$sort_maps = $surls = array();
							foreach(array_keys($urls) as $url) $surls[$url] = strlen(preg_replace("/[\W]/","",$url));
							arsort($surls);
							krsort($surls);
							foreach(array_keys($surls) as $url) $sort_maps[$url] = $maps[$url];
							$apps[] = array('type'=>'handle'
											,'maps'=>$sort_maps
											,'modules'=>$modules
											,'vars'=>$vars
											,'session'=>$handler->in_param('session')
											,'on_error'=>array('status'=>$handler->in_param('error_status')
																,'template'=>$handler->in_param('error_template')
																,'redirect'=>$handler->in_param('error_redirect'))
											);
						}
						break;
					case 'invoke':
						$targets = $methods = $args = $modules = array();
						if($handler->is_param('method')){
							$targets[] = $handler->add('name',$handler->in_param('method'));
						}else{
							$targets = $handler->in_all('method');
						}
						foreach($targets as $method_tag){
							$args = array();
							foreach($method_tag->in(array('arg','result')) as $arg) $args[] = array('type'=>$arg->name(),'value'=>$arg->in_param('value',Text::plain($arg->value())));
							$methods[] = array('method'=>$method_tag->in_param('name'),'args'=>((empty($args) && $handler->is_param('class') && $invoke_count > 0) ? array(array('type'=>'result','value'=>null)) : $args));
						}
						foreach($handler->in('module') as $m) $modules[] = self::parse_module($m);
						$apps[] = array('type'=>'invoke','class'=>$handler->in_param('class'),'methods'=>$methods,'modules'=>$modules);
						$invoke_count++;
						break;
				}
			}
		}
		return array("nomatch_redirect"=>$app_nomatch_redirect,"nomatch_template"=>$app_nomatch_template,"apps"=>$apps);
		
		
		
	}
	static private function parse_map(Tag $map_tag,$is_url,$url,$template_path,$theme_path,$scope,$base_class,$secure,$update,$map_index){
		$params = $args = $vars = $modules = array();
		if(!$map_tag->is_param('class')) $map_tag->param('class',$base_class);
		$params['url'] = $is_url ? $url : null;
		$params['scope'] = $scope;
		$params['map_index'] = $map_index;
		$params['redirect'] = File::path_slash($map_tag->in_param('redirect'),false,false);
		$params['template'] = File::path_slash($map_tag->in_param('template'),false,false);
		$params['vars_xml'] = File::path_slash($map_tag->in_param('vars'),false,false);
		$params['secure'] = ($map_tag->in_param('secure',$secure) === 'true');
		$params['update'] = strtolower($map_tag->in_param('update',$update));
		$params['template_path'] = (empty($params['template'])) ? null : $template_path;
		$params['theme_path'] = (empty($params['template'])) ? null : $theme_path;
		if(empty($params['update'])) $params['update'] = 'none';
		switch($params['update']){
			case 'none':
			case 'get':
			case 'post':
			case 'both': break;
			default: Exceptions::add(new InvalidArgumentException('map `'.$params['update'].'` update type not found'));
		}
		foreach(array('class','method','name') as $c) $params[$c] = $map_tag->in_param($c);
		if(isset($params['name'])){
			if(isset($params['class']) && !isset($params['method'])) $params['method'] = $params['name'];
			if(!isset($params['url'])) $params['url'] = $url.$params['name'];
		}
		foreach($map_tag->in('module') as $t) $modules[] = self::parse_module($t);
		foreach($map_tag->in('var') as $t) $vars[] = self::parse_var($t);
		foreach($map_tag->in('arg') as $a) $args[$a->in_param('name')] = $a->in_param('value',$a->value());
		list($params['vars'],$params['modules'],$params['args']) = array($vars,$modules,$args);
		if(!empty($params['class']) && empty($params['method'])) Exceptions::add(new InvalidArgumentException('map `'.$map_tag->plain().'` method not found'));
		return $params;
	}
	static private function parse_module(Tag $tag){
		if(!$tag->is_param('class')) throw new LogicException('module class not found');
		$args = array();
		foreach($tag->in('arg') as $arg){
			$args[] = $arg->in_param('value',Text::plain($arg->value()));
		}
		return array($tag->in_param('class'),$args);
	}
	static private function import_instance($module){
		list($class_name,$args) = $module;
		$class_name = Lib::import($class_name);
		if(empty($args)) return new $class_name;
		$ref = new ReflectionClass($class_name);
		return $ref->newInstanceArgs($args);
	}
	static private function parse_var(Tag $tag){
		if($tag->is_param('class')){
			$var_value = array();
			foreach($tag->in('arg') as $arg) $var_value[] = $arg->in_param('value',Text::plain($arg->value()));
		}else{
			$var_value = $tag->in_param('value',Text::plain($tag->value()));
		}
		return array('name'=>$tag->in_param('name'),'value'=>$var_value,'class'=>$tag->in_param('class'),'method'=>$tag->in_param('method'));
	}
	static private function execute_var($vars){
		$results = array();
		foreach($vars as $var){
			$name = $var['name'];
			$var_value = $var['value'];
			if(isset($var['class'])){
				$r = new ReflectionClass(Lib::import($var['class']));
				if(empty($var['method'])){
					$var_value = (empty($var_value) ? $r->newInstance() : $r->newInstance($var_value));
				}else{
					try{
						if($r->getMethod($var['method'])->isStatic()){
							$var_value = call_user_func_array(array($r->getName(),$var['method']),array());
						}else{
							throw new ReflectionException();
						}
					}catch(ReflectionException $e){
						$var_value = call_user_func_array(array($r->newInstance(),$var['method']),array());
					}
				}
			}
			if(isset($results[$name])){
				if(!is_array($results[$name])) $results[$name] = array($results[$name]);
				$results[$name][] = $var_value;
			}else{
				$results[$name] = $var_value;
			}
		}
		return $results;
	}
}
/**
 * HTTP関連処理
 * @author Kazutaka Tokushima
 * @license New BSD License
 * @see http://jp2.php.net/manual/ja/context.ssl.php
 * @var mixed{} $vars query文字列で渡す値
 * @var string{} $header 実行時に渡すヘッダ情報
 * @var boolean $status_redirect ステータスコードがリダイレクトの場合にリダイレクトするか
 * @var boolean $query_array 配列をquery文字列で展開するか
 * @var number $status 返却されたステータスコード @{"set": false}
 * @var string $body 内容 @{"set": false}
 * @var string $head レスポンスのヘッダ情報@{"set": false}
 * @var string $url アクセスしたURL @{"set": false}
 * @var string $encode 文字エンコード
 * @var string $agent アクセスするユーザエージェント
 * @var integer $timeout アクセスタイムアウト
 * @var text $raw RAWデータで渡す値
 * @var text $cmd 実行されたコマンド@{"set":false}
 */
class Http extends Object{
	static private $send_header;
	static private $status_header;
	private $user;
	private $password;
	protected $body;
	protected $head;
	protected $url;
	protected $status = 200;
	protected $encode;
	protected $status_redirect = true;
	protected $query_array = true;
	private $form = array();
	protected $agent;
	protected $timeout = 30;
	protected $vars = array();
	protected $raw;
	protected $cmd;
	protected $header = array();
	private $cookie = array();
	protected $api_url;
	protected $api_key;
	protected $api_key_name = 'api_key';
	/**
	 * URLが有効かを調べる
	 *
	 * @param string $url 確認するURL
	 * @return boolean
	 */
	static public function is_url($url){
		try{
			$self = new self();
			$result = $self->request($url,'HEAD',array(),array(),null,false);
			return ($result->status === 200);
		}catch(Exception $e){}
		return false;
	}
	/**
	 * URLのステータスを確認する
	 * @param string $url 確認するURL
	 * @return integer
	 */
	static public function request_status($url){
		try{
			$self = new self();
			$result = $self->request($url,'HEAD',array(),array(),null,false);
			return $result->status;
		}catch(Exception $e){}
		return 404;
	}
	/**
	 * ヘッダ情報をハッシュで取得する
	 * @return string{}
	 */
	public function explode_head(){
		$result = array();
		foreach(explode("\n",$this->head) as $h){
			if(preg_match("/^(.+?):(.+)$/",$h,$match)) $result[trim($match[1])] = trim($match[2]);
		}
		return $result;
	}
	protected function __cp__($obj){
		if(!empty($obj)){
			if($obj instanceof Object){
				foreach($obj->prop_values() as $name => $value) $this->vars[$name] = $obj->{'fm_'.$name}();
			}else if(is_array($obj)){
				foreach($obj as $name => $value){
					if(ctype_alpha($name[0])) $this->vars[$name] = $value;
				}
			}else{
				throw new InvalidArgumentException('cp');
			}
		}
	}
	/**
	 * URL情報を返す
	 *
	 * @param string $url パースするURL、$base_urlと結合できる
	 * @param string $base_url $urlのベースとなるURL
	 * @return Object(url,full_url,scheme,host,port,path,fragment,query)
	 */
	static public function parse_url($url,$base_url=null){
		$furl = (!empty($base_url)) ? File::absolute($base_url,$url) : $url;
		$parse_url = parse_url($furl);
		$result = new Object();
		$result->url = $url;
		$result->full_url = $furl;
		$result->scheme = (isset($parse_url['scheme']) ? $parse_url['scheme'] : 'http');
		$result->host = (isset($parse_url['host']) ? $parse_url['host'] : null);
		$result->port = (isset($parse_url['port']) ? $parse_url['port'] : 80);
		$result->path = (isset($parse_url['path']) ? $parse_url['path'] : "/");
		$result->fragment = (isset($parse_url['fragment']) ? $parse_url['fragment'] : null);
		$result->query = array();
		if(isset($parse_url['query'])){
			foreach(explode('&',$parse_url['query']) as $q){
				$key_value = explode("=",$q,2);
				if(sizeof($key_value) == 1) $key_value = array($key_value[0],null);
				list($key,$value) = $key_value;
				$result->query[$key] = $value;
			}
		}
		return $result;
	}
	private function build_url($url){
		if($this->api_key !== null) $this->vars($this->api_key_name,$this->api_key);
		if($this->api_url !== null) return File::absolute($this->api_url,(substr($url,0,1) == '/') ? substr($url,1) : $url);
		return $url;
	}
	/**
	 * getでアクセスする
	 * @param string $url アクセスするURL
	 * @param boolean $form formタグの解析を行うか
	 * @return $this
	 */
	public function do_get($url=null,$form=true){
		return $this->browse($this->build_url($url),'GET',$form);
	}
	/**
	 * postでアクセスする
	 * @param string $url アクセスするURL
	 * @param boolean $form formタグの解析を行うか
	 * @return $this
	 */
	public function do_post($url=null,$form=true){
		return $this->browse($this->build_url($url),'POST',$form);
	}
	/**
	 * ダウンロードする
	 *
	 * @param string $url アクセスするURL
	 * @param string $download_path ダウンロード先のファイルパス
	 * @return $this
	 */
	public function do_download($url=null,$download_path){
		return $this->browse($this->build_url($url),'GET',false,$download_path);
	}
	/**
	 * HEADでアクセスする formの取得はしない
	 * @param string $url アクセスするURL
	 * @return $this
	 */
	public function do_head($url=null){
		return $this->browse($this->build_url($url),'HEAD',false);
	}
	/**
	 * PUTでアクセスする
	 * @param string $url アクセスするURL
	 * @return $this
	 */
	public function do_put($url=null){
		return $this->browse($this->build_url($url),'PUT',false);
	}
	/**
	 * DELETEでアクセスする
	 * @param string $url アクセスするURL
	 * @return $this
	 */
	public function do_delete($url=null){
		return $this->browse($this->build_url($url),'DELETE',false);
	}
	/**
	 * 指定の時間から更新されているか
	 * @param string $url アクセスするURL
	 * @param integer $time 基点となる時間
	 * @return string
	 */
	public function do_modified($url,$time){
		$this->header('If-Modified-Since',date('r',$time));
		return $this->browse($this->build_url($url),'GET',false)->body();
	}
	/**
	 * Basic認証
	 * @param string $user ユーザ名
	 * @param string $password パスワード
	 */
	public function auth($user,$password){
		$this->user = $user;
		$this->password = $password;
	}
	/**
	 * WSSE認証
	 * @param string $user ユーザ名
	 * @param string $password パスワード
	 */
	public function wsse($user,$password){
		$nonce = sha1(md5(time().rand()),true);
		$created = date("Y-m-d\TH:i:s\Z",time() - date('Z'));
		$this->header('X-WSSE',sprintf("UsernameToken Username=\"%s\", PasswordDigest=\"%s\", Nonce=\"%s\", Created=\"%s\"",
					$user,base64_encode(sha1($nonce.$created.$password,true)),base64_encode($nonce),$created));
	}
	private function browse($url,$method,$form=true,$download_path=null){
		$cookies = '';
		$variables = '';
		$headers = $this->header;
		$cookie_base_domain = preg_replace("/^[\w]+:\/\/(.+)$/","\\1",$url);
		foreach($this->cookie as $domain => $cookie_value){
			if(strpos($cookie_base_domain,$domain) === 0 || strpos($cookie_base_domain,(($domain[0] == '.') ? $domain : '.'.$domain)) !== false){
				foreach($cookie_value as $name => $value){
					if(!$value['secure'] || ($value['secure'] && substr($url,0,8) == 'https://')) $cookies .= sprintf("%s=%s; ",$name,$value['value']);
				}
			}
		}
		if(!empty($cookies)) $headers["Cookie"] = $cookies;
		if(!empty($this->user)){
			if(preg_match("/^([\w]+:\/\/)(.+)$/",$url,$match)){
				$url = $match[1].$this->user.":".$this->password."@".$match[2];
			}else{
				$url = "http://".$this->user.":".$this->password."@".$url;
			}
		}
		if($this->is_raw()) $headers['rawdata'] = $this->raw();
		$result = $this->request($url,$method,$headers,$this->vars,$download_path,false);
		$this->cmd = $result->cmd;
		$this->head = $result->head;
		$this->url = $result->url;
		$this->status = $result->status;
		$this->encode = $result->encode;
		$this->body = ($this->encode !== null) ? mb_convert_encoding($result->body,"UTF-8",$this->encode) : $result->body;
		$this->form = array();
		if(preg_match_all("/Set-Cookie:[\s]*(.+)/i",$this->head,$match)){
			$unsetcookie = $setcookie = array();
			foreach($match[1] as $cookies){
				$cookie_name = $cookie_value = $cookie_domain = $cookie_path = $cookie_expires = null;
				$cookie_domain = $cookie_base_domain;
				$cookie_path = "/";
				$secure = false;
				foreach(explode(";",$cookies) as $cookie){
					$cookie = trim($cookie);
					if(strpos($cookie,"=") !== false){
						list($name,$value) = explode("=",$cookie,2);
						$name = trim($name);
						$value = trim($value);
						switch(strtolower($name)){
							case 'expires': $cookie_expires = ctype_digit($value) ? (int)$value : strtotime($value); break;
							case 'domain': $cookie_domain = preg_replace("/^[\w]+:\/\/(.+)$/","\\1",$value); break;
							case 'path': $cookie_path = $value; break;
							default:
								$cookie_name = $name;
								$cookie_value = $value;
						}
					}else if(strtolower($cookie) == "secure"){
						$secure = true;
					}
				}
				$cookie_domain = substr(File::absolute('http://'.$cookie_domain,$cookie_path),7);
				if($cookie_expires !== null && $cookie_expires < time()){
					if(isset($this->cookie[$cookie_domain][$cookie_name])) unset($this->cookie[$cookie_domain][$cookie_name]);
				}else{
					$this->cookie[$cookie_domain][$cookie_name] = array('value'=>$cookie_value,'expires'=>$cookie_expires,'secure'=>$secure);
				}
			}
		}
		$this->vars = array();
		if($this->status_redirect){
			if(isset($result->redirect)) return $this->browse($result->redirect,'GET',$form,$download_path);
			if(Tag::setof($tag,$result->body,'head')){
				foreach($tag->in('meta') as $meta){
					if(strtolower($meta->in_param('http-equiv')) == 'refresh'){
						if(preg_match("/^[\d]+;url=(.+)$/i",$meta->in_param('content'),$refresh)){
							$this->vars = array();
							return $this->browse(File::absolute(dirname($url),$refresh[1]),'GET',$form,$download_path);
						}
					}
				}
			}
		}
		if($form) $this->parse_form();
		return $this;
	}
	private function parse_form(){
		$tag = Tag::anyhow($this->body);
		foreach($tag->in('form') as $key => $formtag){
			$form = new stdClass();
			$form->name = $formtag->in_param('name',$formtag->in_param('id',$key));
			$form->action = File::absolute($this->url,$formtag->in_param('action',$this->url));
			$form->method = strtolower($formtag->in_param('method','get'));
			$form->multiple = false;
			$form->element = array();
			foreach($formtag->in('input') as $count => $input){
				$obj = new stdClass();
				$obj->name = $input->in_param('name',$input->in_param('id','input_'.$count));
				$obj->type = strtolower($input->in_param('type','text'));
				$obj->value = Text::htmldecode($input->in_param('value'));
				$obj->selected = ('selected' === strtolower($input->in_param('checked',$input->in_attr('checked'))));
				$obj->multiple = false;
				$form->element[] = $obj;
			}
			foreach($formtag->in('textarea') as $count => $input){
				$obj = new stdClass();
				$obj->name = $input->in_param('name',$input->in_param('id','textarea_'.$count));
				$obj->type = 'textarea';
				$obj->value = Text::htmldecode($input->value());
				$obj->selected = true;
				$obj->multiple = false;
				$form->element[] = $obj;
			}
			foreach($formtag->in('select') as $count => $input){
				$obj = new stdClass();
				$obj->name = $input->in_param('name',$input->in_param('id','select_'.$count));
				$obj->type = 'select';
				$obj->value = array();
				$obj->selected = true;
				$obj->multiple = ('multiple' == strtolower($input->param('multiple',$input->attr('multiple'))));
				foreach($input->in('option') as $count => $option){
					$op = new stdClass();
					$op->value = Text::htmldecode($option->in_param('value',$option->value()));
					$op->selected = ('selected' == strtolower($option->in_param('selected',$option->in_attr('selected'))));
					$obj->value[] = $op;
				}
				$form->element[] = $obj;
			}
			$this->form[] = $form;
		}
	}
	/**
	 * formをsubmitする
	 * @param string $form FORMタグの名前、または順番
	 * @param string $submit 実行するINPUTタグ(type=submit)の名前
	 * @return $this
	 */
	public function submit($form=0,$submit=null){
		foreach($this->form as $key => $f){
			if($f->name === $form || $key === $form){
				$form = $key;
				break;
			}
		}
		if(isset($this->form[$form])){
			$inputcount = 0;
			$onsubmit = ($submit === null);
			foreach($this->form[$form]->element as $element){
				switch($element->type){
					case 'hidden':
					case 'textarea':
						if(!array_key_exists($element->name,$this->vars)){
							$this->vars($element->name,$element->value);
						}
						break;
					case 'text':
					case 'password':
						$inputcount++;
						if(!array_key_exists($element->name,$this->vars)) $this->vars($element->name,$element->value); break;
						break;
					case 'checkbox':
					case 'radio':
						if($element->selected !== false){
							if(!array_key_exists($element->name,$this->vars)) $this->vars($element->name,$element->value);
						}
						break;
					case 'submit':
					case 'image':
						if(($submit === null && $onsubmit === false) || $submit == $element->name){
							$onsubmit = true;
							if(!array_key_exists($element->name,$this->vars)) $this->vars($element->name,$element->value);
							break;
						}
						break;
					case 'select':
						if(!array_key_exists($element->name,$this->vars)){
							if($element->multiple){
								$list = array();
								foreach($element->value as $option){
									if($option->selected) $list[] = $option->value;
								}
								$this->vars($element->name,$list);
							}else{
								foreach($element->value as $option){
									if($option->selected){
										$this->vars($element->name,$option->value);
									}
								}
							}
						}
						break;
					case "button":
						break;
				}
			}
			if($onsubmit || $inputcount == 1){
				return ($this->form[$form]->method == 'post') ?
							$this->browse($this->form[$form]->action,'POST') :
							$this->browse($this->form[$form]->action,'GET');
			}
		}
		return $this;
	}
	/**
	 * リファラを取得する
	 *
	 * @return string
	 */
	static public function referer(){
		return (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'],'://') !== false) ? $_SERVER['HTTP_REFERER'] : (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : null);
	}
	/**
	 * rawdataを取得する
	 * @return string
	 */
	static public function rawdata(){
		return file_get_contents('php://input');
	}
	protected function __str__(){
		return $this->body;
	}
	private function request($url,$method,array $header=array(),array $vars=array(),$download_path=null,$status_redirect=true){
		$url = (string)$url;
		Log::debug('Http request `'.$url.'`');
		$result = (object)array('url'=>$url,'status'=>200,'head'=>null,'redirect'=>null,'body'=>null,'encode'=>null,'cmd'=>null);
		$raw = isset($header['rawdata']) ? $header['rawdata'] : null;
		if(isset($header['rawdata'])) unset($header['rawdata']);
		$header['Content-Type'] = 'application/x-www-form-urlencoded';
		if(!isset($raw) && !empty($vars)){
			if($method == 'GET'){
				$url = (strpos($url,'?') === false) ? $url.'?' : $url.'&';
				$url .= self::query($vars,null,true,$this->query_array);
			}else{
				$query_vars = array(array(),array());
				foreach(self::expand_vars($tmp,$vars,null,false) as $v){
					$query_vars[is_string($v[1]) ? 0 : 1][] = $v;
				}
				if(empty($query_vars[1])){
					$raw = self::query($vars,null,true,$this->query_array);
				}else{
					$boundary = '-----------------'.md5(microtime());
					$header['Content-Type'] = 'multipart/form-data;  boundary='.$boundary;
					$raws = array();
	
					foreach($query_vars[0] as $v){
						$raws[] = sprintf('Content-Disposition: form-data; name="%s"',$v[0])
									."\r\n\r\n"
									.$v[1]
									."\r\n";
					}
					foreach($query_vars[1] as $v){
						$raws[] = sprintf('Content-Disposition: form-data; name="%s"; filename="%s"',$v[0],$v[1]->name())
									."\r\n".sprintf('Content-Type: %s',$v[1]->mime())
									."\r\n".sprintf('Content-Transfer-Encoding: %s',"binary")
									."\r\n\r\n"
									.$v[1]->get()
									."\r\n";
					}
					$raw = "--".$boundary."\r\n".implode("--".$boundary."\r\n",$raws)."\r\n--".$boundary."--\r\n"."\r\n";
				}
			}
		}
		$ulist = parse_url(preg_match("/^([\w]+:\/\/)(.+?):(.+)(@.+)$/",$url,$m) ? ($m[1].urlencode($m[2]).":".urlencode($m[3]).$m[4]) : $url);
		$ssl = (isset($ulist['scheme']) && ($ulist['scheme'] == 'ssl' || $ulist['scheme'] == 'https'));
		$port = isset($ulist['port']) ? $ulist['port'] : null;
		$errorno = $errormsg = null;
		if(!isset($ulist['host']) || substr($ulist['host'],-1) === '.') throw new InvalidArgumentException('Connection fail `'.$url.'`');
		$fp	= fsockopen((($ssl) ? 'ssl://' : '').$ulist['host'],(isset($port) ? $port : ($ssl ? 443 : 80)),$errorno,$errormsg,$this->timeout);
		if($fp == false || false == stream_set_blocking($fp,true) || false == stream_set_timeout($fp,$this->timeout)) throw new InvalidArgumentException('Connection fail `'.$url.'` '.$errormsg.' '.$errorno);
		$cmd = sprintf("%s %s%s HTTP/1.1\r\n",$method,((!isset($ulist["path"])) ? "/" : $ulist["path"]),(isset($ulist["query"])) ? sprintf("?%s",$ulist["query"]) : "")
				.sprintf("Host: %s\r\n",$ulist['host'].(empty($port) ? '' : ':'.$port));
		if(!isset($header['User-Agent'])) $header['User-Agent'] = empty($this->agent) ? (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null) : $this->agent;
		if(!isset($header['Accept'])) $header['Accept'] = isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : null;
		if(!isset($header['Accept-Language'])) $header['Accept-Language'] = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : null;
		if(!isset($header['Accept-Charset'])) $header['Accept-Charset'] = isset($_SERVER['HTTP_ACCEPT_CHARSET']) ? $_SERVER['HTTP_ACCEPT_CHARSET'] : null;
		$header['Connection'] = 'Close';
		foreach($header as $k => $v){
			if(isset($v)) $cmd .= sprintf("%s: %s\r\n",$k,$v);
		}
		if(!isset($header['Authorization']) && isset($ulist["user"]) && isset($ulist["pass"])){
			$cmd .= sprintf("Authorization: Basic %s\r\n",base64_encode(sprintf("%s:%s",urldecode($ulist["user"]),urldecode($ulist["pass"]))));
		}
		$result->cmd = $cmd.((!empty($raw)) ? ('Content-length: '.strlen($raw)."\r\n\r\n".$raw) : "\r\n");
		fwrite($fp,$result->cmd);
		while(!feof($fp) && substr($result->head,-4) != "\r\n\r\n"){
			$result->head .= fgets($fp,4096);
			self::check_timeout($fp,$url);
		}
		$result->status = (preg_match("/HTTP\/.+[\040](\d\d\d)/i",$result->head,$httpCode)) ? intval($httpCode[1]) : 0;
		$result->encode = (preg_match("/Content-Type.+charset[\s]*=[\s]*([\-\w]+)/",$result->head,$match)) ? trim($match[1]) : null;
		switch($result->status){
			case 300:
			case 301:
			case 302:
			case 303:
			case 307:
				if(preg_match("/Location:[\040](.*)/i",$result->head,$redirect_url)){
					$result->redirect = preg_replace("/[\r\n]/","",File::absolute($url,$redirect_url[1]));
					if($method == 'GET' && $result->redirect === $result->url){
						$result->redirect = null;
					}else if($status_redirect){
						fclose($fp);
						return $this->request($result->redirect,"GET",$h,array(),$download_path,$status_redirect);
					}
				}
		}
		$download_handle = ($download_path !== null && File::mkdir(dirname($download_path)) === null) ? fopen($download_path,"wb") : null;
		if(preg_match("/^Content\-Length:[\s]+([0-9]+)\r\n/i",$result->head,$m)){
			if(0 < ($length = $m[1])){
				$rest = $length % 4096;
				$count = ($length - $rest) / 4096;
				while(!feof($fp)){
					if($count-- > 0){
						self::write_body($result,$download_handle,fread($fp,4096));
					}else{
						self::write_body($result,$download_handle,fread($fp,$rest));
						break;
					}
					self::check_timeout($fp,$url);
				}
			}
		}else if(preg_match("/Transfer\-Encoding:[\s]+chunked/i",$result->head)){
			while(!feof($fp)){
				$size = hexdec(trim(fgets($fp,4096)));
				$buffer = "";
				while($size > 0 && strlen($buffer) < $size){
					$value = fgets($fp,$size);
					if($value === feof($fp)) break;
					$buffer .= $value;
				}
				self::write_body($result,$download_handle,substr($buffer,0,$size));
				self::check_timeout($fp,$url);
			}
		}else{
			while(!feof($fp)){
				self::write_body($result,$download_handle,fread($fp,4096));
				self::check_timeout($fp,$url);
			}
		}
		fclose($fp);
		if($download_handle !== null) fclose($download_handle);
		return $result;
	}
	static private function check_timeout($fp,$url){
		$info = stream_get_meta_data($fp);
		if($info['timed_out']){
			fclose($fp);
			throw new LogicException('Connection time out. `'.$url.'`');
		}
	}
	static private function write_body(&$result,&$download_handle,$value){
		if($download_handle !== null) return fwrite($download_handle,$value);
		return $result->body .= $value;
	}
	static private function output_file_content(File $file,$disposition){
		Log::disable_display();
		if($file->value() !== null || is_file($file->fullname())){
			if($file->update() > 0){
				if(isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && $file->update() <= strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE'])){
					self::status_header(304);
					exit;
				}
				self::send_header('Last-Modified: '.gmdate('D, d M Y H:i:s',$file->update()).' GMT');
			}
			self::send_header(sprintf('Content-Type: '.$file->mime().'; name=%s',$file->name()));
			self::send_header(sprintf('Content-Disposition: %s; filename=%s',$disposition,$file->name()));
			if(isset($_SERVER['HTTP_RANGE']) && $file->is_fullname() && preg_match("/^bytes=(\d+)\-(\d+)$/",$_SERVER['HTTP_RANGE'],$range)){
				list($null,$offset,$end) = $range;
				$length = $end - $offset + 1;
				
				self::send_header('HTTP/1.1 206 Partial content');
				self::send_header('Accept-Ranges: bytes');
				self::send_header(sprintf('Content-length: %u',$length));
				self::send_header(sprintf('Content-Range: bytes %u-%u/%u',$offset,$end,$file->size()));
				print(file_get_contents($file->fullname(),null,null,$offset,$length));
				exit;
			}else{
				if($file->size() > 0) self::send_header(sprintf('Content-length: %u',$file->size()));
				$file->output();
				exit;
			}
		}
		self::status_header(404);
		exit;
	}
	/**
	 * inlineで出力する
	 * @param File $file 出力するファイル
	 */
	static public function inline(File $file){
		self::output_file_content($file,'inline');
	}
	/**
	 * attachmentで出力する
	 * @param File $file 出力するファイル
	 */
	static public function attach(File $file){
		self::output_file_content($file,'attachment');
	}
	/**
	 * リダイレクトする
	 * @param string $url リダイレクトするURL
	 * @param mixed{} $vars query文字列として渡す変数
	 */
	static public function redirect($url,array $vars=array()){
		Log::disable_display();
		if(!empty($vars)){
			$requestString = self::query($vars);
			if(substr($requestString,0,1) == "?") $requestString = substr($requestString,1);
			$url = sprintf("%s?%s",$url,$requestString);
		}
		self::status_header(302);
		self::send_header("Location: ".$url);
		exit;
	}
	/**
	 * query文字列に変換する
	 * @param mixed $var query文字列化する変数
	 * @param string $name ベースとなる名前
	 * @param boolean $null nullの値を表現するか
	 * @param boolean $array 配列を表現するか
	 * @return string
	 */
	static public function query($var,$name=null,$null=true,$array=true){
		
		$result = "";
		foreach(self::expand_vars($vars,$var,$name,$array) as $v){
			if(($null || ($v[1] !== null && $v[1] !== '')) && is_string($v[1])) $result .= $v[0]."=".urlencode($v[1])."&";
		}
		return (empty($result)) ? $result : substr($result,0,-1);
	}
	static private function expand_vars(&$vars,$value,$name=null,$array=true){
		if(!is_array($vars)) $vars = array();
		if($value instanceof File){
			$vars[] = array($name,$value);
		}else{
			if(is_object($value)) $value = ($value instanceof Object) ? $value->hash() : "";
			if(is_array($value)){
				foreach($value as $k => $v){
					self::expand_vars($vars,$v,(empty($name) ? $k : $name.(($array) ? "[".$k."]" : "")),$array);
				}
			}else if(!is_numeric($name)){
				if(is_bool($value)) $value = ($value) ? "true" : "false";
				$vars[] = array($name,(string)$value);
			}
		}
		return $vars;
	}	
	
	/**
	 * HTTPステータスを出力する
	 * @param integer $statuscode 出力したいステータスコード
	 * @param boolean $force 強制的に変更する
	 */
	static public function status_header($statuscode,$force=false){
		if(isset(self::$status_header) && !$force) return;
		self::$status_header = $statuscode;
		$v = null;
		switch($statuscode){
			case 100: $v = '100 Continue'; break;
			case 101: $v = '101 Switching Protocols'; break;
			case 200: $v = '200 OK'; break;
			case 201: $v = '201 Created'; break;
			case 202: $v = '202 Accepted'; break;
			case 203: $v = '203 Non-Authoritative Information'; break;
			case 204: $v = '204 No Content'; break;
			case 205: $v = '205 Reset Content'; break;
			case 206: $v = '206 Partial Content'; break;
			case 300: $v = '300 Multiple Choices'; break;
			case 301: $v = '301 MovedPermanently'; break;
			case 302: $v = '302 Found'; break;
			case 303: $v = '303 See Other'; break;
			case 304: $v = '304 Not Modified'; break;
			case 305: $v = '305 Use Proxy'; break;
			case 307: $v = '307 Temporary Redirect'; break;
			case 400: $v = '400 Bad Request'; break;
			case 401: $v = '401 Unauthorized'; break;
			case 403: $v = '403 Forbidden'; break;
			case 404: $v = '404 Not Found'; break;
			case 405: $v = '405 Method Not Allowed'; break;
			case 406: $v = '406 Not Acceptable'; break;
			case 407: $v = '407 Proxy Authentication Required'; break;
			case 408: $v = '408 Request Timeout'; break;
			case 409: $v = '409 Conflict'; break;
			case 410: $v = '410 Gone'; break;
			case 411: $v = '411 Length Required'; break;
			case 412: $v = '412 Precondition Failed'; break;
			case 413: $v = '413 Request Entity Too Large'; break;
			case 414: $v = '414 Request-Uri Too Long'; break;
			case 415: $v = '415 Unsupported Media Type'; break;
			case 416: $v = '416 Requested Range Not Satisfiable'; break;
			case 417: $v = '417 Expectation Failed'; break;
			case 500: $v = '500 Internal Server Error'; break;
			case 501: $v = '501 Not Implemented'; break;
			case 502: $v = '502 Bad Gateway'; break;
			case 503: $v = '503 Service Unavailable'; break;
			case 504: $v = '504 Gateway Timeout'; break;
			case 505: $v = '505 Http Version Not Supported'; break;
			default: $v = '403 Forbidden ('.$statuscode.')'; break;
		}
		self::send_header('HTTP/1.1 '.$v);
	}
	/**
	 * GETしてbodyを取得する
	 *
	 * @param string $url アクセスするURL
	 * @return string
	 */
	static public function read($url){
		$self = new self();
		return $self->do_get($url)->body();
	}
	/**
	 * headerを送信する
	 * @param string $value 
	 */
	static public function send_header($value=null){
		if(!empty($value)){
			self::$send_header[] = $value;
			header($value);
		}
		return self::$send_header;
	}
	/**
	 * 送信したheaderの一覧
	 * @return string[]
	 */
	static public function headers_list(){
		return self::$send_header;
	}
}
/**
 * ログ処理
 *
 * @author Kazutaka Tokushima
 * @license New BSD License
 * @var string $level
 * @var timestamp $time
 * @var string $file
 * @var integer $line
 */
class Log extends Object{
	static private $exception_trace = false;
	static private $start_time = 0;
	static private $disp = false;
	static private $stdout = true;
	static private $current_level = 0;
	static private $level_strs = array('none','error','warn','info','debug');
	static private $logs = array();
	static private $id;
	protected $level; # ログのレベル
	protected $time; # 発生時間
	protected $file; # 発生したファイル名
	protected $line; # 発生した行
	protected $value; # 内容
	/**
	 * 例外を詳しく出力するか
	 * @param boolean $bool 例外を詳しく出力するか
	 */
	static public function config_exception_trace($bool){
		self::$exception_trace = $bool;
	}
	static public function __import__(){
		self::$id = uniqid('');
		self::$start_time = microtime(true);
		self::$logs[] = new self(4,'--- logging start '
									.date('Y-m-d H:i:s')
									.' ( '.(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : (isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : null)).' )'
									.' { '.(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null).' }'
								.' --- ');
	}
	static public function __shutdown__(){
		if(self::$current_level >= 4){
			if(function_exists('memory_get_usage')){
				self::$logs[] = new self(4,sprintf('--- end logger ( %s sec / %s MByte) --- ',round((microtime(true) - (float)self::$start_time),4),round(number_format((memory_get_usage() / 1024 / 1024),3),2)));
			}
		}
		if(self::$current_level >= 2){
			foreach(Exceptions::gets() as $e) self::$logs[] = new self(2,$e);
		}
		self::flush();
	}
	final protected function __new__($level,$value,$file=null,$line=null,$time=null){
		if($file === null){
			$debugs = debug_backtrace(false);
			if(sizeof($debugs) > 4){
				list($dumy,$dumy,$dumy,$debug,$op) = $debugs;
			}else{
				list($dumy,$debug) = $debugs;
			}
			$file = File::path(isset($debug['file']) ? $debug['file'] : $dumy['file']);
			$line = (isset($debug['line']) ? $debug['line'] : $dumy['line']);
			$class = (isset($op['class']) ? $op['class'] : $dumy['class']);
		}
		$this->level = $level;
		$this->file = $file;
		$this->line = intval($line);
		$this->time = ($time === null) ? time() : $time;
		$this->class = $class;
		$this->value = (is_object($value)) ? 
							(($value instanceof Exception) ? 
								array_merge(
									array($value->getMessage())
									,(self::$exception_trace ? $value->getTrace() : array($value->getTraceAsString()))
								)
								: clone($value)
							)
							: $value;
	}
	protected function __fm_value__(){
		if(!is_string($this->value)){
			ob_start();
				var_dump($this->value);
			return ob_get_clean();
		}
		return $this->value;
	}
	protected function __fm_level__(){
		return self::$level_strs[$this->level()];
	}
	protected function __get_time__($format='Y/m/d H:i:s'){
		return (empty($format)) ? $this->time : date($format,$this->time);
	}
	protected function __str__(){
		return '['.$this->time().']'.'['.self::$id.']'.'['.$this->fm_level().']'.':['.$this->file().':'.$this->line().']'.' '.$this->fm_value();
	}
	/**
	 * 格納されたログを出力する
	 */
	final static public function flush(){
		if(!empty(self::$logs)){
			foreach(self::$logs as $log){
				if(self::$current_level >= $log->level()){
					if(self::$disp && self::$stdout) print($log->str()."\n");
					switch($log->fm_level()){
						/**
						 * debugログの場合の処理
						 * @param self $log
						 * @param string $id
						 */
						case 'debug': Object::C(__CLASS__)->call_module('debug',$log,self::$id); break;
						/**
						 * infoログの場合の処理
						 * @param self $log
						 * @param string $id
						 */
						case 'info': Object::C(__CLASS__)->call_module('info',$log,self::$id); break;
						/**
						 * warnログの場合の処理
						 * @param self $log
						 * @param string $id
						 */
						case 'warn': Object::C(__CLASS__)->call_module('warn',$log,self::$id); break;
						/**
						 * errorログの場合の処理
						 * @param self $log
						 * @param string $id
						 */
						case 'error': Object::C(__CLASS__)->call_module('error',$log,self::$id); break;
					}
				}
			}
		}
		/**
		 * フラッシュ時の処理
		 * @param self[] $logs
		 * @param string $id
		 * @param boolean $stdout 標準出力に出力するか
		 */
		Object::C(__CLASS__)->call_module('flush',self::$logs,self::$id,self::$stdout);
		/**
		 * フラッシュの後処理
		 * @param string $id
		 */
		Object::C(__CLASS__)->call_module('after_flush',self::$id);
		self::$logs = array();
	}
	/**
	 * 現在のログレベル
	 * @return string
	 */
	static public function current_level(){
		return self::$level_strs[self::$current_level];
	}
	/**
	 * Exceptionをどう扱うか
	 * @return boolean
	 */
	static public function exception_trace(){
		return self::$exception_trace;
	}
	/**
	 * ログレベルを定義する
	 * @param choice(none,error,warn,info,debug) $level ログレベル
	 * @param boolean $bool 標準出力に出力するか
	 */
	static public function config_level($level,$bool=false){
		self::$current_level = array_search($level,self::$level_strs);
		self::$disp = (boolean)$bool;
	}
	/**
	 * 一時的に無効にされた標準出力へのログ出力を有効にする
	 * ログのモードに依存する
	 */
	static public function enable_display(){
		self::debug('log stdout on');
		self::$stdout = true;
	}
	/**
	 * 標準出力へのログ出力を一時的に無効にする
	 */
	static public function disable_display(){
		self::debug('log stdout off');
		self::$stdout = false;
	}
	/**
	 * 標準出力へのログ可不可
	 * @return boolean
	 */
	static public function is_display(){
		return self::$stdout;
	}
	/**
	 * errorを生成
	 * @param mixed $value 内容
	 */
	static public function error(){
		if(self::$current_level >= 1){
			foreach(func_get_args() as $value) self::$logs[] = new self(1,$value);
		}
	}
	/**
	 * warnを生成
	 * @param mixed $value 内容
	 */
	static public function warn($value){
		if(self::$current_level >= 2){
			foreach(func_get_args() as $value) self::$logs[] = new self(2,$value);
		}
	}
	/**
	 * infoを生成
	 * @param mixed $value 内容
	 */
	static public function info($value){
		if(self::$current_level >= 3){
			foreach(func_get_args() as $value) self::$logs[] = new self(3,$value);
		}
	}
	/**
	 * debugを生成
	 * @param mixed $value 内容
	 */
	static public function debug($value){
		if(self::$current_level >= 4){
			foreach(func_get_args() as $value) self::$logs[] = new self(4,$value);
		}
	}
	/**
	 * var_dumpで出力する
	 * @param mixed $value 内容
	 */
	static public function d($value){
		list($debug_backtrace) = debug_backtrace(false);
		$args = func_get_args();
		var_dump(array_merge(array($debug_backtrace['file'].':'.$debug_backtrace['line']),$args));
	}
}
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
Test::__import__();
Log::__import__();
$exception = $isweb = $run = null;
if(($run = sizeof(debug_backtrace())) > 0 || !($isweb = (isset($_SERVER['REQUEST_URI']) && isset($_SERVER['REQUEST_METHOD'])))){
	try{
		if(is_file(getcwd().'/__settings__.php')) require_once(getcwd().'/__settings__.php');
		if(class_exists('App') && is_file(getcwd().'/__common_'.App::mode().'__.php')) require_once(getcwd().'/__common_'.App::mode().'__.php');
		if(is_file(getcwd().'/__common__.php')) require_once(getcwd().'/__common__.php');
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
}
