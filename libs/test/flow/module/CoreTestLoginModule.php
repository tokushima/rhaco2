<?php
class CoreTestLoginModule{
	/**
	 * ログイン処理をやるFlowとは別物、ログインしてないはず
	 */
	public function begin_flow_handle(Flow $flow){
		$flow->vars("begin_flow_handle",($flow->is_login() ? "" : "BEGIN_FLOW_HANDLE"));
	}
	
	public function init_flow_handle(Flow $flow){
		$flow->vars("init_flow_handle",($flow->is_login() ? "INIT_FLOW_HANDLE" : ""));
	}
	public function before_flow_handle(Flow $flow){
		$flow->vars("before_flow_handle",($flow->is_login() ? "BEFORE_FLOW_HANDLE" : ""));
	}
	public function after_flow_handle(Flow $flow){
		$flow->vars("after_flow_handle",($flow->is_login() ? "AFTER_FLOW_HANDLE" : ""));		
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
	/**
	 * ログイン処理をやるFlowとは別物、ログインしてないはず
	 * @param Flow $flow
	 */
	public function before_flow_print_template(&$src,Flow $flow){
		$src = $src.($flow->is_login() ? "" : "BEFORE_FLOW_PRINT_TEMPLATE");
	}
	public function before_exec_template(&$src,Template $template){
		$src = $src."BEFORE_EXEC_TEMPLATE";
	}
	public function after_exec_template(&$src,Template $template){
		$src = $src."AFTER_EXEC_TEMPLATE";
	}
}