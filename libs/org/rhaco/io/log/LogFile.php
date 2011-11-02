<?php
/**
 * ファイルにログを出力するLogモジュール
 * @author tokushima
 */
class LogFile extends Object{
	protected $path;

	/**
	 * @const string $path ログファイルを保存するパス
	 */
	protected function __get_path__(){
		if(!isset($this->path)) $this->path = File::absolute(module_const("path",work_path("log")),date("Ymd").".log");
		return $this->path;
	}
	/**
	 * Logのモジュール
	 */
	public function debug(Log $log,$id){
		File::append($this->path(),$log->str()."\n");
	}
	/**
	 * Logのモジュール
	 */
	public function info(Log $log,$id){
		File::append($this->path(),$log->str()."\n");
	}
	/**
	 * Logのモジュール
	 */
	public function warn(Log $log,$id){
		File::append($this->path(),$log->str()."\n");
	}
	/**
	 * Logのモジュール
	 */
	public function error(Log $log,$id){
		File::append($this->path(),$log->str()."\n");
	}
}
