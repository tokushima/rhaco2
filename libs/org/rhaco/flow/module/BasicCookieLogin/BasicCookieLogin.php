<?php
module("BasicCookieLoginException");
/**
 * user/passwordまたはcookieを利用した認証
 * @author tokushima
 */
class BasicCookieLogin extends Object{
	protected $user_name = "user_name";
	protected $password = "password";
	protected $remember_me = "remember_me";
	
	protected $user; # メンバ情報をセットする、ログイン成功時にRequst::userにセットされる

	/**
	 * cookieを利用したログイン条件
	 * @param string $request_tsid クッキーに入ってるID
	 */
	protected function cookie_condition($request_tsid){
		throw new BasicCookieLoginException('not implemented');
	}
	/**
	 * user_name/passwordを利用したログイン条件
	 * @param string $request_user_name リクエストされたuser_name
	 * @param string $request_password リクエストされたパスワード
	 */
	protected function account_condition($request_user_name,$request_password){
		throw new BasicCookieLoginException('not implemented');
	}
	/**
	 * ログイン成功後の処理
	 * $tsidをユニークな値に変更する
	 * @param Request $request
	 * @param string $tsid クッキーに保存されるID
	 */
	protected function before_set_cookie($request,&$tsid){
		throw new BasicCookieLoginException('not implemented');
	}
	/**
	 * @module Request
	 * @param Request $request
	 */
	public function login_condition(Request $request){
		$password = $request->in_vars($this->password());
		$request->rm_vars($this->password());
		try{
			if($request->in_vars($this->user_name()) != '' || $password != ''){
				try{
					$this->account_condition($request->in_vars($this->user_name()),$password);
					
					$tmp_session_id = $this->random_id();
					if($request->is_vars($this->remember_me())){
						$this->before_set_cookie($request,$tmp_session_id);
						$this->set_cookie($request,$tmp_session_id);
					}
					$request->user($this->user);
					return true;
				}catch(BasicCookieLoginException $e){
					Exceptions::add(new BasicCookieLoginException(trans('ユーザIDとパスワードを確認してください。')));
				}
			}
		}catch(BasicCookieLoginException $e){}
		return false;
	}
	/**
	 * @module Request
	 * @param Request $request
	 */
	public function silent_login_condition(Request $request){
		try{
			if($request->in_vars($this->tmp_session_id_name()) != ""){
				$this->cookie_condition($request->in_vars($this->tmp_session_id_name()));

				$tmp_session_id = $this->random_id();
				$this->before_set_cookie($request,$tmp_session_id);
				$this->set_cookie($request,$tmp_session_id);
				$request->user($this->user);
				return true;
			}
		}catch(BasicCookieLoginException $e){}
		return false;		
	}
	private function random_id(){
		return md5(microtime().mt_rand());
	}
	private function set_cookie($request,$tmp_session_id){
		$request->vars($this->tmp_session_id_name(),$tmp_session_id);
		$request->write_cookie($this->tmp_session_id_name(),(24*3600*14));
	}
	public function before_logout(Request $request){
		$request->delete_cookie($this->tmp_session_id_name());
	}
	protected function tmp_session_id_name(){
		return "TSID".substr(md5(get_class($this)),3,16);
	}
}
