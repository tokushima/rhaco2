<?php
/**
 * テンプレートで利用するフォーマットツール
 * @author tokushima
 */
class Templf{
	private $counter = array();
	private $flow;

	public function __construct($flow=null){
		if($flow instanceof Flow) $this->flow = $flow;
	}
	/**
	 * handlerのマップ名を呼び出しているURLを生成する
	 * 引数を与える事も可能
	 * @param string $name マップ名
	 * @return string
	 */
	public function map_url($name){
		if($this->flow instanceof Flow){
			$args = func_get_args();
			return call_user_func_array(array($this->flow,'map_url'),$args);
		}
		return null;
	}
	/**
	 * handlerでpackageを呼び出してる場合にメソッド名でURLを生成する
	 * 引数を与える事も可能
	 * @param string $name メソッド名
	 * @return string
	 */
	public function package_method_url($name){
		if($this->flow instanceof Flow){
			$args = func_get_args();
			return call_user_func_array(array($this->flow,'package_method_url'),$args);
		}
		return null;
	}
	/**
	 * マッチしたパターン（名）を返す
	 * @return string
	 */
	public function match_pattern(){
		return ($this->flow instanceof Flow) ? ($this->flow->is_name() ?  $this->flow->name() : $this->flow->pattern()) : null;
	}
	/**
	 * マッチしたパターンと$patternが同じなら$trueを、違うなら$falseを返す
	 * @param string $pattern 比較する文字列
	 * @param string $true 一致した場合に返す文字列
	 * @param string $false 一致しなかった場合に返す文字列
	 * @return string
	 */
	public function match_pattern_switch($pattern,$true='on',$false=''){
		return ($this->match_pattern() == $pattern) ? $true : $false;
	}
	/**
	 * 真偽値により$trueまたは$falseを返す
	 * @param boolean $cond 真偽値
	 * @param string $true 真の場合に返す文字列
	 * @param string $false 偽の場合に返す文字列
	 * @return string
	 */
	public function cond_switch($cond,$true='on',$false=''){
		return ($cond === true) ? $true : $false;
		/***
			$t = new self();
			eq('on',$t->cond_switch(true,'on','off'));
			eq('off',$t->cond_switch(false,'on','off'));
			eq('off',$t->cond_switch(1,'on','off'));
		*/
	}
	/**
	 * リクエストされたURLを返す
	 * @return string
	 */
	public function request_url($query=true){
		return ($this->flow instanceof Flow) ? $this->flow->request_url($query) : null;
	}
	/**
	 * refererを返す
	 * @return string
	 */
	public function referer(){
		return Http::referer();
	}
	/**
	 * ログイン済みか
	 * @return boolean
	 */
	public function is_login(){
		return ($this->flow instanceof Flow) ? $this->flow->is_login() : false;
	}
	/**
	 * ログインユーザを返す
	 * @return mixed
	 */
	public function user(){
		return ($this->flow instanceof Flow) ? $this->flow->user() : null;
	}
	/**
	 * アプリケーションのメディアのURLを返す
	 * @param string $url ベースのURLに続く相対パス
	 * @return string
	 */
	public function media($url=null){
		return ($this->flow instanceof Flow) ? File::absolute($this->flow->media_url(),$url) : null;
	}	
	/**
	 * アプリケーションのURLを返す
	 * @param string $path ベースのURLに続く相対パス
	 * @param boolean $branch ブランチ名を結合するか
	 * @return string
	 */
	public function url($path=null,$branch=true){
		return App::url($path,$branch);
	}
	/**
	 * アプリケーションのURLをhttpsで返す
	 * @param string $path ベースのURLに続く相対パス
	 * @param boolean $branch ブランチ名を結合するか
	 * @return string
	 */
	public function surl($path=null,$branch=true){
		return App::surl($path,$branch);
	}
	/**
	 * query文字列に変換する
	 * Http::queryのエイリアス
	 *
	 * @param mixed $var query文字列化する変数
	 * @param string $name ベースとなる名前
	 * @param boolean $null nullの値を表現するか
	 * @return string
	 */
	public function query($var,$name=null,$null=true){
		return Http::query($var,$name,$null);
		/***
			$t = new self();
			eq("req=123",$t->query("123","req"));
			eq("req[0]=123",$t->query(array(123),"req"));
		 */
	}
	/**
	 * ゼロを桁数分前に埋める
	 * @param integer $int 対象の値
	 * @param $dig 0埋めする桁数
	 * @return string
	 */
	public function zerofill($int,$dig=0){
		return sprintf("%0".$dig."d",$int);
		/***
			$t = new self();
			eq("00005",$t->zerofill(5,5));
			eq("5",$t->zerofill(5));
		 */
	}
	/**
	 * 数字を千位毎にグループ化してフォーマットする
	 * @param number $number 対象の値
	 * @param integer $dec 小数点以下の桁数
	 * @return string
	 */
	public function number_format($number,$dec=0){
		return number_format($number,$dec,".",",");
		/***
			$t = new self();
			eq("123,456,789",$t->number_format("123456789"));
			eq("123,456,789.020",$t->number_format("123456789.02",3));
			eq("123,456,789",$t->number_format("123456789.02"));
		 */
	}
	/**
	 * カウンタ
	 * @param string $name カウンタ名
	 * @param integer $increment 増加値
	 * @return integer
	 */
	public function counter($name,$increment=1){
		if(!isset($this->counter[$name])) $this->counter[$name] = 0;
		$this->counter[$name] = $this->counter[$name] + $increment;
		return $this->counter[$name];
		/***
			$t = new self();
			eq(1,$t->counter("hoge"));
			eq(2,$t->counter("hoge"));
			eq(3,$t->counter("hoge"));
			eq(1,$t->counter("fuga"));
			eq(2,$t->counter("fuga"));
			eq(4,$t->counter("hoge"));
		 */
	}
	/**
	 * カウント
	 * @param mixed $var 対象の値
	 * @return integer
	 */
	public function count($var){
		return sizeof($var);
		/***
			$t = new self();
			eq(3,$t->count(array(1,2,3)));
		 */
	}
	/**
	 * フォーマットした日付を返す
	 * @param integer $value 時間
	 * @param string $format フォーマット文字列 ( http://jp2.php.net/manual/ja/function.date.php )
	 * @return string
	 */
	public function df($value,$format="Y/m/d H:i:s"){
		return date($format,$value);
		/***
			$t = new self();
			$time = time();
			eq(date("YmdHis",$time),$t->df($time,"YmdHis"));
		 */
	}
	/**
	 * HTMLエスケープされた文字列を返す
	 * @param string $value 対象の文字列
	 * @param integer $length 取得する文字列の最大長
	 * @param integer $lines 取得する文字列の最大行数
	 * @param string $postfix 文字列が最大長または最大行数を超えた場合に末尾に接続される文字列
	 * @return string
	 */
	public function html($value,$length=0,$lines=0,$postfix=null){
		$value = Tag::cdata(str_replace(array("\r\n","\r"),"\n",$value));
		if($length > 0){
			$det = mb_detect_encoding($value);
			$value = mb_substr($value,0,$length,$det).((mb_strlen($value,$det) > $length) ? $postfix : null);
		}
		if($lines > 0){
			$ln = array();
			$l = explode("\n",$value);
			for($i=0;$i<$lines;$i++) $ln[] = $l[$i];
			$value = implode("\n",$ln).((sizeof($l) > $lines) ? $postfix : null);
		}
		return nl2br(str_replace(array("<",">","'","\""),array("&lt;","&gt;","&#039;","&quot;"),$value));
		/***
			$t = new self();
			eq("&lt;hoge&gt;hoge&lt;/hoge&gt;<br />\n&lt;hoge&gt;hoge&lt;/hoge&gt;",$t->html("<hoge>hoge</hoge>\n<hoge>hoge</hoge>"));
			eq("aaa<br />\nb",$t->html("aaa\nbbb\nccc",5));
			eq("aaa<br />\nbbb",$t->html("aaa\nbbb\nccc",0,2));
			eq("aaa<br />\nb",$t->html("aaa\nbbb\nccc",5,2));
			eq("aaa<br />\nbbb..",$t->html("aaa\nbbb\nccc",0,2,".."));
			eq("aaa<br />\nb..",$t->html("aaa\nbbb\nccc",5,2,".."));			
		 */
	}
	/**
	 * 全てのタグを削除した文字列を返す
	 * @param string $value 対象の文字列
	 * @param integer $length 取得する文字列の最大長
	 * @param integer $lines 取得する文字列の最大行数
	 * @param string $postfix 文字列が最大長または最大行数を超えた場合に末尾に接続される文字列
	 * @return string
	 */
	public function text($value,$length=0,$lines=0,$postfix=null){
		return self::html(preg_replace("/<.+?>/","",$value),$length,$lines,$postfix);
		/***
			$t = new self();
			eq("hoge<br />\nhoge",$t->text("<hoge>hoge</hoge>\n<hoge>hoge</hoge>"));
			eq("aaa<br />\nb",$t->text("aaa\nbbb\nccc",5));
			eq("aaa<br />\nbbb",$t->text("aaa\nbbb\nccc",0,2));
			eq("aaa<br />\nb",$t->text("aaa\nbbb\nccc",5,2));
		 */
	}
	/**
	 * brタグを改行コードに変換
	 * @param $src 変換する文字列
	 * @return string
	 */
	public function br2nl($src){
		foreach(Tag::anyhow($src)->in("br") as $t){
			$src = str_replace($t->get(),"\n",$src);
		}
		return $src;
		/***
			$body = text('hoge<br />hoge<br>hoge<br /><br />hoge');
			$t = new self();
			eq("hoge\nhoge<br>hoge\n\nhoge",$t->br2nl($body));
		 */
	}
	/**
	 * 改行を削除(置換)する
	 *
	 * @param string $value 対象の文字列
	 * @param string $glue 置換後の文字列
	 * @return string
	 */
	public function one_liner($value,$glue=" "){
		return str_replace(array("\r\n","\r","\n","<br>","<br />"),$glue,$value);
		/***
			$t = new self();
			eq("a bc    d ef  g ",$t->one_liner("a\nbc\r\n\r\n\n\rd<br>ef<br /><br />g<br>"));
			eq("abcdefg",$t->one_liner("a\nbc\r\n\r\n\n\rd<br>ef<br /><br />g<br>",""));
			eq("a-bc----d-ef--g-",$t->one_liner("a\nbc\r\n\r\n\n\rd<br>ef<br /><br />g<br>","-"));
		 */
	}
	/**
	 * 文字列を丸める
	 * @param string $str 対象の文字列
	 * @param integer $width 指定の幅
	 * @param string $postfix 文字列がまるめられた場合に末尾に接続される文字列
	 * @return string
	 */
	public function trim_width($str,$width,$postfix=''){
		$rtn = "";
		$cnt = 0;
		$len = mb_strlen($str);
		for($i=0;$i<$len;$i++){
			$c = mb_substr($str,$i,1);
			$cnt += (mb_strwidth($c) > 1) ? 2 : 1;
			if($width < $cnt) break;
			$rtn .= $c;
		}
		if($len > mb_strlen($rtn)) $rtn .= $postfix;
		return $rtn;
		/***
			$t = new self();
			$str = "あいうえお12345かきくけこ";
			eq("あいう",$t->trim_width($str,7));
			
			$t = new self();
			$str = "あいうえお12345かきくけこ";
			eq("あいう...",$t->trim_width($str,7,"..."));
			
			$t = new self();
			$str = "あいうえお12345かきくけこ";
			eq("あいうえお123...",$t->trim_width($str,13,"..."));

			$t = new self();
			$str = "あいうえお12345かきくけこ";
			eq("あいうえお12345かきくけこ",$t->trim_width($str,50,"..."));	
			
			$t = new self();
			$str = "あいうえお12345かきくけこ";
			eq("あいうえお12345かきくけこ",$t->trim_width($str,30,"..."));
		 */
	}
	/**
	 * htmlエンコードをする
	 * @param string $value 対象の文字列
	 * @return string
	 */
	public function htmlencode($value){
		return Text::htmlencode(Tag::cdata($value));
		/***
			$t = new self();
			eq("&lt;abc aa=&#039;123&#039; bb=&quot;ddd&quot;&gt;あいう&lt;/abc&gt;",$t->htmlencode("<abc aa='123' bb=\"ddd\">あいう</abc>"));
		 */
	}
	/**
	 * htmlデコードをする
	 * @param string $value 対象の文字列
	 * @return string
	 */
	public function htmldecode($value){
		return Text::htmldecode(self::cdata($value));
		/***
			$t = new self();
			eq("ほげほげ",$t->htmldecode("&#12411;&#12370;&#12411;&#12370;"));
			eq("&gt;&lt;ほげ& ほげ",$t->htmldecode("&amp;gt;&amp;lt;&#12411;&#12370;&amp; &#12411;&#12370;"));
			eq("<abc />",$t->htmldecode("<![CDATA[<abc />]]>"));
		 */
	}
	/**
	 * CDATA形式から値を取り出す
	 * @param string $value 対象の文字列
	 * @return string
	 */
	public function cdata($value){
		return Tag::cdata($value);
		/***
			$t = new self();
			eq("<abc />",$t->cdata("<![CDATA[<abc />]]>"));
		 */
	}
	/**
	 * 何もしない
	 * @param mixed $var そのまま返す値
	 * @return mixed
	 */
	public function noop($var){
		return $var;
		/***
			$t = new self();
			eq("hoge",$t->noop("hoge"));
		 */
	}
	/**
	 * primary型の値を返す
	 * @param Object $obj 対象のObject
	 * @param string $default デフォルト値
	 * @return string
	 */
	public function primary($obj,$default=null){
		if($obj instanceof Object){
			$primarys = array();
			foreach($obj->props() as $prop){
				if($obj->a($prop,'primary') === true) $primarys[] = $obj->{$prop}();
			}
			if(!empty($primarys)) return implode('_',$primarys);
		}
		return (isset($default) ? $default : Text::str($obj));
		/***
			$name1 = create_class('
				protected $id;
				protected function __str__(){
					return "hoge";
				}
			',null,'
				@var serial $id
			');
			$o = new $name1("id=1");
			$t = new self();
			eq(1,$t->primary($o));
			
			$name1 = create_class('
				protected $id;
				protected function __str__(){
					return "hoge";
				}
			',null,'
				@var number $id @{"primary":true}
			');
			$o = new $name1("id=1");
			$t = new self();
			eq(1,$t->primary($o));
			
			$name1 = create_class('
				protected $id1;
				protected $id2;
				protected function __str__(){
					return "hoge";
				}
			',null,'
				@var number $id1 @{"primary":true}
				@var number $id2 @{"primary":true}
			');
			$o = new $name1("id1=1,id2=4");
			$t = new self();
			eq("1_4",$t->primary($o));
			
			$name1 = create_class('
				protected function __str__(){
					return "hoge";
				}
			');
			$o = new $name1();
			$t = new self();
			eq("hoge",$t->primary($o));
			
			$name1 = create_class('
				protected function __str__(){
					return "hoge";
				}
			');			
			$o = new $name1();
			$t = new self();
			eq("fuga",$t->primary($o,"fuga"));
		 */
	}
	/**
	 * 文字列の構文ハイライト表示
	 * @param string $src 対象の文字列
	 * @return string
	 */
	public function highlight($src){
		return highlight_string($src,true);
		/***
			$return = text('
						<code><span style="color: #000000">
						<span style="color: #0000BB">&lt;?php&nbsp;phpinfo</span><span style="color: #007700">();&nbsp;</span><span style="color: #0000BB">?&gt;</span>
						</span>
						</code>
						');
			$t = new self();
			eq($return,$t->highlight('<?php phpinfo(); ?>'));
		 */
	}
	/**
	 * 国際化文字列を返す
	 * @param string $message
	 * @return string
	 */
	public function trans($message){
		$args = func_get_args();
		return call_user_func_array(array('Gettext','trans'),$args);
		/***
			$t = new self();
			eq("hoge",$t->trans("hoge"));
		 */
	}
	/**
	 * aがbより小さい
	 * @param integer $a
	 * @param integer $b
	 * @return boolean
	 */
	public function lt($a,$b){
		return ($a < $b);
	}
	/**
	 * aがbより小さいか等しい
	 * @param integer $a
	 * @param integer $b
	 * @return boolean
	 */
	public function lte($a,$b){
		return ($a <= $b);
	}
	/**
	 * aがbより大きい
	 * @param integer $a
	 * @param integer $b
	 * @return boolean
	 */
	public function gt($a,$b){
		return ($a > $b);
	}
	/**
	 * aがbより大きいか等しい
	 * @param integer $a
	 * @param integer $b
	 * @return boolean
	 */
	public function gte($a,$b){
		return ($a >= $b);
	}
}