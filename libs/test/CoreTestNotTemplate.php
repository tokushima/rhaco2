<?php
class CoreTestNotTemplate extends Flow{
	public function aaa(){
		$this->vars("abc","ABC");
		$this->vars("newtag",new Tag("hoge","HOGE"));
	}
}