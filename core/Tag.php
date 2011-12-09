<?php
/**
 * Tagを処理する
 *
 * @author tokushima
 * @var string{} $attr アトリビュート
 * @var string{} $param パラメータ
 * @var string $plain 実際の文字列 @{"set":false}
 * @var number $pos 見つかった位置 @{"set":false}
 * @var boolean $close_empty 内容が無い場合に<tag />とするか
 * @var boolean $cdata_value 内容をCDATAとして表現するか
 * @var string $name タグ名
 * @var string $value 内容
 */
class Tag extends Object{
	protected $param;
	protected $attr;
	protected $name;
	protected $value;
	protected $plain;
	protected $pos;
	protected $close_empty = true;
	protected $cdata_value = false;

	final protected function __str__(){
		return $this->get();
	}
	final protected function __new__($name=null,$value=null){
		if($value === null && ($name instanceof Object)){
			$this->name(get_class($name));
			$this->extract($name);
		}else{
			$this->name(trim($name));
			$this->value($value);
		}
		/***
			$self = new self("hoge",array("abc"=>"ABC","def"=>"DEF"));
			eq("<hoge><abc>ABC</abc><def>DEF</def></hoge>",$self->get());
		 */
	}
	final protected function __set_value__($value){
		if(is_array($value) || (is_object($value) && !($value instanceof self))){
			$this->extract($value);
		}else{
			if(is_bool($value)) $value = ($value) ? 'true' : 'false';
			$this->value = ($value === '' || $value === null) ? null : (($this->cdata_value) ? self::xmltext($value) : $value);
		}
	}
	final public function add($arg){
		$args = func_get_args();
		if(!empty($args)){
			if(sizeof($args) == 2){
				$this->param($args[0],$args[1]);
			}else if($args[0] instanceof self){
				$this->value = $this->value().$args[0]->get();
			}else if($args[0] instanceof Object){
				$this->value($this->value().$args[0]->str());
			}else{
				$this->value($this->value().Text::str($args[0]));
			}
		}
		return $this;
		/***
			$tag = new self("hoge","aaa");
			eq("aaa",$tag->value());
			$tag->add("bbb");
			eq("aaabbb",$tag->value());
			$tag->add("ccc");
			eq("aaabbbccc",$tag->value());
		*/
	}
	final protected function __hash__(){
		$list = array();
		$src = $this->value();
		foreach($this->ar_param() as $name => $param) $list[$name] = $param[1];

		while(self::setof($ctag,$src)){
			$result = $ctag->hash();

			if(isset($list[$ctag->name()])){
				if(!is_array($list[$ctag->name()]) || !array_key_exists(0,$list[$ctag->name()])) $list[$ctag->name()] = array($list[$ctag->name()]);
				$list[$ctag->name()][] = $result;
			}else{
				$list[$ctag->name()] = $result;
			}
			$src = substr($src,strpos($src,$ctag->plain()) + strlen($ctag->plain()));
		}
		return (!empty($list)) ? $list : $src;
		/***
			$html = text('
				<div>aaaa</div>
				<div>bbbb</div>
				<div>cccc</div>
			');
			$tag = self::anyhow($html);
			eq(array("div"=>array("aaaa","bbbb","cccc")),$tag->hash());

			$tag = new self("hoge","aaa");
			eq("aaa",$tag->hash());

		 	$src = text('
						<tag aaa="bbb" selected>
							<abc>
								<def var="123">
									<ghi selected>hoge</ghi>
									<ghi>
										<jkl>rails</jkl>
										<mno>ruby</mno>
									</ghi>
									<ghi ab="true">django</ghi>
								</def>
							</abc>
						</tag>
					');
			self::setof($tag,$src,"tag");
			eq(array(
					"aaa"=>"bbb",
					"abc"=>array(
						"def"=>array(
							"var"=>"123",
							"ghi"=>array(
								"hoge",
								array(
									"jkl"=>"rails",
									"mno"=>"ruby"
								),
								array(
									"ab"=>"true",
								),
							)
						)
				)
			),$tag->hash());
		*/
	}
	final protected function __set_param__($name,$value){
		$this->param[strtolower($name)] = array($name,(is_bool($value) ? (($value) ? 'true' : 'false') : (($this->cdata_value) ? Text::htmlencode($value) : $value)));
	}
	/**
	 * パラメータを取得
	 *
	 * @param string $name
	 * @param mixed $default
	 * @return string
	 */
	final protected function __in_param__($name,$default=null){
		$name = strtolower($name);
		$result = (isset($this->param[$name])) ? $this->param[$name] : null;
		$result = ($result === null) ? $default : $result[1];
		return ($this->cdata_value) ? Text::htmldecode($result) : $result;
		/***
			$tag = new self("hoge","abc");
			$tag->add("aaa","123")->add("bbb","456");
			eq("123",$tag->in_param("aaa","hoge"));
			eq("123",$tag->in_param("aAa","hoge"));
			eq("hoge",$tag->in_param("ccc","hoge"));
		 */
	}
	/**
	 * 開始タグを取得
	 * @return string
	 */
	public function start(){
		$param = $attr = '';
		foreach($this->ar_param() as $p) $param .= ' '.$p[0].'="'.$p[1].'"';
		foreach($this->ar_attr() as $value) $attr .= (($value[0] == '<') ? '' : ' ').$value;
		return '<'.$this->name().$param.$attr.(($this->is_close_empty() && !$this->is_value()) ? ' /' : '').'>';
		/***
			$tag = new self("hoge","abc");
			$tag->add("aaa","123")->add("bbb","456");
			eq('<hoge aaa="123" bbb="456">',$tag->start());
			$tag = new self("hoge");
			$tag->add("aaa","123")->add("bbb","456");
			eq('<hoge aaa="123" bbb="456" />',$tag->start());
		 */
	}
	/**
	 * 終了タグを取得
	 * @return string
	 */
	public function end(){
		return (!$this->is_close_empty() || $this->is_value()) ? sprintf("</%s>",$this->name()) : '';
		/***
			$tag = new self("hoge","abc");
			eq('</hoge>',$tag->end());
			$tag = new self("hoge");
			eq('',$tag->end());
		 */
	}
	/**
	 * xmlとして取得
	 * @param string $encoding エンコード名
	 * @return string
	 */
	public function get($encoding=null){
		if(!$this->is_name()) throw new LogicException("undef name");
		return ((empty($encoding)) ? '' : '<?xml version="1.0" encoding="'.$encoding.'" ?>'."\n").$this->start().$this->value().$this->end();
		/***
			$tag = new self("hoge","abc");
			$tag->add("aaa","123")->add("bbb","456");
			eq('<hoge aaa="123" bbb="456">abc</hoge>',$tag->get());
			$result = text('
							<?xml version="1.0" encoding="utf-8" ?>
							<hoge aaa="123" bbb="456">abc</hoge>
						');
			eq($result,$tag->get("utf-8"));
		 */
		/***
			$tag = new self("textarea");
			eq("<textarea />",$tag->get());

			$tag = new self("textarea");
			$tag->close_empty(false);
			eq("<textarea></textarea>",$tag->get());
		 */
		/***
			$tag = new self("textarea");
			eq("<textarea />",$tag->get());

			$tag = new self("textarea","\n\n\nhoge\n\n");
			eq("<textarea>\n\n\nhoge\n\n</textarea>",$tag->get());
		 */
	}
	/**
	 * xmlとし出力する
	 * @param string $encoding エンコード名
	 * @param string $name ファイル名
	 */
	public function output($encoding=null,$name=null){
		Log::disable_display();
		Http::send_header(sprintf('Content-Type: application/xml%s',(empty($name) ? '' : sprintf('; name=%s',$name))));
		print($this->get($encoding));
		exit;
	}
	/**
	 * attachmentとして出力する
	 * @param string $encoding エンコード名
	 * @param string $name ファイル名
	 */
	public function attach($encoding=null,$name=null){
		Http::send_header(sprintf('Content-Disposition: attachment%s',(empty($name) ? '' : sprintf('; filename=%s',$name))));
		$this->output($encoding,$name);
	}
	
