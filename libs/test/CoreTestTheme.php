<?php
class CoreTestTheme extends Flow{
	public function index(){
		if($this->is_vars("hoge")){
			$this->theme($this->in_vars("hoge"));
		}
	}
}
