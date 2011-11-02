<?php
import("org.rhaco.storage.db.Dao");
/**
 * クッキー情報とログイン情報をひも付けるモデル
 * @author tokushima
 * @var string $id cookieのキーとなる文字列@{"primary":true}
 * @var string $no ログイン情報とのひも付けを表す文字列@{"require":true,"max":32}
 * @var timestamp $expires
 */
class CookieLoginDao extends Dao{
	protected $id;
	protected $no;
	protected $expires;

	protected function __init__(){
		$this->expires = time() + 1209600; // 2week
	}
	/**
	 * クッキー情報を追加する
	 * @param string $id cookieのキーとなる文字列
	 * @param string $no ログイン情報とのひも付けを表す文字列
	 * @return string cookieのキーとなる文字列
	 */
	static public function set($id,$no){
		if(empty($id)) $id = md5(microtime().mt_rand());
		while(C(CookieLoginDao)->find_count(Q::eq("id",$id)) > 0) $id = md5(microtime().mt_rand());
		$self = new self();
		$self->id($id);
		$self->no($no);
		$self->save();		
		return $id;
	}
	/**
	 * 期限切れのデータを削除する
	 */
	public function flow_gc(){
		C(__CLASS__)->find_delete(Q::lt('expires',time()));
		C(__CLASS__)->commit();
	}
}
