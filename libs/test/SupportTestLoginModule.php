<?php
/**
 * ログインのテスト
 * @author tokushima
 *
 */
class SupportTestLoginModule extends Object{
	public function login_condition(Request $request){
		if($request->is_post()){
			$password = $request->in_vars("password");
			$user_name = $request->in_vars("user_name");
			$dat_file = $request->in_vars("user_data_file");
			
			$request->rm_vars("user_name");;
			$request->rm_vars("password");
			$request->rm_vars("user_data_file");
			
			if($user_name == "hogeuser" && $password == "hogehoge" && !empty($dat_file)){
				$dat = App::work($dat_file);
				if(!File::exist($dat)) File::write($dat,0);
				$count = File::read($dat);
				$count++;
				File::write($dat,$count);
				$request->user(array('count'=>$count));
				
				return true;
			}
		}
		return false;
	}
}