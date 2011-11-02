<?php
/**
 * ログをPOSTする
 * @author tokushima
 */
class LogPost extends Object{
	protected $url;
	private $http;
	
	protected function __init__(){
		$this->http = new Http();
	}
	protected function url(){
		if(!isset($this->url)) $this->url = module_const("url");
		return $this->url;
	}
	protected function var_name(){
		if(!isset($this->var_name)) $this->var_name = module_const("var_name","msg");
		return $this->var_name;
	}
	public function debug(Log $log,$id){
		if($this->url() !== null){
			$this->http->vars($this->var_name(),$log->str());
			$this->http->do_post($this->url());
		}
	}
	public function info(Log $log,$id){
		if($this->url() !== null){
			$this->http->vars($this->var_name(),$log->str());
			$this->http->do_post($this->url());
		}
	}
	public function warn(Log $log,$id){
		if($this->url() !== null){
			$this->http->vars($this->var_name(),$log->str());
			$this->http->do_post($this->url());
		}
	}
	public function error(Log $log,$id){
		if($this->url() !== null){
			$this->http->vars($this->var_name(),$log->str());
			$this->http->do_post($this->url());
		}
	}
}
