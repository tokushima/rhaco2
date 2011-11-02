<?php
import("org.rhaco.net.mail.Mail");
import("org.rhaco.lang.Env");
/**
 * ログをメール送信する
 *
 * 以下パスにテンプレートファイルがあれば送信
 * [template_path]/debug_mail.xml
 * [template_path]/info_mail.xml
 * [template_path]/warn_mail.xml
 * [template_path]/error_mail.xml
 *
 * @const template_base string mailテンプレートのパス
 * @author tokushima
 *
 */
class LogMailSender extends Object{
	private $template_base;
	private $default_template;

	protected function __init__(){
		$this->template_base = module_const("template_base",File::absolute(Template::base_template_path(),"mail"));
		$this->default_template = File::absolute($this->template_base,"log.xml");
	}
	public function debug(Log $log){
		$this->send('debug',$log);
	}
	public function info(Log $log){
		$this->send('info',$log);
	}
	public function warn(Log $log){
		$this->send('warn',$log);
	}
	public function error(Log $log){
		$this->send('error',$log);
	}
	protected function send($level,Log $log){
		$template = File::absolute($this->template_base,$level."_log.xml");
		if(!is_file($template)) $template = $this->default_template;
		if(is_file($template)){
			$mail = new Mail();
			$mail->send_template($template,array('log'=>$log,'env'=>new Env()));
		}
	}
}
