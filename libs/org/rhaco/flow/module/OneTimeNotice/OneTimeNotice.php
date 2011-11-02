<?php
module("OneTimeNoticeException");
/**
 * リクエスト中一度だけ表示させる
 * ※ map_nameをログインが必要なメソッドを指定すると無限ループになる可能性があります
 * 
 * コンストラクタの第一引数でNoticeのmapのnameを指定でき
 * 第二引数で例外発生時にリダイレクトされるmapのnameを指定できる
 * 
 * @author tokushima
 */
class OneTimeNotice extends Object{
	private $map_name;
	private $exception_map_name;

	protected function __new__($map_name="one_time_notice",$exception_map_name=null){
		$this->map_name = $map_name;
		$this->exception_map_name = $exception_map_name;
	}
	public function before_flow_handle(Flow $flow){
		$session_cheked = __CLASS__.'_checked_';
		$session_redirect = __CLASS__.'_redirect_url_';
		
		if(!$flow->is_sessions($session_cheked)){
			try{
				$notice_url = null;
				
				try{
					$notice_url = $flow->map_url($this->map_name);
				}catch(Exception $e){
					throw new OneTimeNoticeException(trans("undef map `{1}`",$this->map_name));
				}
				if($flow->request_url(false) != $notice_url){
					$flow->sessions($session_redirect,$flow->request_url());
					$flow->redirect($notice_url);
				}
				if($flow->is_post()){
					$flow->sessions($session_cheked,time());
					$redirect_url = $flow->rm_sessions($session_redirect);
					if(empty($redirect_url)) throw new OneTimeNoticeException(trans("undef redirect url"));
					$flow->redirect($redirect_url);
				}
			}catch(Exception $e){
				if(isset($this->exception_map_name)){
					$flow->redirect($flow->map_url($this->exception_map_name));
				}
				throw $e;
			}
		}
	}
}