	/**
	 * 指定のタグを探索する
	 * @param string $tag_name タグ名
	 * @param integer $offset 開始位置
	 * @param integer $length 取得する最大数
	 * @return TagIterator
	 */
	public function in($tag_name,$offset=0,$length=0){
		return new TagIterator($tag_name,$this->value(),$offset,$length);
		/***
			$src = "<tag><a b='1' /><a>abc</a><b>0</b><a b='1' /><a /></tag>";
			if(self::setof($tag,$src,"tag")){
				$list = array();
				foreach($tag->in("a") as $a){
					$list[] = $a;
					eq("a",$a->name());
				}
				eq(4,sizeof($list));
			}
			$html = text('
				<div>aaaa</div>
				<div style="background: url(http://example.jp/example.png);">bbbb</div>
				<div>cccc</div>
			');
			self::setof($tag, '<div>'.$html.'</div>', 'div');
			$divs = array();
			foreach($tag->in("div") as $d){
				$divs[] = $d;
			}
			eq(3, count($divs));

			$html = text('
				<div>aaaa</div>
				<div>bbbb</div>
				<div>cccc</div>
			');
			self::setof($tag, '<div>'.$html.'</div>', 'div');
			$divs = array();
			foreach($tag->in("div") as $d){
				$divs[] = $d;
			}
			eq(3, count($divs));

			self::setof($tag,"<tag><data1 /><data2 /><data1 /><data3 /><data3 /><data2 /><data4 /></tag>","tag");
			$result = array();
			foreach($tag->in(array("data2","data3")) as $d){
				$result[] = $d;
			}
			eq("data2",$result[0]->name());
			eq("data3",$result[1]->name());
			eq("data3",$result[2]->name());
			eq("data2",$result[3]->name());
		*/
		/***
			# length_test
			self::setof($tag,"<tag><data1 p='1' /><data2 /><data1 p='2' /><data3 /><data3 /><data1 p='3' /><data1 p='4' /><data2 /><data4 /><data1 p='5' /></tag>","tag");
			$result = array();
			foreach($tag->in("data1",1,2) as $d){
				$result[] = $d;
			}
			eq(2,sizeof($result));
		*/
		/***
			# noend
			$tag = self::anyhow("hoge<br />hoge<br>hoge<br /><br />hoge");
			$count = 0;
			foreach($tag->in("br") as $br){
				eq("br",$br->name());
				$count++;
			}
			eq(4,$count);
		 */
	}
	/**
	 * 指定のタグをすべて返す
	 * @param string $tag_name タグ名
	 * @param integer $offset 開始位置
	 * @param integer $length 取得する最大数
	 * @return self[]
	 */
	public function in_all($tag_name,$offset=0,$length=0){
		$result = array();
		foreach($this->in($tag_name,$offset,$length) as $tag) $result[] = $tag;
		return $result;
		/***
			# length_test
			self::setof($tag,"<tag><data1 p='1' /><data2 /><data1 p='2' /><data3 /><data3 /><data1 p='3' /><data1 p='4' /><data2 /><data4 /><data1 p='5' /></tag>","tag");
			$result = $tag->in_all("data1",1,2);
			eq(2,sizeof($result));
		*/
	}
	/**
	 * パスで検索する
	 * @param string $path 検索文字列
	 * @return mixed
	 */
	public function f($path){
		$arg = (func_num_args() == 2) ? func_get_arg(1) : null;
		$paths = explode('.',$path);
		$last = (strpos($path,'(') === false) ? null : array_pop($paths);
		$tag = clone($this);
		$route = array();
		if($arg !== null) $arg = (is_bool($arg)) ? (($arg) ? 'true' : 'false') : strval($arg);

		foreach($paths as $p){
			$pos = 0;
			if(preg_match("/^(.+)\[([\d]+?)\]$/",$p,$matchs)) list($tmp,$p,$pos) = $matchs;
			$tags = $tag->in_all($p,$pos,1);
			if(!isset($tags[0]) || !($tags[0] instanceof self)){
				$tag = null;
				break;
			}
			$route[] = $tag = $tags[0];
		}
		if($tag instanceof self){
			if($arg === null){
				switch($last){
					case '': return $tag;
					case 'plain()': return $tag->plain();
					case 'value()': return $tag->value();
					default:
						if(preg_match("/^(param|attr|in_all|in)\((.+?)\)$/",$last,$matchs)){
							list($null,$type,$name) = $matchs;
							switch($type){
								case 'in_all': return $tag->in_all(trim($name));
								case 'in': return $tag->in(trim($name));
								case 'param': return $tag->in_param($name);
								case 'attr': return $tag->is_attr($name);
							}
						}
						return null;
				}
			}
			if($arg instanceof self) $arg = $arg->get();
			if(is_bool($arg)) $arg = ($arg) ? 'true' : 'false';
			krsort($route,SORT_NUMERIC);
			$ltag = $rtag = $replace = null;
			$f = true;

			foreach($route as $r){
				$ltag = clone($r);
				if($f){
					switch($last){
						case 'value()':
							$replace = $arg;
							break;
						default:
							if(preg_match("/^(param|attr)\((.+?)\)$/",$last,$matchs)){
								list($null,$type,$name) = $matchs;
								switch($type){
									case 'param':
										$r->param($name,$arg);
										$replace = $r->get();
										break;
									case 'attr':
										($arg === 'true') ? $r->attr($name) :$r->rm_attr($name);
										$replace = $r->get();
										break;
									default:
										return null;
								}
							}
					}
					$f = false;
				}
				$r->value(empty($rtag) ? $replace : str_replace($rtag->plain(),$replace,$r->value()));
				$replace = $r->get();
				$rtag = clone($ltag);
			}
			$this->value(str_replace($ltag->plain(),$replace,$this->value()));
			return null;
		}
		return (!empty($last) && substr($last,0,2) == 'in') ? array() : null;
		/***
			$src = "<tag><abc><def var='123'><ghi selected>hoge</ghi></def></abc></tag>";
			if(self::setof($tag,$src,"tag")){
				eq("hoge",$tag->f("abc.def.ghi.value()"));
				eq("123",$tag->f("abc.def.param(var)"));
				eq(true,$tag->f("abc.def.ghi.attr(selected)"));
				eq("<def var='123'><ghi selected>hoge</ghi></def>",$tag->f("abc.def.plain()"));
				eq(null,$tag->f("abc.def.xyz"));
			}
		 	$src = text('
						<tag>
							<abc>
								<def var="123">
									<ghi selected>hoge</ghi>
									<ghi>
										<jkl>rails</jkl>
									</ghi>
									<ghi ab="true">django</ghi>
								</def>
							</abc>
						</tag>
					');
			self::setof($tag,$src,"tag");
			eq("django",$tag->f("abc.def.ghi[2].value()"));
			eq("rails",$tag->f("abc.def.ghi[1].jkl.value()"));
			$tag->f("abc.def.ghi[2].value()","python");
			eq("python",$tag->f("abc.def.ghi[2].value()"));

			eq("123",$tag->f("abc.def.param(var)"));
			eq("true",$tag->f("abc.def.ghi[2].param(ab)"));
			$tag->f("abc.def.ghi[2].param(cd)",456);
			eq("456",$tag->f("abc.def.ghi[2].param(cd)"));

			eq(true,$tag->f("abc.def.ghi[0].attr(selected)"));
			eq(false,$tag->f("abc.def.ghi[1].attr(selected)"));
			$tag->f("abc.def.ghi[1].attr(selected)",true);
			eq(true,$tag->f("abc.def.ghi[1].attr(selected)"));
			eq(array(),$tag->f("abc.def.in(xyz)"));
			eq(array(),$tag->f("abc.opq.in(xyz)"));
		*/
	}
	/**
	 * idで検索する
	 *
	 * @param string $name 指定のID
	 * @return self
	 */
	public function id($name){
		if(preg_match("/<.+[\s]*id[\s]*=[\s]*([\"\'])".preg_quote($name)."\\1/",$this->value(),$match,PREG_OFFSET_CAPTURE)){
			if(self::setof($tag,substr($this->value(),$match[0][1]))) return $tag;
		}
		return null;
		/***
			$src = text('
						<aaa>
							<bbb id="DEF"></bbb>
							<ccc id="ABC">
								<ddd id="XYZ">hoge</ddd>
							</ccc>
						</aaa>
					');
			$tag = self::anyhow($src);
			eq("ddd",$tag->id("XYZ")->name());
			eq(null,$tag->id("xyz"));
		 */
	}

