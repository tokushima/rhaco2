<?php
class SupportTestFlow extends Flow{
	private $init_count = 0;
	
	protected function __init__(){
		$this->init_count++;
	}
	public function init_count(){
		$this->vars('init_count',$this->init_count);
	}
	public function check_session(){
		$ses = __CLASS__.'::'.__METHOD__;
		$var = $this->in_sessions($ses);
		if(empty($var)) $var = 0;
		$var++;
		$this->sessions($ses,$var);
		
		$this->vars('count',$var);
	}
	public function check_login(){
		$this->vars('is_login',$this->is_login());
		$this->vars('user',$this->user());
	}
	public function redirect_check_login(){
		$this->redirect_method('check_login');
	}
}