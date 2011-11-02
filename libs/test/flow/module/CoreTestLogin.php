<?php
/**
 * ログインのテスト
 * @author tokushima
 *
 */
class CoreTestLogin extends Object{
	public function login_condition(Request $request){
		if($request->is_post()){
			$password = $request->in_vars("password");
			$request->rm_vars("password");			
			
			if($request->in_vars("user_name") == "hogeuser" && $password == "hogehoge"){
				return true;
			}
		}
		return false;
	}
}