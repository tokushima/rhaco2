<?php
/**
 * Flow実行時の使用メモリと速度を計測する
 * @author tokushima
 *
 */
class FlowBench extends Object{
	static private $start_time = 0;

	public function init_flow_handle(){
		self::$start_time = microtime(true);
	}
	public function after_flow_handle(Flow $flow){
		if(function_exists('memory_get_usage')){
			$bench = sprintf('%s ( %s / %s MByte | %s sec)'
						,(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : (isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : null))
						,round(number_format((memory_get_usage() / 1024 / 1024),3),2)
						,round(number_format((memory_get_peak_usage() / 1024 / 1024),3),2)
						,round((microtime(true) - (float)self::$start_time),4)
					);
			Object::C(__CLASS__)->call_module('info',new Log(3,$bench,__CLASS__));
		}
	}
}