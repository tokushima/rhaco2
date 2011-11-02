<?php
/**
 * HTTP関連処理
 * @author tokushima
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
	 * POSTでダウンロードする
	 *
	 * @param string $url アクセスするURL
	 * @param string $download_path ダウンロード先のファイルパス
	 * @return $this
	 */
	public function do_post_download($url=null,$download_path){
		return $this->browse($this->build_url($url),'POST',false,$download_path);
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
		/***
			eq("req=123",self::query("123","req"));
			eq("req[0]=123",self::query(array(123),"req"));
			eq("req[0]=123&req[1]=456&req[2]=789",self::query(array(123,456,789),"req"));
			eq("",self::query(array(123,456,789)));
			eq("abc=123&def=456&ghi=789",self::query(array("abc"=>123,"def"=>456,"ghi"=>789)));
			eq("req[0]=123&req[1]=&req[2]=789",self::query(array(123,null,789),"req"));
			eq("req[0]=123&req[2]=789",self::query(array(123,null,789),"req",false));
			
			eq("req=123&req=789",self::query(array(123,null,789),"req",false,false));
			eq("label=123&label=&label=789",self::query(array("label"=>array(123,null,789)),null,true,false));

			$name = create_class('
				public $id = 0;
				public $value = "";
				public $test = "TEST";
			');
			$obj = new $name();
			$obj->id(100);
			$obj->value("hogehoge");
			eq("req[id]=100&req[value]=hogehoge&req[test]=TEST",self::query($obj,"req"));
			eq("id=100&value=hogehoge&test=TEST",self::query($obj));
		 */
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