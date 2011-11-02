<?php
class CoreTestNotUrlMethod extends Flow{
	public function not_url_method(){
		$this->vars("hoge",123);
	}
	public function not_method(){
		$this->vars("hoge",456);
	}
	public function not_method_empty_url(){
		$this->vars("hoge",789);
	}
}