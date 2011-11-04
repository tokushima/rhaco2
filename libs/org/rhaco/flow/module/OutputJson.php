<?php
/**
 * Jsonで出力する
 * @author tokushima
 */
class OutputJson{
	private $mode;
	private $varname;
	
	public function __construct($mode='json',$varname='callback'){
		$this->mode = strtolower($mode);
		$this->varname = $varname;
	}
	private function is_jsonp($flow){
		return ($this->mode == 'jsonp' && $flow->in_vars($this->varname) != '');
	}
	public function flow_another_output($flow){
		Log::disable_display();
		Http::send_header('Content-Type: '.($this->is_jsonp($flow) ? 'text/javascript' : 'application/json'));
		$json = array('result'=>array());

		foreach($flow->vars() as $k => $v){
			if(!$flow->is_cookie($k)) $json['result'][$k] = $v;
		}
		print(sprintf(($this->is_jsonp($flow) ? $flow->in_vars($this->varname).'(%s);' : '%s'),Text::to_json($json)));
		exit;
	}
	public function flow_handle_exception($exceptions,$flow){
		Log::disable_display();
		Http::send_header('Content-Type: '.($this->is_jsonp($flow) ? 'text/javascript' : 'application/json'));
		$json = array('error'=>array());
		foreach(Exceptions::groups() as $group){
			foreach(Exceptions::gets($group) as $e){
				$json['error'][] = array('message'=>$e->getMessage(),'group'=>$group,'type'=>get_class($e));
			}
		}
		print(sprintf(($this->is_jsonp($flow) ? $flow->in_vars($this->varname).'(%s);' : '%s'),Text::to_json($json)));
		exit;
	}
}