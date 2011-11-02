<?php
/**
 * 絵文字
 * @author tokushima
 * @see http://www.unicode.org/~scherer/emoji4unicode/snapshot/full.html
 * @see http://wap2.jp/download/spict/
 * @see http://creation.mb.softbank.jp/web/web_pic_about.html
 */
class Pictogram{
	static private $maps = array();
	/**
	 * 絵文字から絵文字コードへ変換する
	 * @param string $src
	 * @param string $carrier docomo/softbank/au
	 * @param string $encode sjis/utf8
	 * @return string
	 */
	static public function bin2code($src,$carrier,$encode="sjis"){
		if(!empty($src)){
			if(!isset(self::$maps[$carrier][$encode]["b2c"])){
				self::$maps[$carrier][$encode]["b2c"] = unserialize(File::gzread(module_path("maps/".$carrier."_".$encode."_code".".gz")));
			}
			$src = str_replace(array_values(self::$maps[$carrier][$encode]["b2c"]),array_keys(self::$maps[$carrier][$encode]["b2c"]),$src);
		}
		return $src;
	}
	/**
	 * 絵文字コードから絵文字へ変換する
	 * @param string $src
	 * @param string $carrier docomo/softbank/au
	 * @param string $encode sjis/utf8
	 * @const string $image_url 絵文字画像のURL
	 * @return string
	 */
	static public function code2bin($src,$carrier,$encode="sjis"){
		if(!empty($src) && strpos($src,"{e-") !== false){
			if(!isset(self::$maps[$carrier][$encode]["c2b"])){
				self::$maps[$carrier][$encode]["c2b"] = unserialize(File::gzread(module_path("maps/".$carrier."_code_".$encode.".gz")));
			}
			$src = str_replace(array_keys(self::$maps[$carrier][$encode]["c2b"]),array_values(self::$maps[$carrier][$encode]["c2b"]),$src);
		}
		return $src;
	}
	/**
	 * HTML向けに絵文字コードから絵文字へ変換する
	 * @param string $src
	 * @param boolean $secure httpsとするか
	 */
	static public function html_code2bin($src,$secure=false){
		if(!empty($src) && strpos($src,"{e-") !== false){
			if(Tag::setof($body,$src,"body")){
				foreach($body->in(array("textarea","input","select")) as $form){
					$src = str_replace($form->plain(),str_replace("{e-","@{E-@",$form->plain()),$src);
				}
			}			
			if(!isset(self::$maps["pc"]["utf8"]["c2b"])){
				self::$maps["pc"]["utf8"]["c2b"] = unserialize(File::gzread(module_path("maps/pc_code_utf8.gz")));
			}
			$src = str_replace(array_keys(self::$maps["pc"]["utf8"]["c2b"]),array_values(self::$maps["pc"]["utf8"]["c2b"]),$src);
			$image_url = File::path_slash(module_const("image_url",App::url("resources/media/pictogram",false)),null,true);
			if($secure) $image_url = str_replace("http://","https://",$image_url);
			$src = preg_replace("/{i-(e-\w+)}/","<img src=\"".$image_url."\\1.png\" />",$src);
			$src = str_replace("@{E-@","{e-",$src);
		}
		return $src;
	}
	/**
	 * 絵文字を取り除く
	 * @param string $src
	 * @param string $carrier docomo/softbank/au
	 * @param string $encode sjis/utf8
	 * @return string
	 */
	static public function trim($src,$carrier,$encode="sjis"){
		if(!empty($src)){
			if(!isset(self::$maps[$carrier][$encode]["b2c"])){
				self::$maps[$carrier][$encode]["b2c"] = unserialize(File::gzread(module_path("maps/".$carrier."_".$encode."_code".".gz")));
			}
			$src = str_replace(array_values(self::$maps[$carrier][$encode]["b2c"]),"",$src);
		}
		return $src;
	}
	/**
	 * 絵文字を１文字として文字列を丸める
	 * @param string $str 対象の文字列
	 * @param integer $width 指定の幅
	 * @param string $postfix 文字列がまるめられた場合に末尾に接続される文字列
	 * @return string
	 */
	static public function trim_width($str,$width,$postfix=''){
		$rtn = "";
		$cnt = 0;
		$len = mb_strlen($str);
		for($i=0;$i<$len;$i++,$l=0){
			if(preg_match("/\{e\-[0-9A-Z]{3}\}/",mb_substr($str,$i,7))){
				$c = mb_substr($str,$i,7);
				$l = 2;
				$i += 6;
			}else{
				$c = mb_substr($str,$i,1);
				$l = (mb_strwidth($c) > 1) ? 2 : 1;
			}
			$cnt += $l;
			if($width < $cnt) break;
			$rtn .= $c;
		}
		if($len > mb_strlen($rtn)) $rtn .= $postfix;
		return $rtn;
	}
	/**
	 * マッピングデータ
	 * @return array
	 */
	static public function maps(){
		return unserialize(File::gzread(module_path("maps/all.gz")));		
	}

