<?php
/**
 * リクエスト/テンプレートを処理する
 *
 * @author tokushima
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
	
	static private $load_apps_vars = array();
	
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
	/**
	 * エントリポイントのロード時に置換される変数の定義
	 * @param string $entry エントリポイント名
	 * @param string $key 変数名
	 * @param string $value 値
	 */
	static public function set_entry_vars($entry,$key,$value){
		if(!isset(self::$load_apps_vars[$entry][$key])) self::$load_apps_vars[$entry][$key] = (string)$value;
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
					$action = new $class('scope='.$map['scope']);
					foreach(array('redirect','name') as $k) $action->{$k} = $map[$k];
					$action->set($this,$map,$pattern,$params,$urls,$index,true);
				}
				$this->cp($action->vars());
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
		list($template,$template_path,$media_url) = array($map['template'],$map['template_path'],$map['media_path']);
		if($method_call) call_user_func_array(array($this,$map['method']),$params);
		if($method_call){
			if($this->ext_template->filename() === null && !isset($template)){
				$ref = new ReflectionObject($this);
				$file = dirname($ref->getFileName()).'/resources/templates/'.$map['method'].'.html';
				if(is_file($file)){
					$template = basename($file);
					$template_path = dirname($file);
					$media_url = App::url(self::$package_media_url.'/'.$index.'/'.$urls[$pattern]['map_index']);
				}
			}
		}
		if(!empty($template)){
			if($this->is_theme() || isset($map['theme_path'])){
				if(!isset($map['theme_path'])) $map['theme_path'] = 'theme';
				if(!$this->is_theme()) $this->theme('default');
				$template = File::path_slash($map['theme_path'],false,true).File::path_slash($this->theme(),false,true).$template;
				$media_url = File::path_slash($map['theme_path'],false,true).File::path_slash($this->theme(),false,false);
			}
			if(isset($template_path) && strpos($template,'://') === false) $template = File::path_slash($template_path,null,true).$template;
			$this->ext_template->media_url(File::absolute($this->ext_template->media_url(),$media_url));
			$this->ext_template->template_super($map['template_super']);
			$this->ext_template->filename($template);
		}
		/**
		 * 後処理
		 * @param self $this
		 */
		$this->call_module('after_flow_handle',$this);
		$module_obj->ext_template = $this->ext_template;
		$module_obj->ext_template->copy_module($this);
		$module_obj->ext_template->secure($module_obj->secure_map);
		$this->cp(self::execute_var($map['vars']));
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
					if(!empty($parse_app['session']) && !Object::C(Request)->has_module('session_read')){
						$session_class = Lib::import($parse_app['session']);
						Object::C(Request)->add_module(new $session_class);
					}
					foreach($app['modules'] as $module){
						$self->add_module(self::import_instance($module));
					}
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
								 * @param self $self
								 */
								$self->call_module('flow_gc',$self);
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
						if(isset($app['maps'][$self->pattern()]) && $app['maps'][$self->pattern()]['error_template'] != ''){
							$action = new self('scope='.$app['maps'][$self->pattern()]['scope']);
							$action->set($self,$app['maps'][$self->pattern()],$self->pattern(),array(),$app['maps']);
							$action->cp(self::execute_var($app['vars']));

							$map = $app['maps'][$self->pattern()];
							list($template,$template_path,$media_url) = array($map['error_template'],$map['template_path'],$map['media_path']);
							
							if($action->is_theme() || isset($map['theme_path'])){
								if(!isset($map['theme_path'])) $map['theme_path'] = 'theme';
								if(!$action->is_theme()) $action->theme('default');
								$template = File::path_slash($map['theme_path'],false,true).File::path_slash($action->theme(),false,true).$template;
								$media_url = File::path_slash($map['theme_path'],false,true).File::path_slash($action->theme(),false,false);
							}
							if(isset($template_path) && strpos($template,'://') === false) $template = File::path_slash($template_path,null,true).$template;
							$action->ext_template->media_url(File::absolute($action->ext_template->media_url(),$media_url));
							$action->ext_template->template_super($map['template_super']);
							$action->ext_template->filename($template);
							$action->print_template($action->read());
							exit;
						}
						if(isset($app['on_error']['status'])) Http::status_header((int)$app['on_error']['status']);
						if(isset($app['on_error']['redirect'])){
							$self->redirect(($app['on_error']['redirect'][0] == '/') ? substr($app['on_error']['redirect'],1) : $app['on_error']['redirect']);
						}else if(isset($app['on_error']['template'])){
							$action = $self;
							if($self->is_pattern()){
								$action = new self('scope='.$app['maps'][$self->pattern()]['scope']);
								$action->set($self,$app['maps'][$self->pattern()],$self->pattern(),array(),$app['maps']);
							}
							$action->cp(self::execute_var($app['vars']));
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
			if($parse_app['nomatch_redirect'] !== null) Http::redirect(App::url($parse_app['nomatch_redirect']));
			if($parse_app['nomatch_template'] !== null){
				$self = new self();
				$self->ext_template->filename($parse_app['nomatch_template']);
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
	private function handle_exception(){
		$exceptions = Exceptions::gets();
		/**
		 * Flow処理で例外が発生した場合に実行する処理
		 * @param Exception[] $exceptions
		 * @param self $this
		 */
		$this->call_module('flow_handle_exception',$exceptions,$this);
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
						$map->add('name',(empty($package_name) ? '' : $package_name.'/').$method->getName().(($i>0) ? '/'.$i : ''));
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
		$app_nomatch_redirect = $app_nomatch_template = $app_session = null;
		$src = File::read($file);
		$entry = basename($file,'.php');
		
		if(isset(self::$load_apps_vars[$entry]) && !empty(self::$load_apps_vars[$entry]) && preg_match_all('/{\$([\w_]+?)}/',$src,$m)){
			foreach($m[1] as $k => $v) $src = str_replace($m[0][$k],isset(self::$load_apps_vars[$entry][$v]) ? self::$load_apps_vars[$entry][$v] : '',$src);
		}
		if(Tag::setof($tag,Tag::uncomment($src),'app')){
			$app_ns = $tag->in_param('ns');
			$app_nomatch_redirect = File::path_slash($tag->in_param('nomatch_redirect'),false,null);
			$app_nomatch_template = File::path_slash($tag->in_param('nomatch_template'),false,null);
			$app_session = $tag->in_param('session');
			$handler_count = 0;
			$invoke_count = 0;

			foreach($tag->in(array('invoke','handler')) as $handler){
				switch(strtolower($handler->name())){
					case 'handler':
						if($handler->is_param('session')){
							if(!empty($app_session)) throw new LogicException('session module already exists '.$app_session);
							$app_session = $handler->in_param('session');
						}
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
							$handler_name = (empty($app_ns)) ? preg_replace("/^([\w]+).*$/","\\1",basename($file)).$handler_count++ : $app_ns;
							$maps = $modules = $vars = array();
							$handler_url = File::path_slash($handler->in_param('url'),false,true);
							$map_index = 0;

							$template_path = $handler->is_param('template_path') ? File::path_slash($handler->in_param('template_path'),false,false) : null;
							$media_path = $handler->is_param('media_path') ? File::path_slash($handler->in_param('media_path'),false,false) : null;
							$handler_theme_path = $handler->in_param('theme_path') ? File::path_slash($handler->in_param('theme_path'),false,false) : null;

							foreach($handler->in(array('maps','map','var','module')) as $tag){
								switch(strtolower($tag->name())){
									case 'map':
										$url = File::path_slash($handler_url.File::path_slash($tag->in_param('url'),false,false),false,false);
										$theme_path = ($tag->is_param('theme_path') || isset($handler_theme_path)) ? File::path_slash($handler_theme_path,false,true).File::path_slash($tag->in_param('theme_path'),false,false) : null;
										$map = self::parse_map($tag,$tag->is_param('url'),$url,$template_path,$media_path,null,$theme_path,$handler_name,null,null,null,$map_index++);
										$maps[$map['url']] = $map;
										break;
									case 'maps':
										$maps_map = $maps_module = array();
										$maps_template_path = ($tag->in_param('template_path') != '' || isset($template_path)) ? ((strpos($tag->in_param('template_path'),'://') === false) ? File::path_slash($template_path,false,true) : '').File::path_slash($tag->in_param('template_path'),false,false) : null;
										$maps_media_path = ($tag->in_param('media_path') != '' || isset($media_path)) ? ((strpos($tag->in_param('media_path'),'://') === false) ? File::path_slash($media_path,false,true) : '').File::path_slash($tag->in_param('media_path'),false,false) : null;
										$maps_error_template = $tag->is_param('error_template') ? $tag->in_param('error_template') : null;
										$maps_theme_path = ($tag->in_param('theme_path') != '' || isset($handler_theme_path)) ? File::path_slash($handler_theme_path,false,true).File::path_slash($tag->in_param('theme_path'),false,false) : null;
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
												$map = self::parse_map($m,$m->is_param('url'),$url,$maps_template_path,$maps_media_path,$maps_error_template,$theme_path,$handler_name,$tag->in_param('class'),$tag->in_param('secure'),$tag->in_param('update'),$map_index++);
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
		return array('nomatch_redirect'=>$app_nomatch_redirect,'nomatch_template'=>$app_nomatch_template,'apps'=>$apps,'session'=>$app_session);
		/***
			# app
			ftmp("flow/parse_app_app.html",'
				<app nomatch_redirect="404/redirect" nomatch_template="nomatch/template.html" multiple="true">
				</app>
			');
			$app = self::parse_app(tmp_path("flow/parse_app_app.html"));
			eq("404/redirect",$app["nomatch_redirect"]);
			eq("nomatch/template.html",$app["nomatch_template"]);
		 */
		/***
			# invoke
			ftmp("flow/parse_app_invoke.html",'
				<app>
					<invoke class="org.rhaco.net.xml.Feed" method="do_read">
						<arg value="http://ameblo.jp/nakagawa-shoko/" />
						<arg value="http://ameblo.jp/kurori1985/" />
					</invoke>
					<invoke class="org.rhaco.net.xml.FeedConverter" method="strip_tags" />
					<invoke method="output" />
				</app>
			');
			$app = self::parse_app(tmp_path("flow/parse_app_invoke.html"));
			eq(null,$app["nomatch_redirect"]);
			eq(null,$app["nomatch_template"]);
			eq("invoke",$app["apps"][0]["type"]);
			eq("org.rhaco.net.xml.Feed",$app["apps"][0]["class"]);
			eq("do_read",$app["apps"][0]["methods"][0]["method"]);
			eq("arg",$app["apps"][0]["methods"][0]["args"][0]["type"]);
			eq("http://ameblo.jp/nakagawa-shoko/",$app["apps"][0]["methods"][0]["args"][0]["value"]);
			eq("arg",$app["apps"][0]["methods"][0]["args"][1]["type"]);
			eq("http://ameblo.jp/kurori1985/",$app["apps"][0]["methods"][0]["args"][1]["value"]);
			eq(array(),$app["apps"][0]["modules"]);

			eq("invoke",$app["apps"][1]["type"]);
			eq("org.rhaco.net.xml.FeedConverter",$app["apps"][1]["class"]);
			eq("strip_tags",$app["apps"][1]["methods"][0]["method"]);
			eq("result",$app["apps"][1]["methods"][0]["args"][0]["type"]);
			eq(null,$app["apps"][1]["methods"][0]["args"][0]["value"]);
			eq(array(),$app["apps"][1]["modules"]);

			eq("invoke",$app["apps"][2]["type"]);
			eq(null,$app["apps"][2]["class"]);
			eq("output",$app["apps"][2]["methods"][0]["method"]);
			eq(array(),$app["apps"][2]["methods"][0]["args"]);
			eq(array(),$app["apps"][2]["modules"]);
		 */
		/***
			# handler
			ftmp("flow/parse_app_handler.html",'
				<app>
					<handler>
						<map url="/hello" class="FlowSampleHello" method="hello" template="display.html" summary="ハローワールド" name="hello_world" />
						<map url="/list" class="FlowSampleList" method="data_list" template="list.html" name="list">
							<arg name="paginate_by" value="20" />
						</map>
						<map url="edit" class="FlowSampleHello" method="update_date" template="update.html" name="edit_form" update="POST">
							<arg name="post_success_redirect" value="post_success" />
						</map>
						<map url="post_success" template="success.html" />
					</handler>
				</app>
			');
			$app = self::parse_app(tmp_path("flow/parse_app_handler.html"));

			eq(null,$app["nomatch_redirect"]);
			eq(null,$app["nomatch_template"]);
			eq("handle",$app["apps"][0]["type"]);
			eq("post_success",$app["apps"][0]["maps"]["post_success"]["url"]);
			eq("parse_app_handler0",$app["apps"][0]["maps"]["post_success"]["scope"]);
			eq(3,$app["apps"][0]["maps"]["post_success"]["map_index"]);
			eq(null,$app["apps"][0]["maps"]["post_success"]["redirect"]);
			eq("success.html",$app["apps"][0]["maps"]["post_success"]["template"]);
			eq(false,$app["apps"][0]["maps"]["post_success"]["secure"]);
			eq("none",$app["apps"][0]["maps"]["post_success"]["update"]);
			eq(null,$app["apps"][0]["maps"]["post_success"]["class"]);
			eq(null,$app["apps"][0]["maps"]["post_success"]["method"]);
			eq(null,$app["apps"][0]["maps"]["post_success"]["name"]);
			eq(null,$app["apps"][0]["maps"]["post_success"]["name"]);
			eq(array(),$app["apps"][0]["maps"]["post_success"]["args"]);
			eq(array(),$app["apps"][0]["maps"]["post_success"]["modules"]);
			eq(array(),$app["apps"][0]["maps"]["post_success"]["vars"]);

			eq("list",$app["apps"][0]["maps"]["list"]["url"]);
			eq("parse_app_handler0",$app["apps"][0]["maps"]["list"]["scope"]);
			eq(1,$app["apps"][0]["maps"]["list"]["map_index"]);
			eq(null,$app["apps"][0]["maps"]["list"]["redirect"]);
			eq("list.html",$app["apps"][0]["maps"]["list"]["template"]);
			eq(false,$app["apps"][0]["maps"]["list"]["secure"]);
			eq("none",$app["apps"][0]["maps"]["list"]["update"]);
			eq("FlowSampleList",$app["apps"][0]["maps"]["list"]["class"]);
			eq("data_list",$app["apps"][0]["maps"]["list"]["method"]);
			eq("list",$app["apps"][0]["maps"]["list"]["name"]);
			eq(array("paginate_by"=>"20"),$app["apps"][0]["maps"]["list"]["args"]);
			eq(array(),$app["apps"][0]["maps"]["list"]["modules"]);
			eq(array(),$app["apps"][0]["maps"]["list"]["vars"]);

			eq("hello",$app["apps"][0]["maps"]["hello"]["url"]);
			eq("parse_app_handler0",$app["apps"][0]["maps"]["hello"]["scope"]);
			eq(0,$app["apps"][0]["maps"]["hello"]["map_index"]);
			eq(null,$app["apps"][0]["maps"]["hello"]["redirect"]);
			eq("display.html",$app["apps"][0]["maps"]["hello"]["template"]);
			eq(false,$app["apps"][0]["maps"]["hello"]["secure"]);
			eq("none",$app["apps"][0]["maps"]["hello"]["update"]);
			eq("FlowSampleHello",$app["apps"][0]["maps"]["hello"]["class"]);
			eq("hello",$app["apps"][0]["maps"]["hello"]["method"]);
			eq("hello_world",$app["apps"][0]["maps"]["hello"]["name"]);
			eq(array(),$app["apps"][0]["maps"]["hello"]["args"]);
			eq(array(),$app["apps"][0]["maps"]["hello"]["modules"]);
			eq(array(),$app["apps"][0]["maps"]["hello"]["vars"]);

			eq("edit",$app["apps"][0]["maps"]["edit"]["url"]);
			eq("parse_app_handler0",$app["apps"][0]["maps"]["edit"]["scope"]);
			eq(2,$app["apps"][0]["maps"]["edit"]["map_index"]);
			eq(null,$app["apps"][0]["maps"]["edit"]["redirect"]);
			eq("update.html",$app["apps"][0]["maps"]["edit"]["template"]);
			eq(false,$app["apps"][0]["maps"]["edit"]["secure"]);
			eq("post",$app["apps"][0]["maps"]["edit"]["update"]);
			eq("FlowSampleHello",$app["apps"][0]["maps"]["edit"]["class"]);
			eq("update_date",$app["apps"][0]["maps"]["edit"]["method"]);
			eq("edit_form",$app["apps"][0]["maps"]["edit"]["name"]);
			eq(array("post_success_redirect"=>"post_success"),$app["apps"][0]["maps"]["edit"]["args"]);
			eq(array(),$app["apps"][0]["maps"]["edit"]["modules"]);
			eq(array(),$app["apps"][0]["maps"]["edit"]["vars"]);
		*/
	}
	static private function parse_map(Tag $map_tag,$is_url,$url,$template_path,$media_path,$error_template,$theme_path,$scope,$base_class,$secure,$update,$map_index){
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
		$params['media_path'] = $media_path;
		$params['theme_path'] = $theme_path;
		$params['error_template'] = $map_tag->is_param('error_template') ? File::path_slash($map_tag->in_param('error_template'),false,false) : $error_template;
		$params['template_super'] = File::path_slash($map_tag->in_param('template_super'),false,false);
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