<?php
/**
 * 配列を扱うユーティリティ
 * @author tokushima
 */
class ArrayUtil{
	/**
	 * 変数がハッシュか
	 * @param array $var
	 * @return boolean
	 */
	static public function is_hash($var){
		/***
		 * eq(false,self::is_hash(array("A","B","C")));
		 * eq(false,self::is_hash(array(0=>"A",1=>"B",2=>"C")));
		 * eq(true,self::is_hash(array(1=>"A",2=>"B",3=>"C")));
		 * eq(true,self::is_hash(array("a"=>"A","b"=>"B","c"=>"C")));
		 */
		if(!is_array($var)) return false;
		$keys = array_keys($var);
		$size = sizeof($keys);

		for($i=0;$i<$size;$i++){
			if($keys[$i] !== $i) return true;
		}
		return false;
	}
	/**
	 * 配列として取得
	 *
	 * @param array $array
	 * @param integer $low
	 * @param integer $high
	 * @return array
	 */
	static public function arrays($array,$offset=0,$length=0,$fill=false){
		/***
		 * eq(1,sizeof(self::arrays(array(0,1),1,1)));
		 * eq(2,sizeof(self::arrays(array(0,1,2),0,2)));
		 * eq(3,sizeof(self::arrays(array(0,1),0,3,true)));
		 * eq(2,sizeof(self::arrays(array(0,1,2,3,4),3,6)));
		 * eq(3,sizeof(self::arrays(array(0,1,2,3,4,5,6,7,8,9),3,3)));
		 * eq(1,sizeof(self::arrays(array(0,1,2,3,4,5,6,7,8,9),3,1)));
		 * eq(7,sizeof(self::arrays(array(0,1,2,3,4,5,6,7,8,9),3)));
		 * eq(3,sizeof(self::arrays(array(0,1,2,3,4,5,6,7,8,9),-3,3)));
		 * eq(1,sizeof(self::arrays(array(0,1,2,3,4,5,6,7,8,9),-3,1)));
		 * eq(3,sizeof(self::arrays(array(0,1,2,3,4,5,6,7,8,9),-3,5)));
		 * eq(7,sizeof(self::arrays(array(0,1,2,3,4,5,6,7,8,9),0,-3)));
		 * eq(1,sizeof(self::arrays(array(0))));
		 * eq(1,sizeof(self::arrays(array(0),0,-1)));
		 * eq(2,sizeof(self::arrays(array(0,1,2,3,4,5,6,7,8,9),8,-3)));
		 * 
		 * eq(array("abc"),self::arrays("abc"));
		 * eq(array("abc","123"),self::arrays(array("abc","123")));
		 */
		$array = (is_array($array)) ? $array : (is_null($array) ? array() : array($array));
		if($offset == 0 && $length == 0) return $array;
		$array = (empty($length) || ($length < 0 && (sizeof($array) - ($offset - $length)) <= 0)) ? array_slice($array,$offset) : array_slice($array,$offset,$length);
		if($fill) for($i=sizeof($array);$i<$length;$i++) $array[] = null;
		return $array;
	}
	/**
	 * 配列要素を文字列により連結する
	 * @param array $array
	 * @param string $glue 配列の要素を glue 文字列で連結します。
	 * @param integer $offset
	 * @param integer $length
	 * @param boolean $fill
	 * @return string
	 */
	static public function implode($array,$glue="",$offset=0,$length=0,$fill=false){
		/***
		 * eq("hogekokepopo",self::implode(array("hoge","koke","popo")));
		 * eq("koke:popo",self::implode(array("hoge","koke","popo"),":",1));
		 * eq("koke",self::implode(array("hoge","koke","popo"),":",1,1));
		 * eq("hoge:koke:popo::",self::implode(array("hoge","koke","popo"),":",0,5,true));
		 * 
		 */
		return implode($glue,self::arrays($array,$offset,$length,$fill));
	}
	
	/**
	 * ハッシュからキーをcase insensitiveで値を取得する
	 *
	 * @param array $array
	 * @param string $name
	 * @return mixed
	 */
	static public function get($array,$name){
		/***
		 * $list = array("ABC"=>"AA","deF"=>"BB","gHi"=>"CC");
		 * 
		 * eq("AA",self::get($list,"abc"));
		 * eq("BB",self::get($list,"def"));
		 * eq("CC",self::get($list,"ghi"));
		 * 
		 * eq(null,self::get($list,"jkl"));
		 * eq(null,self::get("ABCD","jkl"));
		 */
		if(!is_array($array)) return null;
		$array = array_change_key_case($array);
		$name = strtolower($name);
		return (array_key_exists($name,$array)) ? $array[$name] : null;
	}
	
