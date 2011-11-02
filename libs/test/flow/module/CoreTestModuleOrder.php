<?php
class CoreTestModuleOrder{
	public function begin_flow_handle(Flow $flow){
		$flow->vars("order",$flow->in_vars("order")."1");
	}
	public function init_flow_handle(Flow $flow){
		$flow->vars("order",$flow->in_vars("order")."2");
	}
	public function before_flow_handle(Flow $flow){
		$flow->vars("order",$flow->in_vars("order")."3");		
	}
	public function after_flow_handle(Flow $flow){
		$flow->vars("order",$flow->in_vars("order")."4");		
	}
	public function init_template(&$src,Template $template){
		$src = $src."5";
	}
	public function before_template(&$src,Template $template){
		$src = $src."6";
	}
	public function after_template(&$src,Template $template){
		$src = $src."7";
	}
	public function before_exec_template(&$src,Template $template){
		$src = $src."8";
	}
	public function after_exec_template(&$src,Template $template){
		$src = $src."9";
	}
	public function before_flow_print_template(&$src,Flow $flow){
		$src = $src."10";
	}
}