	/**
	 * value値がcdataとなるTagを返す
	 * @param $name タグ名
	 * @param $value 内容
	 * @return self
	 */
	final static public function xml($name,$value=null){
		$self = new self($name);
		$self->cdata_value(true);
		$self->value($value);
		return $self;
	}
	/**
	 * ユニークな名前でTagとして作成する
	 * @param string $plain 内容
	 * @return self
	 */
	final static public function anyhow($plain){
		$uniq = uniqid('Anyhow_');
		if(self::setof($tag,'<'.$uniq.'>'.$plain.'</'.$uniq.'>',$uniq)) return $tag;
		/***
		 	$src = "hoge";
			$tag = self::anyhow($src);
			eq("hoge",$tag->value());
		 */
	}
	/**
	 * Tagとして正しければTagインスタンスを作成する
	 * @param mixed $var
	 * @param string $plain
	 * @param string $name
	 * @return boolean
	 */
	final static public function setof(&$var,$plain,$name=null){
		return self::parse_tag($var,$plain,$name);
		/***
			$src = 'AAA<hoge aaa="123" BbB="456" selected><EE>ee</EE></hoge>ZZZZ';
			if(eq(true,self::setof($tag,$src))){
				eq('<EE>ee</EE>',$tag->value());
				eq(array("aaa"=>array("aaa","123"),"bbb"=>array("BbB","456")),$tag->ar_param());
			}
			eq(false,self::setof($src,"abc"));
		*/
		/***
			# noend
			$src = '<ae>';
			$count = 0;
			if(eq(true,self::setof($tag,$src,"ae"))){
				eq("<ae>",$tag->plain());
				$count++;
			}
			eq(1,$count);
			
			$src = '<aa><bb><ae abc="123"></bb></aa><ae>456</qe>';
			if(eq(true,self::setof($tag,$src,"ae"))){
				eq("ae",$tag->name());
				eq('<ae abc="123">',$tag->plain());
				eq("123",$tag->in_param("abc"));
			}
		 */
		/***
			$src = '<abc>0</abc>';
			if(eq(true,self::setof($tag,$src))){
				eq('abc',$tag->name());
				eq('0',$tag->value());
			}
		*/
	}
	static private function parse_tag(&$var,$plain,$name=null,$vtag=null){
		$plain = Text::str($plain);
		$name = Text::str($name);
		if(empty($name) && preg_match("/<([\w\:\-]+)[\s][^>]*?>|<([\w\:\-]+)>/is",$plain,$parse)){
			$name = str_replace(array("\r\n","\r","\n"),"",(empty($parse[1]) ? $parse[2] : $parse[1]));
		}
		$qname = preg_quote($name,'/');
		if(!preg_match("/<(".$qname.")([\s][^>]*?)>|<(".$qname.")>/is",$plain,$parse,PREG_OFFSET_CAPTURE)) return false;
		$var = new self();
		$var->pos = $parse[0][1];
		$balance = 0;
		$params = '';

		if(substr($parse[0][0],-2) == '/>'){
			$var->name = $parse[1][0];
			$var->plain = empty($vtag) ? $parse[0][0] : preg_replace('/'.preg_quote(substr($vtag,0,-1).' />','/').'/',$vtag,$parse[0][0],1);
			$params = $parse[2][0];
		}else if(preg_match_all("/<[\/]{0,1}".$qname."[\s][^>]*[^\/]>|<[\/]{0,1}".$qname."[\s]*>/is",$plain,$list,PREG_OFFSET_CAPTURE,$var->pos)){
			foreach($list[0] as $arg){
				if(($balance += (($arg[0][1] == '/') ? -1 : 1)) <= 0 &&
						preg_match("/^(<(".$qname.")([\s]*[^>]*)>)(.*)(<\/\\2[\s]*>)$/is",
							substr($plain,$var->pos,($arg[1] + strlen($arg[0]) - $var->pos)),
							$match
						)
				){
					$var->plain = $match[0];
					$var->name = $match[2];
					$var->value = ($match[4] === '' || $match[4] === null) ? null : $match[4];
					$params = $match[3];
					break;
				}
			}
			if(!isset($var->plain)){
				return self::parse_tag($var,preg_replace('/'.preg_quote($list[0][0][0],'/').'/',substr($list[0][0][0],0,-1).' />',$plain,1),$name,$list[0][0][0]);
			}
		}
		if(!isset($var->plain)) return false;
		if(!empty($params)){
			if(preg_match_all("/[\s]+([\w\-\:]+)[\s]*=[\s]*([\"\'])([^\\2]*?)\\2/ms",$params,$param)){
				foreach($param[0] as $id => $value){
					$var->param($param[1][$id],$param[3][$id]);
					$params = str_replace($value,"",$params);
				}
			}
			if(preg_match_all("/([\w\-]+)/",$params,$attr)){
				foreach($attr[1] as $value) $var->attr($value);
			}
		}
		return true;
	}
	/**
	 * 指定のタグで閉じていないものを閉じる
	 * @param string $src XML文字列
	 * @param string $name 閉じたいタグ名
	 * @return string
	 */
	static public function xhtmlnize($src,$name){
		/***
			eq("<img src='hoge' />",self::xhtmlnize("<img src='hoge'>","img"));
			eq("<img src='hoge' />",self::xhtmlnize("<img src='hoge' />","img"));
			eq("<a><br /></a>",self::xhtmlnize("<a><br></a>","br"));
			eq("<br /><img src='hoge' /><br />",self::xhtmlnize("<br><img src='hoge'><br>","img","br"));
			eq("<br /><brc><br />",self::xhtmlnize("<br><brc><br>","br"));
			eq("<meta name='description' />\n<title>a</title>",self::xhtmlnize("<meta name='description' />\n<title>a</title>","meta"));
		 */
		$args = func_get_args();
		array_shift($args);
		foreach($args as $name){
			if(preg_match_all(sprintf("/(<%s>)|(<%s[\s][^>]*[^\/]>)/is",$name,$name),$src,$link)){
				foreach($link[0] as $value) $src = str_replace($value,substr($value,0,-1).' />',$src);
			}
		}
		return $src;
	}
	/**
	 * CDATA形式にして返す
	 * @param string $value CDATA形式にしたい文字列
	 * @return string
	 */
	static public function xmltext($value){
		if(is_string($value) && strpos($value,'<![CDATA[') === false && preg_match("/&|<|>|\&[^#\da-zA-Z]/",$value)) return '<![CDATA['.$value.']]>';
		return $value;
		/***
			eq("abc",self::xmltext("abc"));
			eq("<![CDATA[<abc />]]>",self::xmltext("<abc />"));
			eq("<![CDATA[htt://hoge?a=1&b=1]]>",self::xmltext("htt://hoge?a=1&b=1"));
		 */
	}
	/**
	 * CDATA形式から値を取り出す
	 * @param string $value 内容
	 * @return string
	 */
	static public function cdata($value){
		if(preg_match_all("/<\!\[CDATA\[(.+?)\]\]>/ims",$value,$match)){
			foreach($match[1] as $key => $v) $value = str_replace($match[0][$key],$v,$value);
		}
		return $value;
		/***
			eq("<abc />",self::cdata("<![CDATA[<abc />]]>"));
		 */
	}
	/**
	 * XMLコメントを削除する
	 * @param string $src コメントを含む文字列
	 * @return string
	 */
	static public function uncomment($src){
		return preg_replace('/<!--.+?-->/s','',$src);
		/***
			$text = text('
							abc
							<!--
								comment
							-->
							def
						');
			eq("abc\n\ndef",self::uncomment($text));
		*/
	}
	/**
	 * ファイルから読み込みTagとして正しければTagインスタンスを作成する
	 * @param mixied $var Tagを格納する変数
	 * @param string $xml_file ファイルパス
	 * @param string $name ルートの要素名
	 * @return boolean
	 */
	static public function load(&$var,$xml_file,$name=null){
		return self::setof($var,file_get_contents($xml_file),$name);
	}
	/**
	 * 配列またはObjectから抽出して追加する
	 * @param mixed $var 展開する内容
	 * @return $this
	 */
	public function extract($var){
		if($var instanceof self) $var = $var->value();
		return $this->extract_get($var);
		/***
			$name = create_class('
				public $id = 0;
				public $value = "123";
			');
			$list = array("a"=>"A","b"=>"B","c"=>array("c1"=>true,"c2"=>false),"d"=>new $name(),"e"=>new self("hh","oioi"));
			$tag = new self("hoge");
			eq('<hoge><a>A</a><b>B</b><c><c1>true</c1><c2>false</c2></c><d><id>0</id><value>123</value></d><e><hh>oioi</hh></e></hoge>',$tag->extract($list)->get());

			$tag = new self("hoge");
			eq('<hoge>aaa</hoge>',$tag->extract("aaa")->get());
		*/
		/***
			# object
			$name1 = create_class('
				public $id = 0;
				public $value = "123";
				public $abc;
				public $def;
			');
			$name2 = create_class('
				public $aa = "A";
				public $bb = "B";
			');

			$obj = new $name1();
			$obj->abc = new $name2();
			$obj->abc->aa("><");
			$obj->def[] = new $name2();
			$obj->def[] = new $name2();

			$tag = self::xml("hoge");
			eq('<hoge><id>0</id><value>123</value><abc><aa><![CDATA[><]]></aa><bb>B</bb></abc><def><'.$name2.'><aa>A</aa><bb>B</bb></'.$name2.'><'.$name2.'><aa>A</aa><bb>B</bb></'.$name2.'></def></hoge>',$tag->extract($obj)->get());
		 */
		/***
			# tag
			$tag = new self("abc");
			$tag->add(new self("def","xxx"));
			
			$new_tag = new self("xyz");
			$new_tag->extract($tag);
			eq('<xyz><def>xxx</def></xyz>',$new_tag->get());
		 */
		/***
			# boolean
			$tag = new self("abc");
			$tag->extract(array("hoge"=>true));
			eq('<abc><hoge>true</hoge></abc>',$tag->get());
			
			$tag = new self("abc");
			$tag->extract(array("hoge"=>false));
			eq('<abc><hoge>false</hoge></abc>',$tag->get());			
		 */
	}
	private function extract_get($var){
		if(is_object($var)){
			if($var instanceof Object){
				$var = ($var instanceof self) ? $var->get() : $var->hash();
			}else{
				$var = get_object_vars($var);				
			}
		}
		if(is_array($var)){
			foreach($var as $key => $value){
				if(is_bool($value)) $value = ($value === true) ? 'true' : 'false';
				if(is_numeric($key) && is_object($value)) $key = get_class($value);
				if(is_numeric($key)) $key = 'data';
				$tag = new self($key);
				$tag->cdata_value($this->cdata_value());
				$this->add($tag->extract_get($value));
			}
		}else{
			$this->add($var);
		}
		return $this;
	}
}