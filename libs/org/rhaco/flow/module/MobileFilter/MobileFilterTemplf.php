<?php
import("org.rhaco.lang.text.Pictogram");
/**
 * @author tokushima
 */
class MobileFilterTemplf extends Object{
	private $cookie;

	protected function __new__($cookie=false){
		$this->cookie = $cookie;
	}
	/**
	 * 文字列を丸める
	 * @param string $str 対象の文字列
	 * @param integer $width 指定の幅
	 * @param string $postfix 文字列がまるめられた場合に末尾に接続される文字列
	 * @return string
	 */
	public function trim_width($str,$width,$postfix=''){
		return Pictogram::trim_width($str,$width,$postfix);
	}
	/**
	 * cookieが使用可能か
	 * @return boolean
	 */
	public function use_cookie(){
		return $this->cookie;
	}
}