	/**
	 * 配列のキーと値を逆にしてキーを小文字に変換する
	 *
	 * @param array $list
	 * @return array
	 */
	static public function lowerflip($list){
		/***
		 * $list = array("abc"=>"hoGe","def"=>123,"ghi"=>"__A__");
		 * eq(array("hoge"=>"abc",123=>"def","__a__"=>"ghi"),self::lowerflip($list));
		 */
		if(is_array($list)) return array_change_key_case(array_flip($list));
		return $list;
	}
	
	/**
	 * $patternで分割されたハッシュキーを持つ値にして返す
	 *
	 * @param string $pattern 検索するパターンを表す正規表現文字列
	 * @param string $key
	 * @param mixed $value
	 * @return array
	 */
	static public function splitkeys($pattern,$key,$value){
		/***
		 * $result = self::splitkeys("/","/abc/def/ghi/jklmn","hoge");
		 * eq("hoge",$result["abc"]["def"]["ghi"]["jklmn"]);
		 * 
		 * $result = self::splitkeys("/","/abc/bbb","hoge");
		 * eq("hoge",$result["abc"]["bbb"]);
		 * 
		 * $result = self::splitkeys("/","/abc/111","hoge");
		 * eq("hoge",$result["abc"][111]);
		 */
		$result = $value;		
		$list = preg_split("/".str_replace(array("\/","/","__SLASH__"),array("__SLASH__","\/","\/"),$pattern)."/",$key);
		krsort($list);
		foreach($list as $k){
			if($k !== "" && $k !== null) $result = array($k=>$result);
		}
		return $result;
	}
	/**
	 * 配列をマージする
	 * 同じキーは常に上書き
	 * @return array
	 */
	static public function merge(){
		$args = func_get_args();
		$result = array_shift($args);
		if(!is_array($result)) $result = array($result);
		
		foreach($args as $arg){
			if(is_array($arg)){
				foreach($arg as $k => $v){
					if(!isset($result[$k]) || !is_array($result[$k])) $result[$k] = array();
					$result[$k] = (is_array($v)) ? self::merge($result[$k],$v) : $v;
				}
			}else{
				$result = $arg;
			}
		}
		return $result;		
		/***
			$list1 = "START";
			$list2 = array("A"=>1,"B"=>2);
			$result = array(0=>"START","A"=>1,"B"=>2);
			eq($result,self::merge($list1,$list2));
			
			$list1 = array("A"=>array("a"=>"hoge","b"=>"hoge"));
			$list2 = array("A"=>"hoge");
			$result = array("A"=>"hoge");
			eq($result,self::merge($list1,$list2));
			
			$list1 = array("A"=>"hoge");
			$list2 = array("A"=>array("a"=>"hoge","b"=>"hoge"));
			$result = array("A"=>array("a"=>"hoge","b"=>"hoge"));
			eq($result,self::merge($list1,$list2));			

			$list1 = array("aaa"=>array("bbb"=>array("ccc"=>array("ddd"=>"hoge"))));
			$list2 = array("AAA"=>array("BBB"=>array("CCC"=>"hoge")));
			$list3 = array("aaa"=>array("bbb"=>array("ccc"=>array("eee"=>"hoge"))));
			$list4 = array("AAA"=>array(111=>"hoge"));
			$result = array("aaa"=>array("bbb"=>array("ccc"=>array("ddd"=>"hoge","eee"=>"hoge"))),"AAA"=>array("BBB"=>array("CCC"=>"hoge"),111=>"hoge"));
			eq($result,self::merge($list1,$list2,$list3,$list4));
			
			$list0 = array();
			$list1 = array("aaa"=>array("bbb"=>array("ccc"=>array("ddd"=>"hoge"))));
			$list2 = array("AAA"=>array("BBB"=>array("CCC"=>"hoge")));
			$list3 = array("aaa"=>array("bbb"=>array("ccc"=>array("eee"=>"hoge"))));
			$list4 = array("AAA"=>array(111=>"hoge"));

			$list0 = self::merge($list0,$list1);
			$list0 = self::merge($list0,$list2);
			$list0 = self::merge($list0,$list3);
			$list0 = self::merge($list0,$list4);
			$result = array("aaa"=>array("bbb"=>array("ccc"=>array("ddd"=>"hoge","eee"=>"hoge"))),"AAA"=>array("BBB"=>array("CCC"=>"hoge"),111=>"hoge"));			
			eq($result,$list0);
		 */
	}
}
