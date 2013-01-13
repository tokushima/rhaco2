<?php
/**
 * リクエストを処理する
 * @author tokushima
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
	private $login_id;

	static private $session_limiter = 'nocache';
	static private $session_expire = 10800;
	static private $session_name = 'SID';

	/**
	 * セッションに関する設定
	 * @param alnum $name セッション名
	 * @param choice(none,nocache,private,private_no_expire,public) $limiter キャッシュリミッタ
	 * @param integer $expire 有効期間(秒)
	 */
	static public function config_session($name,$limiter=null,$expire=null){
		if(!empty($name)) self::$session_name = $name;
		if(isset($limiter)) self::$session_limiter = $limiter;
		if(isset($expire)) self::$session_expire = (int)$expire;
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
				$this->scope = (!empty($args) && preg_match("/scope=([\w_]+)/",$args[0],$m)) ? trim($m[1]) : get_class($this);
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
					session_cache_limiter(self::$session_limiter);
					session_cache_expire((int)(self::$session_expire/60));
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
	 * @param int $expire 有効期限(秒) (+time)
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