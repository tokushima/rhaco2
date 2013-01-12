<?php
class SupportTestFlowNoExt{
	private $init_count = 0;
	
	protected function __init__(){
		$this->init_count++;
	}
	public function init_count(){
		return array('init_count'=>$this->init_count);
	}
}