<?php
class CoreTestPutBlock extends Flow{
	public function index(){
		if($this->is_vars("hoge")){
			$this->put_block("put_block_".$this->in_vars("hoge").".html");
		}
	}
}