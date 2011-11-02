<?php
module("SimpleAuthException");
/**
 * 単純な認証モジュール
 * 
 * @author Kazutaka Tokushima
 * @license New BSD License
 */
class SimpleAuth extends Object{
	protected $user;
	
	/**
	 * Requestのモジュール
	 * @const string $auth string ユーザ,string md5(sha1(パスワード))
	 * @param Request $request
	 * @return boolean
	 */
	public function login_condition(Request $request){
		$users = module_const_array("auth");
		$password = $request->in_vars("password");
		$request->rm_vars("password");
		if(sizeof($users) % 2 !== 0) throw new SimpleAuthException();
		for($i=0;$i<sizeof($users);$i+=2){
			list($user,$pass) = array($users[$i],$users[$i+1]);

			if($request->is_post() && $request->in_vars("login") === $user && md5(sha1($password)) === $pass){
				$this->user($user);
				$request->user($this);
				return true;
			}
		}
		return false;
	}
	/**
	 * Requestのモジュール
	 */
	public function login_invalid(Request $request){
		$users = module_const_array("auth");
		if(!empty($users)){
			$flow = new Flow();
			$flow->output(module_path("templates/login.html"));
		}
	}
}
