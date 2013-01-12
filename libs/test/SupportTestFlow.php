<?php
class SupportTestFlow extends Flow{
	private $init_count = 0;
	
	protected function __init__(){
		$this->init_count++;
	}
	public function init_count(){
		$this->vars('init_count',$this->init_count);
	}
}