	/**
	 * 絵文字画像ファイルをインストールする
	 * @param Request $req
	 * @param string $value インストールするフォルダパス
	 */
	static public function __setup_pictogram_install_images__(Request $req,$value){
		$out = (empty($value)) ? App::path("resources/media/pictogram") : $value;
		File::copy(module_path("images"),$out);
	}
	/**
	 * 絵文字用マッピングクラスの作成
	 */
	static public function __setup_pictogram_create_model__(){
		$class = array("docomo"=>array(),"au"=>array(),"softbank");
		$carriers = array("docomo","au","softbank");
		$encs = array("sjis","utf8");

		$maps = self::load_map();
		File::gzwrite(module_path("maps/all.gz"),serialize($maps));

		$html = "<html><body><table border=\"1\">\n";
		$html .= "<tr><th rowspan=\"2\">&nbsp;</th><th rowspan=\"2\">code</th><th rowspan=\"2\">image</th><th colspan=\"2\">docomo</th><th colspan=\"2\">softbank</th><th colspan=\"2\">au</th><th rowspan=\"2\">fallback</th></tr>\n";
		$html .= "<tr><th>unicode</th><th>sjis</th><th>unicode</th><th>sjis</th><th>unicode</th><th>sjis</th></tr>\n";
		$counter = 0;
		foreach($maps as $id => $map){
			$counter++;
			$html .= "<tr".(isset($map["fallback"]) ? " style=\"background-color:#cccccc;\"" : "").">";
			$html .= "<td>".$counter."</td>";
			$html .= "<td>{".$id."}</td>";
			$html .= "<td>".(isset($map["image"]) ? "<img src=\"".$map["image"]."\" width=\"50\" />" : "<span style=\"font-size:36px;\">".$map["char"]."</span>")."</td>";
			foreach(array("docomo","softbank","au") as $c){
				$html .= "<td>".(isset($map[$c]["UNICODE"]) ? "&amp;#x" : "").$map[$c]["UNICODE"]."</td><td>".$map[$c]["SJIS"]."</td>";
			}
			$html .= "<td>".$map["fallback"]."</td>";
			$html .= "</tr>\n";
		}
		$html .= "\n</table></body></html>";
		File::write(module_path("maps/all.html"),$html);
		
		foreach($encs as $encode){
			foreach($carriers as $carrier){
				$c2b = $b2c = array();
				foreach($maps as $id => $code){
					$id = "{".$id."}";
					$data = null;

					if(isset($code[$carrier]["SJIS"])){
						$data = pack("H*",$code[$carrier]["SJIS"]);
						if($encode === "utf8") $data = mb_convert_encoding($data,"UTF-8","SJIS-win");
						$b2c[$id] = $data;
					}else{
						$data = $code["fallback"];
						if($encode === "sjis") $data = mb_convert_encoding($data,"SJIS-win","UTF-8");
					}
					if($data !== null) $c2b[$id] = $data;
				}
				File::gzwrite(module_path("maps/".$carrier."_code_".$encode.".gz"),serialize($c2b));
				File::gzwrite(module_path("maps/".$carrier."_".$encode."_code.gz"),serialize($b2c));
			}
		}
		$c2b = array();
		$in_file = module_path("carrier_images/docomo")."/";
		$out_file = module_path("images")."/";
		foreach($maps as $k => $code){
			if(!empty($code["docomo"]["UNICODE"])){
				$id = "{".$k."}";
				$img_file = $in_file.$code["docomo"]["SJIS"].".png";			

				if(is_file($img_file)){
					$c2b[$id] = "{i-".$k."}";
					File::copy($img_file,$out_file.$k.".png");
				}else{
					$c2b[$id] = $code["fallback"];
				}
			}
		}
		File::gzwrite(module_path("maps/pc_code_utf8.gz"),serialize($c2b));
	}
	static private function load_map(){
		$result = array();
		$http = new Http();
		$url = "http://www.unicode.org/~scherer/emoji4unicode/snapshot/full.html";
		
		if(Tag::setof($tag,$http->do_get($url)->body(),"body")){
			$table = $tag->f("table");			
			foreach($table->in("tr") as $tr){
				$id = $tr->f("td[0].a.value()");

				if(!empty($id)){
					$line = array(
								"image"=>null
								,"char"=>null
								,"docomo"=>array("UNICODE"=>null,"SJIS"=>null)
								,"softbank"=>array("UNICODE"=>null,"SJIS"=>null)
								,"au"=>array("UNICODE"=>null,"SJIS"=>null)
								,"fallback"=>"〓"
							);
					if(strpos($tr->start(),"not_in_proposal") === false){
						$column = 3;
						foreach(array("docomo","au","softbank") as $c){
							$td = $tr->f("td[".$column."]");
							if(preg_match("/U\+(\w{4})/",$td->value(),$match)) $line[$c]["UNICODE"] = $match[1];
							if(preg_match("/SJIS\-(\w{4})/",$td->value(),$match)) $line[$c]["SJIS"] = $match[1];
							if(!isset($line[$c]["UNICODE"])) $line["fallback"] = trim($td->value());
							$column++;
						}
						if(!empty($line["docomo"]["UNICODE"]) || !empty($line["softbank"]["UNICODE"]) || !empty($line["au"]["UNICODE"])){
							$line["image"] = $tr->f("td[1].img.param(src)");
							if(!empty($line["image"])) $line["image"] = File::absolute(dirname($url),$line["image"]);
							$line["char"] = $tr->f("td[1].span.value()");
							$result[$id] = $line;
						}
					}
				}
			}
		}
		return $result;
	}
}