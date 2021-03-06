<?php
/**
 * 例外
 * @author tokushima
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