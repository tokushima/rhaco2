<?php
import("org.rhaco.net.mail.Mail");
module("SendGmailException");
/**
 * gmailでメール送信を行う
 * tls://を利用にするためOpenSSL サポートを有効にしてある必要がある
 *
 * @author Kazutaka Tokushima
 * @license New BSD License
 * @copyright Copyright 2008 rhaco project. All rights reserved.
 * @const string[] $account gmailのログインアカウント(address,password)
 */
class SendGmail extends Object{
	protected $login;
	protected $password;
	private $resource;

	protected function __new__($login=null,$password=null){
		/**
		 * gmailへのログイン
		 * @param 
		 */
		list($this->login,$this->password) = module_const_array("account",2);
		if(isset($login)){
			$this->login = $login;
			$this->password = $password;
		}
		$this->resource = fsockopen("tls://smtp.gmail.com",465,$errno,$errstr,30);
		if($this->resource === false) throw new SendGmailException("connect fail");
	}
	protected function __del__(){
		fclose($this->resource);
	}
	private function talk($message){
		fputs($this->resource,$message."\r\n");
		$message = fgets($this->resource,4096);
		list($code) = explode(" ",$message);
		switch($code){
			case 502:
			case 530:
			case 550:
			case 555:
				throw new SendGmailException($message);
			case 235:
			case 250: // OK
			case 334: // レスポンス待ち
			case 354: // 入力の開始
			case 221: // 転送チャンネルを閉じる
		}
		return true;
	}	
	public function send_mail(Mail $mail){
		if("" == fgets($this->resource,4096)) throw new Exception("not connection");
		$this->talk("HELO ".(isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : "localhost"));
		$this->talk("AUTH LOGIN");
		$this->talk(base64_encode($this->login));
		$this->talk(base64_encode($this->password));
		$this->talk(sprintf("MAIL FROM: <%s>",$this->login));
		foreach(array_keys($mail->to()) as $to) $this->talk(sprintf("RCPT TO: <%s>",$to));
		$this->talk("DATA");
		$this->talk($mail->manuscript().".");
		$rtn = $this->talk("QUIT");
	}
}
