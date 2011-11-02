<?php
module('GoogleAuthException');
/**
 * Google の認証ブラウザ
 * @author yabeken
 * @license New BSDLicense
 */
class GoogleAuth extends Http{
	protected $_login_ = false;
	/**
	 * ログイン
	 * @param string $email
	 * @param string $password
	 * @return boolean
	 */
	public function login($email=null,$password=null){
		if(!$this->is_login()){
			if($email === null){
				list($email,$password) = module_const_array("account",2);
			}
			$this->do_get("https://www.google.com/accounts/ServiceLogin");
			$this->vars("Email",$email);
			$this->vars("Passwd",$password);
			$this->submit();
			if(strpos($this->url,"https://www.google.com/accounts/CheckCookie?chtml=LoginDoneHtml") !== 0){
				throw new GoogleAuthException("login failed [{$email}]");
			}
			$this->_login_ = true;
		}
		return true;
	}
	/**
	 * ログイン済みか
	 * @return boolean
	 */
	public function is_login(){
		return $this->_login_;
	}
	/**
	 * ログアウト
	 */
	public function logout(){
		if($this->is_login()){
			$this->do_get('http://www.google.co.jp/accounts/ClearSID');
			$this->_login_ = false;
		}
	}
}