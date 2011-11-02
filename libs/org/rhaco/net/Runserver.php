<?php
import("org.rhaco.net.SocketListener");
/**
 * 簡易HTTPサーバ
 * SocketListenerのmodule
 * @author tokushima
 */
class Runserver extends Object{
	protected $php_cmd;

	public function listen($address,$port){
		if(empty($this->php_cmd)) $this->php_cmd = isset($_ENV['_']) ? $_ENV['_'] : 'php';
		$this->output('checking for php..');
		$cmd = new Command($this->php_cmd.' -v');
		if($cmd->is_stderr()) throw new RuntimeException($cmd->stderr());

		$this->output($cmd->stdout());
		$this->output('Development server is running at http://'.$address.':'.$port);
		$this->output('Quit the server with CONTROL-C.');
	}
	public function connect($channel){
		$head = $body = null;
		$method = $uri = $query = $boundary = null;
		$POST = $GET = $FILES = $SERVER = array();
		$message_len = $null_cnt = 0;

		try{
			$uid = uniqid(""); 

			while(true){
				$message = $channel->read();
				if($message === '') $null_cnt++;
				if($null_cnt > 5) break;

				if($method === null){
					$head .= $message;

					if(substr($head,-4) === "\r\n\r\n"){
						$lines = explode("\n",trim($head));
						if(!empty($lines)){
							$exp = explode(" ",array_shift($lines));
							if(sizeof($exp) >= 2) list($method,$uri) = $exp;
							if(strpos($uri,'?')){
								list($uri,$SERVER["QUERY_STRING"]) = explode('?',$uri);
								parse_str($SERVER["QUERY_STRING"],$GET);
							}
							foreach($lines as $line){
								$exp = explode(":",$line,2);
								if(sizeof($exp) == 2){
									list($name,$value) = $exp;
									$SERVER["HTTP_".str_replace(array("-"),array("_"),strtoupper(trim($name)))] = trim($value);
								}
							}
						}
						if($method === null || $method == "GET") break;
						if(isset($SERVER['HTTP_CONTENT_TYPE']) && preg_match("/multipart\/form-data; boundary=(.+)$/",$SERVER['HTTP_CONTENT_TYPE'],$m)){
							$boundary = "--".$m[1];
						}
					}
				}else if($method == "POST"){
					$message_len += strlen($message);
					$body .= $message;
					if(
						(isset($SERVER['HTTP_CONTENT_LENGTH']) && $message_len >= $SERVER['HTTP_CONTENT_LENGTH'])
						|| (!isset($SERVER['HTTP_CONTENT_LENGTH']) && substr($body,-4) === "\r\n\r\n")
					){
						if(isset($boundary)){
							list($body) = explode($boundary."--\r\n",$body,2);
							foreach(explode($boundary."\r\n",$body) as $k => $block){
								if(!empty($block)){
									list($h,$b) = explode("\r\n\r\n",$block);
									list($b) = explode("\r\n",$b,2);
									
									if(preg_match("/\sname=([\"'])(.+?)\\1/",$h,$m)){
										$name = $m[2];
										
										if(preg_match("/filename=([\"'])(.+?)\\1/",$h,$m)){
											$tmp_name = self::work_path($uid,$k);
											File::write($tmp_name,$b);
											$FILES[$name] = array("name"=>$m[2],"tmp_name"=>$tmp_name,"size"=>filesize($tmp_name),"error"=>0);
										}else{
											$POST[$name] = $b;
										}
									}
								}
							}
						}else{
							parse_str($body,$POST);
						}
						break;
					}else if(!isset($SERVER['HTTP_CONTENT_LENGTH'])){
						break;
					}
				}else{
					$this->output("Unknown method: ".$method);
					break;
				}
			}
			if(!empty($uri)){
				$request_uri = $uri;
				$this->output("request uri: ".$uri);
				$uri = preg_replace("/\/+/","/",$uri);

				if(
					strpos($uri,".php") === false 
					&& !is_file(File::absolute(getcwd(),File::path_slash($uri,false,null)))
					&& $uri != "/favicon.ico"
				){
					$exp = explode("/",File::path_slash($uri,false,null),2);

					if(is_file(File::absolute(getcwd(),$exp[0].".php")) && isset($exp[1])){
						$uri = "/".$exp[0].".php/".$exp[1];
					}else if(is_file(File::absolute(getcwd(),"index.php"))){
						$uri = "/index.php".$uri;
					}
				}
				if($request_uri != $uri) $this->output(" - rewrite uri: ".$uri);
				
				if(strpos($uri,".php/") !== false){
					$exp = explode(".php/",$uri,2);
					$uri = $exp[0].".php";
					$SERVER["PATH_INFO"] = "/".$exp[1];
				}
				$path = File::absolute(getcwd(),File::path_slash($uri,false,null));

				if(is_file($path)){
					$file = new File($path);
					if(substr($path,-4) == ".php") $file->mime('text/html');
					
					$headers = array();
					$headers[] = "Content-Type: ".$file->mime();
					$headers[] = "Connection: close";
					
					if(substr($path,-4) == ".php"){
						$SERVER['REQUEST_METHOD'] = $method;
						$REQUEST = array("_SERVER"=>$SERVER,"_POST"=>$POST,"_GET"=>$GET,"_FILES"=>$FILES);
						File::write(self::work_path($uid,"request"),serialize($REQUEST));
						$exec_command = $this->php_cmd." ".$file->fullname()." -emulator ".$uid;
						$this->output(" -- ".$exec_command);
						$cmd = new Command($exec_command);
						
						if(is_file(self::work_path($uid,"header"))){
							$send_header = unserialize(File::read(self::work_path($uid,"header")));
							if(!empty($send_header) && is_array($send_header)){
								$headers = array_merge($headers,$send_header);
							}
						}
						foreach($headers as $k => $v){
							if(strpos($v,":") === false){
								$top = $headers[$k];
								unset($headers[$k]);
								array_unshift($headers,$top);
								break;
							}
						}
						$output_header = trim(implode("\r\n",$headers));
						if(strpos($output_header,"HTTP/") !== 0){
							if(strpos($output_header,"Location: ") !== false){
								$output_header = "HTTP/1.1 302 Found\r\n".$output_header;
							}else{
								$output_header = "HTTP/1.1 200 OK\r\n".$output_header;
							}
						}
						$channel->write($output_header."\r\n\r\n");
						$channel->write($cmd->stdout());
						File::rm(self::work_path($uid));
					}else{
						$channel->write("HTTP/1.1 200 OK\r\n".implode("\r\n",$headers)."\r\n\r\n");
						$fp = fopen($file->fullname(),"rb");
						while(!feof($fp)) $channel->write(fread($fp,4096));
						fclose($fp);
					}
				}else{
					$this->output($path." not found");
					$channel->write($this->error(404,"Not Found","The requested URL ".$uri." was not found on this server."));
				}
			}
		}catch(SocketErrorException $e){
			$this->output($e->getMessage());
		}
	}
	private function error($status,$summary,$message){
		$headers[] = "HTTP/1.1 ".$status." ".$summary;
		$headers[] = "Connection: close";
		$headers[] = "Content-Type: text/html";

		return implode("\r\n",$headers)."\r\n\r\n".'<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">'
				.'<html><head>'
				.'<title>'.$status.' '.$summary.'</title>'
				.'</head><body>'
				.'<h1>'.$summary.'</h1>'
				.'<p>'.$message.'</p>'
				.'<hr>'
				.'</body></html>';		
	}
	/**
	 * 
	 * Starts a lightweight Web server for development.
	 * @request string $address server address (localhost)
	 * @request integer $port server port (8888)
	 * @request string $php php path (/usr/bin/php)
	 */
	static public function __setup_runserver__(Request $req){
		$src = File::read(App::path("__settings__.php"));
		if(strpos($src,module_package()) === false){
			File::write(App::path("__settings__.php"),$src."\nimport('".module_package()."');");
		}
		$self = new self();
		if($req->is_vars("php")) $self->php_cmd($req->in_vars("php"));
		
		$server = new SocketListener();
		$server->add_module($self);
		$server->start($req->in_vars("address","localhost"),$req->in_vars("port",8888));
	}
	static public function __import__(){
		if(isset($_SERVER['argv'][1]) && $_SERVER['argv'][1] == '-emulator' && isset($_SERVER['argv'][2])){
			Log::info("request emulation");
			$request = unserialize(File::read(self::work_path($_SERVER['argv'][2],"request")));
			foreach($request as $k => $v){
				if(!empty($v)){
					if($k == "_SERVER"){
						if(!isset($_SERVER)) $_SERVER = array();
						$_SERVER = array_merge($_SERVER,$v);
						
						if(isset($_SERVER['HTTP_COOKIE'])){
							foreach(explode(";", $_SERVER['HTTP_COOKIE']) as $v){
								$exp = explode("=",$v);
								$_COOKIE[$exp[0]] = $exp[1];
							}
						}
					}else if($k == "_GET"){
						if(!isset($_GET)) $_GET = array();
						$_GET = array_merge($_GET,$v);
					}else if($k == "_POST"){
						if(!isset($_POST)) $_POST = array();
						$_POST = array_merge($_POST,$v);
					}else if($k == "_FILES"){
						if(!isset($_FILES)) $_FILES = array();
						$_FILES = array_merge($_FILES,$v);
					}
				}
			}
		}
	}
	static public function __shutdown__(){
		if(isset($_SERVER['argv'][1]) && $_SERVER['argv'][1] == '-emulator' && isset($_SERVER['argv'][2])){
			File::write(self::work_path($_SERVER['argv'][2],"header"),serialize(Http::headers_list()));
		}
	}
	static private function work_path($uid,$name=null){
		return work_path("emulator/".$uid.(!empty($name) ? "/".$name : ""));
	}
	private function output($msg){
		println($msg);
	}
}
