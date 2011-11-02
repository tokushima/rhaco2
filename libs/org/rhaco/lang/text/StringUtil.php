<?php
/**
 * 文字列を操作するユーティリティ
 * 
 * @author Kazutaka Tokushima
 * @license New BSD License
 */
class StringUtil{
	/**
	 * スペース4をTABに置換する
	 *
	 * @param string $path
	 */
	static public function s2t($path,$ext="php,html"){
		$conds = array();
		foreach(explode(",",$ext) as $e){
			$e = trim($e);
			if(!empty($e)) $conds[] = "\\.".$e;
		}
		foreach(File::ls($path,true) as $file){
			if(preg_match("/".implode("|",$conds)."$/",$file->oname())){
				$pre = $value = $file->get();
				$value = preg_replace("/([\"\']).*[\040]{4}?\\1/e",'str_replace(str_repeat(" ",4),"|__"."SPACE4"."__|","\\0")',$value);
				$value = str_replace(str_repeat(" ",4),"\t",$value);
				$value = str_replace("|__"."SPACE4"."__|",str_repeat(" ",4),$value);
				if($pre !== $value) File::write($file->fullname(),$value);
			}
		}
	}
	/**
	 * TABをスペース4に置換する
	 *
	 * @param string $path
	 */
	static public function t2s($path,$ext="php,html"){
		$conds = array();
		foreach(explode(",",$ext) as $e){
			$e = trim($e);
			if(!empty($e)) $conds[] = "\\.".$e;
		}
		foreach(File::ls($path,true) as $file){
			if(preg_match("/".implode("|",$conds)."$/",$file->oname())){
				$pre = $value = $file->get();
				$value = preg_replace("/([\"\']).*\t?\\1/e",'str_replace("\t","|__"."TAB4"."__|","\\0")',$value);
				$value = str_replace("\t",str_repeat(" ",4),$value);
				$value = str_replace("|__"."TAB4"."__|","\t",$value);
				if($pre !== $value) File::write($file->fullname(),$value);
			}
		}
	}

	/**
	 * 指定のディレクトリ内の.phpファイルのCRLFをLFに変換する
	 *
	 * @param string $path
	 */
	static public function crlf2lf($path){
		foreach(File::ls($path,true) as $file){
			if($file->ext() == ".php") File::write($file->fullname(),str_replace(array("\r\n","\r"),"\n",$file->get()));
		}
	}
	/**
	 * コメントブロックを削除して書き出し
	 * @param $input_path
	 * @param $output_path
	 * @return unknown
	 */
	static public function uncomment($input_path,$output_path){
		if(substr($input_path,-1) !== "/") $input_path .= "/";
		foreach(File::ls($input_path,true) as $f){
			$path = File::absolute($output_path,str_replace($input_path,"",$f->fullname()));
			if($f->is_class()){
				File::write($path,preg_replace("/\n[\s]+\n/m","\n",preg_replace("/\/\*.+?\*\//ms","",File::read($f))));
			}else{
				File::copy($f->fullname(),$path);
			}
		}
	}
	/**
	 *  $value中に$searchが存在するか
	 *
	 * @param string $value 対象の文字列
	 * @param string $search 検索する正規表現文字列
	 * @return boolean
	 */
	static public function exist($value,$search){
		/***
			eq(true,self::exist("aaabbbccc","aaa"));
			eq(true,self::exist("aaa/bbb/ccc","a/b"));
			eq(false,self::exist("aaa/bbb/ccc","a/b/c"));
		*/
		return (preg_match("/".preg_quote($search,"/")."/",$value)) ? true : false;
	}
}