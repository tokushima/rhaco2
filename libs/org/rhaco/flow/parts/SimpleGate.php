<?php
/**
 * シンプルな認証
 * @author tokushima
 */
class SimpleGate{
	/**
	 * 認証に失敗するとプログラムを終了する
	 * @const string $kawa 認証キー
	 */
	public function knock(){
		$req = new Request();
		if($req->in_vars("yama") != module_const("kawa",uniqid())){
			Log::debug("bye bye");
			exit;
		}
	}
}