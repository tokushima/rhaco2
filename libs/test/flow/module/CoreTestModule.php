<?php
class CoreTestModule{
	public function begin_flow_handle(Flow $flow){
		$flow->vars("begin_flow_handle","BEGIN_FLOW_HANDLE");		
	}
	public function init_flow_handle(Flow $flow){
		$flow->vars("init_flow_handle","INIT_FLOW_HANDLE");
	}
	public function before_flow_handle(Flow $flow){
		$flow->vars("before_flow_handle","BEFORE_FLOW_HANDLE");		
	}
	public function after_flow_handle(Flow $flow){
		$flow->vars("after_flow_handle","AFTER_FLOW_HANDLE");		
	}
	public function init_template(&$src,Template $template){
		$src = $src."INIT_TEMPLATE";
	}
	public function before_template(&$src,Template $template){
		$src = $src."BEFORE_TEMPLATE";
	}
	public function after_template(&$src,Template $template){
		$src = $src."AFTER_TEMPLATE";
	}
	public function before_flow_print_template(&$src,Flow $flow){
		$src = $src."BEFORE_FLOW_PRINT_TEMPLATE";
	}
	public function before_exec_template(&$src,Template $template){
		$src = $src."BEFORE_EXEC_TEMPLATE";
	}
	public function after_exec_template(&$src,Template $template){
		$src = $src."AFTER_EXEC_TEMPLATE";
	}
	public function flow_handle_exception($e,Flow $flow){
		print("FLOW_HANDLE_EXCEPTION");
	}
}