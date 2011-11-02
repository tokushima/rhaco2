<?php
/**
 * ログ処理
 *
 * @author tokushima
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
		$this->class = isset($class) ? $class : null;
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