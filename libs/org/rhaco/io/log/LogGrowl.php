<?php
import("org.rhaco.net.Growl");
/**
 * ログをGrowlする
 * @author tokushima
 * @const string $growl コンストラクタへ渡すdict
 */
class LogGrowl extends Object{
	protected $path;
	private $glowl;

	protected function __init__(){
		$this->growl = new Growl(module_const("growl"));
	}
	/**
	 * @see Log
	 * @param Log $log
	 * @param string $id
	 */
	public function info(Log $log,$id){
		$this->growl->normal($this->value($log),$log->file().':'.$log->line());
	}
	/**
	 * @see Log
	 * @param Log $log
	 * @param string $id
	 */
	public function warn(Log $log,$id){
		$this->growl->high($this->value($log),$log->file().':'.$log->line());
	}
	/**
	 * @see Log
	 * @param Log $log
	 * @param string $id
	 */
	public function error(Log $log,$id){
		$this->growl->emergency($this->value($log),$log->file().':'.$log->line(),true);
	}
	private function value(Log $log){
		$lines = 3;
		$ln = array();
		$l = explode("\n",$log->fm_value());
		for($i=0;$i<$lines&&$i<sizeof($l);$i++) $ln[] = $l[$i];
		return $value = implode("\n",$ln);
	}
}