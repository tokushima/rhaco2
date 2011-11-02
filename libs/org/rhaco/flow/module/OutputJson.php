<?php
/**
 * Jsonで出力する
 * @author tokushima
 */
class OutputJson{
	public function flow_another_output($flow){
		Log::disable_display();
		Http::send_header('Content-Type: application/json');
		$json = array('result'=>array());

		foreach($flow->vars() as $k => $v){
			if(!$flow->is_cookie($k)) $json['result'][$k] = $v;
		}
		print(Text::to_json($json));
		exit;
	}
	public function flow_handle_exception($exceptions,$flow){
		Log::disable_display();
		Http::send_header('Content-Type: application/json');
		$json = array('error'=>array());
		foreach(Exceptions::groups() as $group){
			foreach(Exceptions::gets($group) as $e){
				$json['error'][] = array('message'=>$e->getMessage(),'group'=>$group,'type'=>get_class($e));
			}
		}
		print(Text::to_json($json));
		exit;
	}
}