<?php
import("test.CoreTestM");
class CoreTestL{
	public function login_condition(Request $req){
		$req->user(new CoreTestM());
		return true;
	}
}