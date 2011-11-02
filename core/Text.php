<?php
/**
 * テキスト処理
 * @author tokushima
 */
class Text{
	static private $detect_order = "JIS,UTF-8,eucjp-win,sjis-win,EUC-JP,SJIS";
	/**
	 * ヒアドキュメントのようなテキストを生成する
	 * １行目のインデントに合わせてインデントが消去される
	 * @param string $text 対象の文字列
	 * @return string
	 */
	final public static function plain($text){
		if(!empty($text)){
			$lines = explode("\n",$text);
			if(sizeof($lines) > 2){
				if(trim($lines[0]) == '') array_shift($lines);
				if(trim($lines[sizeof($lines)-1]) == '') array_pop($lines);
				return preg_match("/^([\040\t]+)/",$lines[0],$match) ? preg_replace("/^".$match[1]."/m","",implode("\n",$lines)) : implode("\n",$lines);
			}
		}
		return $text;
		/***
			$text = self::plain('
							aaa
							bbb
						');
			eq("aaa\nbbb",$text);
		 */
		/***
			$text = self::plain("hoge\nhoge");
			eq("hoge\nhoge",$text);
		 */
		/***
			$text = self::plain("hoge\nhoge\nhoge\nhoge");
			eq("hoge\nhoge\nhoge\nhoge",$text);
		 */
	}
	/**
	 * Jsonに変換して取得
	 * @param mixed $variable  対象の値
	 * @return string
	 */
	static public function to_json($variable){
		/***
		 * $variable = array(1,2,3);
		 * eq("[1,2,3]",self::to_json($variable));
		 * $variable = "ABC";
		 * eq("\"ABC\"",self::to_json($variable));
		 * $variable = 10;
		 * eq(10,self::to_json($variable));
		 * $variable = 10.123;
		 * eq(10.123,self::to_json($variable));
		 * $variable = true;
		 * eq("true",self::to_json($variable));
		 *
		 * $variable = array('foo', 'bar', array(1, 2, 'baz'), array(3, array(4)));
		 * eq('["foo","bar",[1,2,"baz"],[3,[4]]]',self::to_json($variable));
		 *
		 * $variable = array("foo"=>"bar",'baz'=>1,3=>4);
		 * eq('{"foo":"bar","baz":1,"3":4}',self::to_json($variable));
		 *
		 * $variable = array("type"=>"hoge","name"=>"fuga");
		 * eq('{"type":"hoge","name":"fuga"}',self::to_json($variable));
		 */
		/***
		 * # array
		 * $variable = array("name"=>"hoge","type"=>"fuga");
		 * eq('{"name":"hoge","type":"fuga"}',self::to_json($variable));
		 *
		 * $variable = array("aa","name"=>"hoge","type"=>"fuga");
		 * eq('{"0":"aa","name":"hoge","type":"fuga"}',self::to_json($variable));
		 *
		 * $variable = array("aa","hoge","fuga");
		 * eq('["aa","hoge","fuga"]',self::to_json($variable));
		 *
		 * $variable = array("aa","hoge","fuga");
		 * eq('["aa","hoge","fuga"]',self::to_json($variable));
		 * 
		 * $variable = array(array("aa"=>1),array("aa"=>2),array("aa"=>3));
		 * eq('[{"aa":1},{"aa":2},{"aa":3}]',self::to_json($variable));
		 */
		switch(gettype($variable)){
			case "boolean": return ($variable) ? "true" : "false";
			case "integer": return intval(sprintf("%d",$variable));
			case "double": return floatval(sprintf("%f",$variable));
			case "array":
				$list = array();
				$i = 0;
				foreach(array_keys($variable) as $key){
					if(!ctype_digit((string)$key) || $i !== (int)$key){
						foreach($variable as $key => $value) $list[] = sprintf("\"%s\":%s",$key,self::to_json($value));
						return sprintf("{%s}",implode(",",$list));
					}
					$i++;
				}
				foreach($variable as $key => $value) $list[] = self::to_json($value);
				return sprintf("[%s]",implode(",",$list));
			case "object":
				$list = array();
				foreach((($variable instanceof Object) ? $variable->hash() : get_object_vars($variable)) as $key => $value){
					$list[] = sprintf("\"%s\":%s",$key,self::to_json($value));
				}
				return sprintf("{%s}",implode(",",$list));
			case "string":
				return sprintf("\"%s\"",addslashes($variable));
			default:
		}
		return "null";
	}
	/**
	 * JSONPとして出力
	 * @param mixied $var 対象の値
	 * @param string $callback コールバック名
	 * @param string $encode 文字エンコード
	 */
	static public function output_jsonp($var,$callback=null,$encode="UTF-8"){
		Log::disable_display();
		Http::send_header("Content-Type: application/json; charset=".$encode);
		print(str_replace(array("\r\n","\r","\n"),array("\\n"),(empty($callback) ? Text::to_json($var) : ($callback."(".Text::to_json($var).");"))));
		exit;
	}
	/**
	 * JsonからPHPの変数に変換
	 * @param string $json JSON文字列
	 * @return mixed
	 */
	static public function parse_json($json){
		if(!is_string($json)) return $json;
		$json = self::seem($json);
		if(!is_string($json)) return $json;
		$json = preg_replace("/[\s]*([,\:\{\}\[\]])[\s]*/","\\1",
						preg_replace("/[\"].*?[\"]/esm",'str_replace(array(",",":","{","}","[","]"),array("#B#","#C#","#D#","#E#","#F#","#G#"),"\\0")',
							str_replace(array('\\\\','\\"','$',"\\'"),array('#J#','#A#','#H#','#I#'),trim($json))));
		if(preg_match("/^\"([^\"]*?)\"$/",$json)){
			return str_replace('#J#','\\',stripcslashes(str_replace(array('#A#','#B#','#C#','#D#','#E#','#F#','#G#','#H#','#I#'),array('\\"',',',':','{','}','[',']','$',"\\'"),substr($json,1,-1))));
		}
		$start = substr($json,0,1);
		$end = substr($json,-1);
		if(($start == '[' && $end == ']') || ($start == '{' && $end == '}')){
			$hash = ($start == '{');
			$src = substr($json,1,-1);
			$list = array();
			while(strpos($src,'[') !== false){
				list($value,$start,$end) = self::block($src,'[',']');
				if($value === null) return null;
				$src = str_replace("[".$value."]",str_replace(array('[',']',','),array('#AA#','#AB','#AC'),'['.$value.']'),$src);
			}
			while(strpos($src,'{') !== false){
				list($value,$start,$end) = self::block($src,'{','}');
				if($value === null) return null;
				$src = str_replace('{'.$value.'}',str_replace(array('{','}',','),array('#BA#','#BB','#AC'),'{'.$value.'}'),$src);
			}
			foreach(explode(',',$src) as $value){
				if($value === '') return null;
				$value = str_replace(array('#AA#','#AB','#BA#','#BB','#AC'),array('[',']','{','}',','),$value);

				if($hash){
					$exp = explode(':',$value,2);
					if(sizeof($exp) != 2) throw new InvalidArgumentException('value error'); 
					list($key,$var) = $exp;
					$index = self::parse_json($key);
					if($index === null) $index = $key;
					$list[$index] = self::parse_json($var);
				}else{
					$list[] = self::parse_json($value);
				}
			}
			return $list;
		}
		return null;
		/***
			$variable = "ABC";
			eq($variable,self::parse_json('"ABC"'));
			$variable = 10;
			eq($variable,self::parse_json(10));
			$variable = 10.123;
			eq($variable,self::parse_json(10.123));
			$variable = true;
			eq($variable,self::parse_json("true"));
			$variable = false;
			eq($variable,self::parse_json("false"));
			$variable = null;
			eq($variable,self::parse_json("null"));
			$variable = array(1,2,3);
			eq($variable,self::parse_json("[1,2,3]"));
			$variable = array(1,2,array(9,8,7));
			eq($variable,self::parse_json("[1,2,[9,8,7]]"));
			$variable = array(1,2,array(9,array(10,11),7));
			eq($variable,self::parse_json("[1,2,[9,[10,11],7]]"));
			
			$variable = array("A"=>"a","B"=>"b","C"=>"c");
			eq($variable,self::parse_json('{"A":"a","B":"b","C":"c"}'));
			$variable = array("A"=>"a","B"=>"b","C"=>array("E"=>"e","F"=>"f","G"=>"g"));
			eq($variable,self::parse_json('{"A":"a","B":"b","C":{"E":"e","F":"f","G":"g"}}'));
			$variable = array("A"=>"a","B"=>"b","C"=>array("E"=>"e","F"=>array("H"=>"h","I"=>"i"),"G"=>"g"));
			eq($variable,self::parse_json('{"A":"a","B":"b","C":{"E":"e","F":{"H":"h","I":"i"},"G":"g"}}'));
			
			$variable = array("A"=>"a","B"=>array(1,2,3),"C"=>"c");
			eq($variable,self::parse_json('{"A":"a","B":[1,2,3],"C":"c"}'));
			$variable = array("A"=>"a","B"=>array(1,array("C"=>"c","D"=>"d"),3),"C"=>"c");
			eq($variable,self::parse_json('{"A":"a","B":[1,{"C":"c","D":"d"},3],"C":"c"}'));
			
			$variable = array(array("a"=>1,"b"=>array("a","b",1)),array(null,false,true));
			eq($variable,self::parse_json('[ {"a" : 1, "b" : ["a", "b", 1] }, [ null, false, true ] ]'));
			
			eq(null,self::parse_json("[1,2,3,]"));
			eq(null,self::parse_json("[1,2,3,,,]"));
			
			if(extension_loaded("json")) eq(null,json_decode("[1,[1,2,],3]"));
			eq(array(1,null,3),self::parse_json("[1,[1,2,],3]"));
			eq(null,self::parse_json('{"A":"a","B":"b","C":"c",}'));
			
			eq(array("hoge"=>"123,456"),self::parse_json('{"hoge":"123,456"}'));
		*/
		/***
			# quote
			eq(array("hoge"=>'123,"456'),self::parse_json('{"hoge":"123,\\"456"}'));
			eq(array("hoge"=>"123,'456"),self::parse_json('{"hoge":"123,\'456"}'));
			eq(array("hoge"=>'123,\\"456'),self::parse_json('{"hoge":"123,\\\\\\"456"}'));
			eq(array("hoge"=>"123,\\'456"),self::parse_json('{"hoge":"123,\\\\\'456"}'));
		 */
		/***
			# escape
			eq(array("hoge"=>"\\"),self::parse_json('{"hoge":"\\\\"}'));
			eq(array("hoge"=>"a\\"),self::parse_json('{"hoge":"a\\\\"}'));
			eq(array("hoge"=>"t\\t"),self::parse_json('{"hoge":"t\\\\t"}'));
			eq(array("hoge"=>"\tA"),self::parse_json('{"hoge":"\\tA"}'));
		 */
		/***
		 	# value_error
		 	try{
			 	self::parse_json("{'hoge':'123,456'}");
			 	fail();
			 }catch(InvalidArgumentException $e){
			 	success();
			 }
		 */
	}
	/**
	 * 指定の開始文字／終了文字でくくられた部分を取得
	 * ブロックの中身,ブロックの開始位置,ブロックの終了位置を返す
	 * @param string $src 対象の文字列
	 * @param string $start ブロックの開始位置
	 * @param string $end ブロックの終了位置
	 * @return mixed[]
	 */
	static public function block($src,$start,$end){
		/***
		 * $src = "xyz[abc[def]efg]hij";
		 * $rtn = self::block($src,"[","]");
		 * eq(array("abc[def]efg",3,16),$rtn);
		 * eq("[abc[def]efg]",substr($src,$rtn[1],$rtn[2] - $rtn[1]));
		 *
		 * $src = "[abc[def]efg]hij";
		 * eq(array("abc[def]efg",0,13),self::block($src,"[","]"));
		 *
		 * $src = "[abc[def]efghij";
		 * eq(array(null,0,15),self::block($src,"[","]"));
		 *
		 * $src = "[abc/def/efghij";
		 * eq(array("def",4,9),self::block($src,"/","/"));
		 *
		 * $src = "[abc|def|efghij";
		 * eq(array("def",4,9),self::block($src,"|","|"));
		 *
		 * $src = "[abc<abc>def</abc>efghij";
		 * eq(array("def",4,18),self::block($src,"<abc>","</abc>"));
		 *
		 * $src = "[abc<abc>def<abc>efghij";
		 * eq(array("def",4,17),self::block($src,"<abc>","<abc>"));
		 *
		 * $src = "[<abc>abc<abc>def</abc>efg</abc>hij";
		 * $rtn = self::block($src,"<abc>","</abc>");
		 * eq(array("abc<abc>def</abc>efg",1,32),$rtn);
		 * eq("<abc>abc<abc>def</abc>efg</abc>",substr($src,$rtn[1],$rtn[2] - $rtn[1]));
		 */
		$eq = ($start == $end);
		if(preg_match_all("/".(($end == null || $eq) ? preg_quote($start,"/") : "(".preg_quote($start,"/").")|(".preg_quote($end,"/").")")."/sm",$src,$match,PREG_OFFSET_CAPTURE)){
			$count = 0;
			$pos = null;

			foreach($match[0] as $key => $value){
				if($value[0] == $start){
					$count++;
					if($pos === null) $pos = $value[1];
				}else if($pos !== null){
					$count--;
				}
				if($count == 0 || ($eq && ($count % 2 == 0))) return array(substr($src,$pos + strlen($start),($value[1] - $pos - strlen($start))),$pos,$value[1] + strlen($end));
			}
		}
		return array(null,0,strlen($src));
	}
	/**
	 * シンプルなyamlからphpに変換
	 * @param string $src YAML文字列
	 * @return mixed[]
	 */
	static public function parse_yaml($src){
		$src = preg_replace("/([\"\'])(.+)\\1/me",'str_replace(array("#",":"),array("__SHAPE__","__COLON__"),"\\0")',$src);
		$src = preg_replace("/^([\t]+)/me",'str_replace("\t"," ","\\1")',str_replace(array("\r\n","\r","\n"),"\n",$src));
		$src = preg_replace("/#.+$/m","",$src);
		$stream = array();

		if(!preg_match("/^[\040]*---(.*)$/m",$src)) $src = "---\n".$src;
		if(preg_match_all("/^[\040]*---(.*)$/m",$src,$match,PREG_OFFSET_CAPTURE | PREG_SET_ORDER)){
			$blocks = array();
			$size = sizeof($match) - 1;

			foreach($match as $c => $m){
				$obj = new stdClass();
				$obj->header = ltrim($m[1][0]);
				$obj->nodes = array();
				$node = array();
				$offset = $m[0][1] + mb_strlen($m[0][0]);
				$block = ($size == $c) ? mb_substr($src,$offset) :
											mb_substr($src,$offset,$match[$c+1][0][1] - $offset);
				foreach(explode("\n",$block) as $key => $line){
					if(!empty($line)){
						if($line[0] == " "){
							$node[] = $line;
						}else{
							self::yamlnodes($obj,$node);
							$result = self::yamlnode($node);
							$node = array($line);
						}
					}
				}
				self::yamlnodes($obj,$node);
				array_shift($obj->nodes);
				$stream[] = $obj;
			}
		}
		return $stream;
		/***
			$yml = text('
						--- hoge
						a: mapping
						foo: bar
						---
						- a
						- sequence
					');
			$obj1 = (object)array("header"=>"hoge","nodes"=>array("a"=>"mapping","foo"=>"bar"));
			$obj2 = (object)array("header"=>"","nodes"=>array("a","sequence"));
			$result = array($obj1,$obj2);
			eq($result,self::parse_yaml($yml));

			$yml = text('
						---
						This: top level mapping
						is:
							- a
							- YAML
							- document
					');
			$obj1 = (object)array("header"=>"","nodes"=>array("This"=>"top level mapping","is"=>array("a","YAML","document")));
			$result = array($obj1);
			eq($result,self::parse_yaml($yml));

			$yml = text('
						--- !recursive-sequence &001
						- * 001
						- * 001
					');
			$obj1 = (object)array("header"=>"!recursive-sequence &001","nodes"=>array("* 001","* 001"));
			$result = array($obj1);
			eq($result,self::parse_yaml($yml));

			$yml = text('
						a sequence:
							- one bourbon
							- one scotch
							- one beer
					');
			$obj1 = (object)array("header"=>"","nodes"=>array("a sequence"=>array("one bourbon","one scotch","one beer")));
			$result = array($obj1);
			eq($result,self::parse_yaml($yml));

			$yml = text('
						a scalar key: a scalar value
					');
			$obj1 = (object)array("header"=>"","nodes"=>array("a scalar key"=>"a scalar value"));
			$result = array($obj1);
			eq($result,self::parse_yaml($yml));

			$yml = text('
						- a plain string
						- -42
						- 3.1415
						- 12:34
						- 123 this is an error
					');
			$obj1 = (object)array("header"=>"","nodes"=>array("a plain string",-42,3.1415,"12:34","123 this is an error"));
			$result = array($obj1);
			eq($result,self::parse_yaml($yml));

			$yml = text('
						- >
						 This is a multiline scalar which begins on
						 the next line. It is indicated by a single
						 carat.
					');
			$obj1 = (object)array("header"=>"","nodes"=>array("This is a multiline scalar which begins on the next line. It is indicated by a single carat."));
			$result = array($obj1);
			eq($result,self::parse_yaml($yml));

			$yml = text('
						- |
						 QTY  DESC		 PRICE TOTAL
						 ===  ====		 ===== =====
						 1  Foo Fighters  $19.95 $19.95
						 2  Bar Belles	$29.95 $59.90
					');
			$rtext = text('
						QTY  DESC		 PRICE TOTAL
						===  ====		 ===== =====
						1  Foo Fighters  $19.95 $19.95
						2  Bar Belles	$29.95 $59.90
						');
			$obj1 = (object)array("header"=>"","nodes"=>array($rtext));
			$result = array($obj1);
			eq($result,self::parse_yaml($yml));

			$yml = text('
						-
						  name: Mark McGwire
						  hr:   65
						  avg:  0.278
						-
						  name: Sammy Sosa
						  hr:   63
						  avg:  0.288
					');
			$obj1 = (object)array("header"=>"","nodes"=>array(
													array("name"=>"Mark McGwire","hr"=>65,"avg"=>0.278),
													array("name"=>"Sammy Sosa","hr"=>63,"avg"=>0.288)));
			$result = array($obj1);
			eq($result,self::parse_yaml($yml));

			$yml = text('
						hr:  65	# Home runs
						avg: 0.278 # Batting average
						rbi: 147   # Runs Batted In
					');
			$obj1 = (object)array("header"=>"","nodes"=>array("hr"=>65,"avg"=>0.278,"rbi"=>147));
			$result = array($obj1);
			eq($result,self::parse_yaml($yml));

			$yml = text('
						name: Mark McGwire
						accomplishment: >
						  Mark set a major league
						  home run record in 1998.
						stats: |
						  65 Home Runs
						  0.278 Batting Average
					');
			$obj1 = (object)array("header"=>"","nodes"=>array(
												"name"=>"Mark McGwire",
												"accomplishment"=>"Mark set a major league home run record in 1998.",
												"stats"=>"65 Home Runs\n0.278 Batting Average"));
			$result = array($obj1);
			eq($result,self::parse_yaml($yml));
		*/
	}
	static private function yamlnodes(&$obj,$node){
		$result = self::yamlnode($node);
		if(is_array($result) && sizeof($result) == 1){
			if(isset($result[1])){
				$obj->nodes[] = array_shift($result);
			}else{
				$obj->nodes[key($result)] = current($result);
			}
		}else{
			$obj->nodes[] = $result;
		}
	}
	static private function yamlnode($node){
		$result = $child = $sequence = array();
		$line = $indent = 0;
		$isseq = $isblock = $onblock = $ischild = $onlabel = false;
		$name = "";
		$node[] = null;

		foreach($node as $value){
			if(!empty($value) && $value[0] == " ") $value = substr($value,$indent);
			switch($value[0]){
				case "[":
				case "{":
					return $value;
					break;
				case " ":
					if($indent == 0 && preg_match("/^[\040]+/",$value,$match)){
						$indent = strlen($match[0]) - 1;
						$value = substr($value,$indent);
					}
					if($isseq){
						if($onlabel){
							$result[$name] .= (($onblock) ? (($isblock) ? "\n" : " ") : "").ltrim(substr($value,1));
						}else{
							$sequence[$line] .= (($onblock) ? (($isblock) ? "\n" : " ") : "").ltrim(substr($value,1));
						}
						$onblock = true;
					}else{
						$child[] = substr($value,1);
					}
					break;
				case "-":
					$line++;
					$value = ltrim(substr($value,1));
					$isseq = $isblock = false;
					switch(trim($value)){
						case "": $ischild = true;
						case "|": $isblock = true; $onblock = false;
						case ">": $value = ""; $isseq = true;
					}
					$sequence[$line] = self::yamlunescape($value);
					break;
				default:
					if(empty($value) && !empty($sequence)){
						if($ischild){
							foreach($sequence as $key => $seq) $sequence[$key] = self::yamlnode(explode("\n",$seq));
							return $sequence;
						}
						return (sizeof($sequence) == 1) ? $sequence[1] : array_merge($sequence);
					}else if($name != "" && !empty($child)){
						$result[$name] = self::yamlnode($child);
					}
					$onlabel = false;
					if(substr(rtrim($value),-1) == ":"){
						$name = ltrim(self::yamlunescape(substr(trim($value),0,-1)));
						$result[$name] = null;
					}else if(strpos($value,":") !== false){
						list($tmp,$value) = explode(":",$value);
						$tmp = self::yamlunescape(trim($tmp));
						switch(trim($value)){
							case "|": $isblock = true; $onblock = false;
							case ">": $isseq = $onlabel = true; $result[$name = $tmp] = ""; break;
							default: $result[$tmp] = self::yamlunescape(ltrim($value));
						}
					}
					$child = array();
					$indent = 0;
			}
		}
		return $result;
	}
	static private function yamlunescape($value){
		return self::seem(preg_replace("/^(['\"])(.+)\\1.*$/","\\2",str_replace(array("__SHAPE__","__COLON__"),array("#",":"),$value)));
	}
	/**
	 * 文字列をそれっぽい型にして返す
	 * @param string $value 対象の文字列
	 * @return mixed
	 */
	static public function seem($value){
		if(!is_string($value)) throw new InvalidArgumentException("not string");
		if(is_numeric(trim($value))) return (strpos($value,".") !== false) ? floatval($value) : intval($value);
		switch(strtolower($value)){
			case "null": return null;
			case "true": return true;
			case "false": return false;
			default: return $value;
		}
		/***
			eq(null,self::seem("null"));
			eq(null,self::seem("NULL"));
			eq(true,self::seem("true"));
			eq(true,self::seem("True"));
			eq(false,self::seem("false"));
			eq(false,self::seem("FALSE"));
			eq(100,self::seem("100"));
			eq(100.05,self::seem("100.05"));
			eq("abc",self::seem("abc"));
		 */
	}
	/**
	 * 文字列中に指定した文字列がすべて存在するか
	 *
	 * @param string $str 対象の文字列
	 * @param string $query 検索する文字列
	 * @param string $delimiter 検索する文字列を分割する文字列
	 * @return boolean
	 */
	static public function match($str,$query,$delimiter=" "){
		foreach(explode($delimiter,$query) as $q){
			if(mb_strpos($str,$q) === false) return false;
		}
		return true;
		/***
			eq(true,self::match("abcdefghijklmn","abc ghi"));
			eq(true,self::match("abcdefghijklmn","abc_ghi","_"));
			eq(true,self::match("あいうえおかきくけこ","うえ け"));
		 */
	}
	/**
	 * 大文字小文字を区別せず、文字列中に指定した文字列がすべて存在するか
	 *
	 * @param string $str 対象の文字列
	 * @param string $query 検索する文字列
	 * @param string $delimiter 検索する文字列を分割する文字列
	 * @return boolean
	 */
	static public function imatch($str,$query,$delimiter=" "){
		foreach(explode($delimiter,$query) as $q){
			if(
				(function_exists("mb_stripos") && mb_stripos($str,$q) === false)
				|| mb_strpos(strtolower($str),strtolower($q)) === false
				) return false;
		}
		return true;
		/***
			eq(true,self::imatch("abcdefghijklmn","aBc ghi"));
			eq(true,self::imatch("abcdefghijklmn","abc_gHi","_"));
			eq(true,self::imatch("あいうえおかきくけこ","うえ け"));
		 */
	}
	/**
	 * 文字列配列をtrimする
	 * @param string $value 対象の文字列
	 *
	 * @return string[]
	 */
	static public function trim(){
		/***
			eq(array("aaa","bbb","ccc"),self::trim("  aaa ","bbb","ccc   "));
			eq(array("aaa","bbb","ccc"),self::trim(array("  aaa ","bbb","ccc   ")));
		*/
		$result = array();
		$args = (func_num_args() === 1 && is_array(func_get_arg(0))) ? func_get_arg(0) : func_get_args();
		foreach($args as $arg) $result[] = trim($arg);
		return $result;
	}
	/**
	 * 改行コードをLFに統一する
	 * @param string $src 対象の文字列
	 * @return string
	 */
	static public function uld($src){
		/***
		 * eq("a\nb\nc\n",self::uld("a\r\nb\rc\n"));
		 */
		return str_replace(array("\r\n","\r"),"\n",$src);
	}
	/**
	 * コメント部分を除去
	 * @param string $src 対象の文字列
	 * @return string
	 */
	static public function uncomment($src){
		return preg_replace("/\/\*.+?\*\//s","",$src);
		/***
			eq("hogehoge",self::uncomment("hoge/*ABC*"."/hoge"));
		 */
	}
	/**
	 * HTMLデコードした文字列を返す
	 * @param string $value 対象の文字列
	 * @return string
	 */
	static public function htmldecode($value){
		if(!empty($value) && is_string($value)){
			$value = mb_convert_encoding($value,"UTF-8",mb_detect_encoding($value));
			$value = preg_replace("/&#[xX]([0-9a-fA-F]+);/eu","'&#'.hexdec('\\1').';'",$value);
			$value = mb_decode_numericentity($value,array(0x0,0x10000,0,0xfffff),"UTF-8");
			$value = html_entity_decode($value,ENT_QUOTES,"UTF-8");
			$value = str_replace(array("\\\"","\\'","\\\\"),array("\"","\'","\\"),$value);
		}
		return $value;
		/***
		 * eq("ほげほげ",self::htmldecode("&#12411;&#12370;&#12411;&#12370;"));
		 * eq("&gt;&lt;ほげ& ほげ",self::htmldecode("&amp;gt;&amp;lt;&#12411;&#12370;&amp; &#12411;&#12370;"));
		 */
	}
	/**
	 * htmlエンコードをする
	 * @param string $value 対象の文字列
	 * @return string
	 */
	final static public function htmlencode($value){
		if(!empty($value) && is_string($value)){
			$value = mb_convert_encoding($value,"UTF-8",mb_detect_encoding($value));
			return htmlentities($value,ENT_QUOTES,"UTF-8");
		}
		return $value;
		/***
			eq("&lt;abc aa=&#039;123&#039; bb=&quot;ddd&quot; cc=&quot;http://hoge?a=1&amp;b=2&quot;&gt;あいう&lt;/abc&gt;",self::htmlencode("<abc aa='123' bb=\"ddd\" cc=\"http://hoge?a=1&b=2\">あいう</abc>"));
		 */
	}
	/**
	 * 文字エンコード
	 *
	 * @param string $value 対象の文字列
	 * @param string $enc 変換後の文字エンコード
	 * @param string $from 元の文字エンコード
	 * @return string
	 */
	final static public function encode($value,$enc="UTF-8",$from=null){
		if(is_string($value)) return mb_convert_encoding($value,$enc,(empty($from) ? self::$detect_order : $from));
		if(is_array($value)){
			foreach($value as $k => $v){
				$value[self::encode($k,$enc,$from)] = self::encode($v,$enc,$from);
			}
		}
		return $value;
		/***
			eq("test",self::encode("test"));
		 */
	}
	/**
	 * フォーマット文字列 $str に基づき生成された文字列を返します。
	 *
	 * @param string $str 対象の文字列
	 * @param mixed[] $params フォーマット中に現れた置換文字列{1},{2}...を置換する値
	 * @return string
	 */
	final static public function fstring($str,$params){
		if(preg_match_all("/\{([\d]+)\}/",$str,$match)){
			$params = func_get_args();
			array_shift($params);
			if(is_array($params[0])) $params = $params[0];

			foreach($match[1] as $key => $value){
				$i = ((int)$value) - 1;
				$str = str_replace($match[0][$key],isset($params[$i]) ? $params[$i] : "",$str);
			}
		}
		return $str;
		/***
			$params = array("A","B","C");
			eq("aAbBcCde",self::fstring("a{1}b{2}c{3}d{4}e",$params));
			eq("aAbBcAde",self::fstring("a{1}b{2}c{1}d{4}e",$params));
			eq("aAbBcAde",self::fstring("a{1}b{2}c{1}d{4}e","A","B","C"));
		 */
	}
	/**
	 * 文字数を返す
	 * @param string $str 対象の文字列
	 * @param string $enc 文字エンコード
	 * @return integer
	 */
	final static public function length($str,$enc=null){
		if(is_array($str)){
			$length = 0;
			foreach($str as $value){
				if($length < self::length($value,$enc)) $length = self::length($value,$enc);
			}
			return $length;
		}
		return mb_strlen($str,empty($enc) ? mb_detect_encoding($str,self::$detect_order,true) : $enc);
		/***
			eq(3,self::length("abc"));
			eq(5,self::length(array("abc","defgh","i")));
		 */
	}
	/**
	 * 文字列の部分を返す
	 * @param string $str 対象の文字列
	 * @param integer $start 開始位置
	 * @param integer $length 最大長
	 * @param string $enc 文字コード
	 * @return string
	 */
	final static public function substring($str,$start,$length=null,$enc=null){
		return mb_substr($str,$start,empty($length) ? self::len($str) : $length,empty($enc) ? mb_detect_encoding($str,self::$detect_order,true) : $enc);
		/***
			eq("def",self::substring("abcdefg",3,3));
		 */
	}
	/**
	 * 文字列から配列にする
	 * @param string $dict 対象の文字列
	 * @return mixed{}
	 */
	final static public function dict($dict){
		$result = array();
		if(is_string($dict) && strpos($dict,'=') !== false){
			$dict = preg_replace("/(\(.+\))|(([\"\']).+?\\3)/e",'stripcslashes(str_replace(",","__ANNON_COMMA__","\\0"))',$dict);			

			foreach(explode(',',$dict) as $arg){
				if($arg != ''){
					$exp = explode('=',$arg,2);
					if(sizeof($exp) !== 2) throw new InvalidArgumentException('syntax error `'.$arg.'`');
					if(substr($exp[1],-1) == ',') $exp[1] = substr($exp[1],0,-1);
					$value = ($exp[1] === '') ? null : str_replace('__ANNON_COMMA__',',',$exp[1]);
					$result[trim($exp[0])] = ($value === 'true') ? true : (($value === 'false') ? false : $value);
				}
			}
		}
		return $result;
		/***
			eq(array("a"=>1,"b"=>2,"c"=>3),self::dict("a=1,b=2,c=3"));
			eq(array("a"=>1,"b"=>2,"c"=>3),self::dict("a=1, b=2,c =3"));
			eq(array("a"=>"A","b"=>"(B,C)","c"=>"D"),self::dict("a=A,b=(B,C),c=D"));
			eq(array("a"=>"A","b"=>"'B,C'","c"=>"D"),self::dict("a=A,b='B,C',c=D"));
			try{
				self::dict("a=A,b='B,C,c=D");
				fail();
			}catch(InvalidArgumentException $e){
				success();
			}
		 */
	}
	/**
	 * 文字列表現を返す
	 * @param Object $obj 対象の値
	 * @return string
	 */
	final static public function str($obj){
		if(is_bool($obj)) return ($obj) ? "true" : "false";
		if(!is_object($obj)) return (string)$obj;
		return (string)$obj;
		/***
			$name = create_class('');
			$obj = new $name;
			eq($name,self::str($obj));
			eq("1",self::str(1));
		 */
	}
}