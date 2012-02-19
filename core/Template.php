<?php
/**
 * テンプレートを処理する
 * @author tokushima
 * @var mixed{} $vars コンテキストとなる値
 * @var string{} $statics スタティックでアクセスするコンテキストとなる値
 * @var string $base_path テンプレートファイルのベースパス
 * @var string $media_url メディアファイルのベースパス
 * @var string $filename テンプレートファイル名
 * @var string $put_block 強制ブロック
 * @var string $template_super 継承元をすげかえするテンプレートファイル名
 * @var boolean $secure セキュアURLを使用するか
 */
class Template extends Object{
	static private $base_media_url;
	static private $base_template_path;
	static private $exception_str;
	static private $is_cache = false;

	protected $base_path;
	protected $media_url;

	protected $statics = array();
	protected $vars = array();
	protected $filename;
	protected $put_block;
	protected $template_super;	
	protected $secure = false;
	private $selected_template;

	/**
	 * ベースパスの定義
	 * @param string $template_path テンプレートファイルのベースパス
	 * @param string $media_url メディアURLのベースパス
	 */
	static public function config_path($template_path,$media_url=null){
		if(!empty($template_path)) self::$base_template_path = preg_replace("/^(.+)\/$/","\\1",str_replace("\\","/",$template_path))."/";
		if(!empty($media_url)) self::$base_media_url = preg_replace("/^(.+)\/$/","\\1",$media_url)."/";
	}
	/**
	 * 例外時に表示する文字列の定義
	 * @param string $str 例外時に表示する文字列
	 */
	static public function config_exception($str){
		self::$exception_str = $str;
	}
	/**
	 * キャッシュするかの定義
	 * @param boolean $bool キャッシュするか
	 */
	static public function config_cache($bool){
		self::$is_cache = (boolean)$bool;
	}
	/**
	 * キャッシュが有効か
	 * @return boolean
	 */
	static public function is_cache(){
		return self::$is_cache;
	}
	/**
	 * テンプレートのパス
	 * @return string
	 */
	static public function base_template_path(){
		return isset(self::$base_template_path) ? self::$base_template_path : App::path('resources/templates').'/';
	}
	/**
	 * メディアURL
	 * @return string
	 */
	static public function base_media_url(){
		return isset(self::$base_media_url) ? self::$base_media_url : App::url('resources/media',false).'/';
	}
	protected function __set_statics__($name,$class){
		$this->statics['$'.$var.'->'] = Lib::import($class).'::';
	}
	protected function __new__($media_url=null,$base_path=null){
		$this->media_url($media_url);
		$this->base_path($base_path);
	}
	protected function __cp__($obj){
		if(!empty($obj)){
			if($obj instanceof Object){
				foreach($obj->prop_values() as $name => $value) $this->vars[$name] = $obj->{'fm_'.$name}();
			}else if(is_array($obj)){
				foreach($obj as $name => $value) $this->vars[$name] = $value;
			}else{
				throw new InvalidArgumentException('cp');
			}
		}
	}
	protected function __set_base_path__($path){
		$this->base_path = File::absolute(self::base_template_path(),File::path_slash($path,null,true));
	}
	protected function __set_media_url__($url){
		$this->media_url = File::absolute(self::base_media_url(),File::path_slash($url,null,true));
	}
	protected function __get_filename__(){
		return empty($this->filename) ? null : File::absolute($this->base_path,$this->filename);
	}
	protected function __fm_filename__($path=null){
		return ($path === null) ? $this->filename() : File::absolute($this->base_path,$path);
	}
	protected function __is_filename__($path=null){
		$path = ($path === null) ? $this->filename() : File::absolute($this->base_path,$path);
		return (!empty($path) && (is_file($path) || strpos($path,'://') !== false));
	}
	/**
	 * 指定したテンプレートが存在するか
	 * @return boolean
	 */
	public function has(){
		if(empty($this->put_block)) return is_file($this->filename($filename));
		return is_file(File::absolute($this->base_path,$this->put_block));
	}
	/**
	 * ファイルから生成する
	 * @param string $filename テンプレートファイルパス
	 * @param string $template_name 対象となるテンプレート名
	 * @return string
	 */
	public function read($filename=null,$template_name=null){
		if(!empty($filename)) $this->filename($filename);
		$this->selected_template = $template_name;
		$cfilename = $this->template_super.$this->put_block.$this->filename().$this->selected_template;

		if(!self::$is_cache || !Store::has($cfilename,true)){
			$filename = $this->filename();
			if(!empty($this->put_block)){
				$src = $this->read_src(File::absolute($this->base_path,$this->put_block));
				if(strpos($src,'rt:extends') !== false){
					$tag = Tag::anyhow($src);
					foreach($tag->in('rt:extends') as $ext) $src = str_replace($ext->plain(),'',$src);
				}
				$src = sprintf('<rt:extends href="%s" />\n',$filename).$src;
			}else{
				$src = $this->read_src($filename);
			}
			$src = $this->parse($src);
			if(self::$is_cache) Store::set($cfilename,$src);
		}else{
			$src = Store::get($cfilename);
		}
		$src = $this->html_reform($this->exec($src));
		return $this->replace_ptag($src);
	}
	private function read_src($filename){
		$src = file_get_contents($filename);
		return (strpos($filename,'://') !== false) ? $this->parse_url($src,dirname($filename)) : $src;
	}
	/**
	 * 出力して終了する
	 * @param string $filename テンプレートファイルパス
	 */
	public function output($filename=null){
		print($this->read($filename));
		exit;
	}
	/**
	 * 文字列から生成する
	 * @param string $src テンプレート文字列
	 * @param string $template_name 対象となるテンプレート名
	 * @return string
	 */
	public function execute($src,$template_name=null){
		$this->selected_template = $template_name;
		$src = $this->replace_ptag($this->html_reform($this->exec($this->parse($src))));
		return $src;
		/***
			$src = text('
				<body>
					{$abc}{$def}
						{$ghi}	{$hhh["a"]}
					<a href="./hoge.html">{$abc}</a>
					<img src="../img/abc.png"> {$ooo.yyy}
					<form action="{$ooo.xxx}">
					</form>
				</body>
			');
			$result = text('
				<body>
					AAA
						B	1
					<a href="http://rhaco.org/tmp/hoge.html">AAA</a>
					<img src="http://rhaco.org/img/abc.png"> fuga
					<form action="index.php">
					</form>
				</body>
			');
			$obj = new stdClass();
			$obj->xxx = "index.php";
			$obj->yyy = "fuga";

			$template = new Template("http://rhaco.org/tmp");
			$template->vars("abc","AAA");
			$template->vars("def",null);
			$template->vars("ghi","B");
			$template->vars("ooo",$obj);
			$template->vars("hhh",array("a"=>1,"b"=>2));
			eq($result,$template->execute($src));
		*/
		/***
			#exception
			self::config_exception("EXCEPTION");
			$src = text('<html><body>{$hoge}</body></html>');
			$template = new Template();
			$result = '<html><body>EXCEPTION</body></html>';
			eq($result,$template->execute($src));
			self::config_exception("");
		 */
	}
	private function replace_ptag($src){
		return str_replace(array('__PHP_TAG_ESCAPE_START__','__PHP_TAG_ESCAPE_END__'),array('<?','?>'),$src);
	}
	private function replace_xtag($src){
		if(preg_match_all("/<\?(?!php[\s\n])[\w]+ .*?\?>/s",$src,$null)){
			foreach($null[0] as $value) $src = str_replace($value,'__PHP_TAG_ESCAPE_START__'.substr($value,2,-2).'__PHP_TAG_ESCAPE_END__',$src);
		}
		return $src;
	}
	/**
	 * rt:**タグをパースする
	 * @param string $src
	 * @return string
	 */
	public function parse_tags($src){
		return $this->rtif($this->rtloop($this->rtunit($this->rtpager($this->rtinvalid($this->html_form($this->html_list($src)))))));		
	}
	/**
	 * {$xxx}変数をパースする
	 * @param string $src
	 * @return string
	 */
	public function parse_vars($src){
		return str_replace(array_keys($this->statics),array_values($this->statics),$this->parse_print_variable($src));
	}
	private function parse($src){
		$src = preg_replace("/([\w])\->/","\\1__PHP_ARROW__",$src);
		$src = str_replace(array("\\\\","\\\"","\\'"),array('__ESC_DESC__','__ESC_DQ__','__ESC_SQ__'),$src);
		$src = $this->replace_xtag($src);
		/**
		 * 初期処理
		 * @param string $src
		 * @param self $this
		 */
		$this->call_module('init_template',$src,$this);
		$src = $this->rtcomment($this->rtblock($this->rttemplate($src),$this->filename()));
		/**
		 * 前処理
		 * @param string $src
		 * @param self $this
		 */
		$this->call_module('before_template',$src,$this);
		$src = $this->parse_tags($src);
		/**
		 * 後処理
		 * @param string $src
		 * @param self $this
		 */
		$this->call_module('after_template',$src,$this);
		$src = str_replace('__PHP_ARROW__','->',$src);
		$src = $this->parse_vars($src);
		$php = array(' ?>','<?php ','->');
		$str = array('PHP_TAG_END','PHP_TAG_START','PHP_ARROW');
		$src = str_replace($str,$php,$this->parse_url(str_replace($php,$str,$src),$this->media_url));
		$src = str_replace(array('__ESC_DQ__','__ESC_SQ__','__ESC_DESC__'),array("\\\"","\\'","\\\\"),$src);
		return $src;		
		/***
			$filter = create_class('
				public function init_template($src){
					$src = "====\n".$src."\n====";
				}
				public function before_template($src){
					$src = "____\n".$src."\n____";
				}
				public function after_template($src){
					$src = "####\n".$src."\n####";
				}
			');
			$src = text('
					hogehoge
				');
			$result = text('
					####
					____
					====
					hogehoge
					====
					____
					####
				');
			$template = new Template();
			$template->add_module(new $filter());
			eq($result,$template->execute($src));
		 */
	}
	final private function parse_url($src,$base){
		if(substr($base,-1) !== '/') $base = $base.'/';
		$secure_base = ($this->secure) ? str_replace('http://','https://',$base) : null;
		if(preg_match_all("/<([^<\n]+?[\s])(src|href|background)[\s]*=[\s]*([\"\'])([^\\3\n]+?)\\3[^>]*?>/i",$src,$match)){
			foreach($match[2] as $k => $p){
				$t = null;
				if(strtolower($p) === 'href') list($t) = (preg_split("/[\s]/",strtolower($match[1][$k])));
				$src = $this->replace_parse_url($src,(($this->secure && $t !== 'a') ? $secure_base : $base),$match[0][$k],$match[4][$k]);
			}
		}
		if(preg_match_all("/[^:]:[\040]*url\(([^\n]+?)\)/",$src,$match)){
			if($this->secure) $base = $secure_base;
			foreach($match[1] as $key => $param) $src = $this->replace_parse_url($src,$base,$match[0][$key],$match[1][$key]);
		}
		return $src;
	}
	final private function replace_parse_url($src,$base,$dep,$rep){
		if(!preg_match("/(^[\w]+:\/\/)|(^PHP_TAG_START)|(^\{\\$)|(^\w+:)|(^[#\?])/",$rep)){
			$src = str_replace($dep,str_replace($rep,File::absolute($base,$rep),$dep),$src);
		}
		return $src;
	}
	final private function rttemplate($src){
		$values = array();
		$bool = false;
		while(Tag::setof($tag,$src,'rt:template')){
			$src = str_replace($tag->plain(),'',$src);
			$values[$tag->in_param('name')] = $tag->value();
			$src = str_replace($tag->plain(),'',$src);
			$bool = true;
		}
		if(!empty($this->selected_template)){
			if(!array_key_exists($this->selected_template,$values)) throw new LogicException('undef rt:template '.$this->selected_template);
			return $values[$this->selected_template];
		}
		return ($bool) ? implode($values) : $src;
		/***
			$template = new Template();
			$src = text('
				AAAA
				<rt:template name="aa">
					aa
				</rt:template>
				BBBB
				<rt:template name="bb">
					bb
				</rt:template>
				CCCC
				<rt:template name="cc">
					cc
				</rt:template>
			');
			eq("	bb\n",$template->execute($src,"bb"));
		 */
	}	
	final private function rtblock($src,$filename){
		if(strpos($src,'rt:block') !== false || strpos($src,'rt:extends') !== false){
			$base_filename = $filename;
			$blocks = $paths = array();
			while(Tag::setof($xml,$this->rtcomment($src),'rt:extends')){
				$bxml = Tag::anyhow($src);
				foreach($bxml->in('rt:block') as $block){
					if(strtolower($block->name()) == 'rt:block'){
						$name = $block->in_param('name');
						if(!empty($name) && !array_key_exists($name,$blocks)){
							$blocks[$name] = $block->value();
							$paths[$name] = $filename;
						}
					}
				}
				if($xml->is_param('href')){
					$src = $this->read_src($filename = File::absolute(dirname($filename),$xml->in_param('href')));
					$this->filename = $filename;
				}else{
					$src = file_get_contents($this->filename());
				}
				$this->selected_template = $xml->in_param('name');
				$src = $this->rttemplate($this->replace_xtag($src));
			}
			if(!empty($this->template_super)) $src = $this->read_src(File::absolute(dirname($base_filename),$this->template_super));
			/**
			 * ブロック実行前処理
			 * @param string $src
			 * @param self $this
			 */
			$this->call_module('before_block_template',$src,$this);
			if(empty($blocks)){
				$bxml = Tag::anyhow($src);
				foreach($bxml->in('rt:block') as $block) $src = str_replace($block->plain(),$block->value(),$src);
			}else{
				while(Tag::setof($xml,$src,'rt:block')){
					$xml = Tag::anyhow($src);
					foreach($xml->in('rt:block') as $block){
						$name = $block->in_param('name');
						$src = str_replace($block->plain(),(array_key_exists($name,$blocks) ? $blocks[$name] : $block->value()),$src);
					}
				}
			}
		}
		return $src;
		/***
			ftmp("template/base.html",'
					=======================
					<rt:block name="aaa">
					base aaa
					</rt:block>
					<rt:block name="bbb">
					base bbb
					</rt:block>
					<rt:block name="ccc">
					base ccc
					</rt:block>
					<rt:block name="ddd">
					base ddd
					</rt:block>
					=======================
				');
			ftmp("template/extends1.html",'
					<rt:extends href="base.html" />

					<rt:block name="aaa">
					extends1 aaa
					</rt:block>

					<rt:block name="ddd">
					extends1 ddd
					<rt:loop param="abc" var="ab" loop_counter="loop_counter" key="loop_key">
						{$loop_key}:{$loop_counter} {$ab}
					</rt:loop>
					<rt:if param="abc">
					aa
					</rt:if>
					<rt:if param="aa" value="1">
					bb
					</rt:if>
					<rt:if param="aa" value="2">
					bb
					<rt:else />
					cc
					</rt:if>
					<rt:if param="zz">
					zz
					</rt:if>
					<rt:if param="aa">
					aa
					</rt:if>
					<rt:if param="tt">
					true
					</rt:if>
					<rt:if param="ff">
					false
					</rt:if>
					</rt:block>
				');
			ftmp("template/sub/extends2.html",'
					<rt:extends href="../extends1.html" />

					<rt:block name="aaa">
					<a href="hoge/fuga.html">fuga</a>
					<a href="{$newurl}/abc.html">abc</a>
					sub extends2 aaa
					</rt:block>

					<rt:block name="ccc">
					sub extends2 ccc
					</rt:block>
				');

			$template = new self("http://rhaco.org",tmp_path("template"));
			$template->vars("newurl","http://hoge.ho");
			$template->vars("abc",array(1,2,3));
			$template->vars("aa",1);
			$template->vars("zz",null);
			$template->vars("ff",false);
			$template->vars("tt",true);
			$result = $template->read("sub/extends2.html");
			$ex = text('
						=======================

						<a href="http://rhaco.org/hoge/fuga.html">fuga</a>
						<a href="http://hoge.ho/abc.html">abc</a>
						sub extends2 aaa


						base bbb


						sub extends2 ccc


						extends1 ddd
							0:1 1
							1:2 2
							2:3 3
						aa
						bb
						cc
						aa
						true

						=======================
					');
			eq($ex,$result);
		 */
		/***
			# on_block
			ftmp("template/on/base.html",'<body><rt:block name="aaa">AAA</rt:block>BBB<rt:block name="ccc">CCC</rt:block></body>');
			ftmp("template/on/main.html",'<rt:extends href="base.html" /><rt:block name="ccc">444</rt:block>');
			ftmp("template/on_block/block.html",'<rt:block name="ccc">FFF</rt:block>');

			$template = new self("http://rhaco.org",tmp_path("template"));
			eq('<body>AAABBBCCC</body>',$template->read("on/base.html"));

			$template = new self("http://rhaco.org",tmp_path("template"));
			eq('<body>AAABBB444</body>',$template->read("on/main.html"));
			
			$template = new self("http://rhaco.org",tmp_path("template"));
			$template->put_block("on_block/block.html");
			eq('<body>AAABBBFFF</body>',$template->read("on/main.html"));
		 */
		/***
			# on_block_ext
			ftmp("template/on/base.html",'<body><rt:block name="aaa">AAA</rt:block>BBB<rt:block name="ccc">CCC</rt:block></body>');
			ftmp("template/on/main.html",'<rt:extends href="base.html" /><rt:block name="ccc">444</rt:block>');
			ftmp("template/on_block/block_base.html",'===<rt:block name="ccc">CCC</rt:block>===');
			ftmp("template/on_block/block.html",'<rt:extends href="block_base.html" /><rt:block name="ccc">FFF</rt:block>');

			$template = new self("http://rhaco.org",tmp_path("template"));
			eq('<body>AAABBBCCC</body>',$template->read("on/base.html"));

			$template = new self("http://rhaco.org",tmp_path("template"));
			eq('<body>AAABBB444</body>',$template->read("on/main.html"));
			
			$template = new self("http://rhaco.org",tmp_path("template"));
			eq('===CCC===',$template->read("on_block/block_base.html"));
			
			$template = new self("http://rhaco.org",tmp_path("template"));
			eq('===FFF===',$template->read("on_block/block.html"));			

			$template = new self("http://rhaco.org",tmp_path("template"));
			$template->put_block("on_block/block.html");
			eq('<body>AAABBBFFF</body>',$template->read("on/main.html"));
		 */
	}
	final private function rtcomment($src){
		while(Tag::setof($tag,$src,'rt:comment')) $src = str_replace($tag->plain(),'',$src);
		return $src;
	}
	final private function rtunit($src){
		if(strpos($src,'rt:unit') !== false){
			while(Tag::setof($tag,$src,'rt:unit')){
				$uniq = uniqid('');
				$param = $tag->in_param('param');
				$var = '$'.$tag->in_param('var','_var_'.$uniq);
				$offset = $tag->in_param('offset',1);
				$total = $tag->in_param('total','_total_'.$uniq);
				$cols = ($tag->is_param('cols')) ? (ctype_digit($tag->in_param('cols')) ? $tag->in_param('cols') : $this->variable_string($this->parse_plain_variable($tag->in_param('cols')))) : 1;
				$rows = ($tag->is_param('rows')) ? (ctype_digit($tag->in_param('rows')) ? $tag->in_param('rows') : $this->variable_string($this->parse_plain_variable($tag->in_param('rows')))) : 0;
				$value = $tag->value();

				$cols_count = '$_ucount_'.$uniq;
				$cols_total = '$'.$tag->in_param('cols_total','_cols_total_'.$uniq);
				$rows_count = '$'.$tag->in_param('counter','_counter_'.$uniq);
				$rows_total = '$'.$tag->in_param('rows_total','_rows_total_'.$uniq);
				$ucols = '$_ucols_'.$uniq;
				$urows = '$_urows_'.$uniq;
				$ulimit = '$_ulimit_'.$uniq;
				$ufirst = '$_ufirst_'.$uniq;				
				$ufirstnm = '_ufirstnm_'.$uniq;

				$ukey = '_ukey_'.$uniq;
				$uvar = '_uvar_'.$uniq;

				$src = str_replace(
							$tag->plain(),
							sprintf('<?php %s=%s; %s=%s; %s=%s=1; %s=null; %s=%s*%s; %s=array(); ?>'
									.'<rt:loop param="%s" var="%s" key="%s" total="%s" offset="%s" first="%s">'
										.'<?php if(%s <= %s){ %s[$%s]=$%s; } ?>'
										.'<rt:first><?php %s=$%s; ?></rt:first>'
										.'<rt:last><?php %s=%s; ?></rt:last>'
										.'<?php if(%s===%s){ ?>'
											.'<?php if(isset(%s)){ $%s=""; } ?>'
											.'<?php %s=sizeof(%s); ?>'
											.'<?php %s=ceil($%s/%s); ?>'
											.'%s'
											.'<?php %s=array(); %s=null; %s=1; %s++; ?>'
										.'<?php }else{ %s++; } ?>'
									.'</rt:loop>'
									,$ucols,$cols,$urows,$rows,$cols_count,$rows_count,$ufirst,$ulimit,$ucols,$urows,$var
									,$param,$uvar,$ukey,$total,$offset,$ufirstnm
										,$cols_count,$ucols,$var,$ukey,$uvar
										,$ufirst,$ufirstnm
										,$cols_count,$ucols
										,$cols_count,$ucols
											,$ufirst,$ufirstnm
											,$cols_total,$var
											,$rows_total,$total,$ucols
											,$value
											,$var,$ufirst,$cols_count,$rows_count
										,$cols_count
							)
							.($tag->is_param('rows') ? 
								sprintf('<?php for(;%s<=%s;%s++){ %s=array(); ?>%s<?php } ?>',$rows_count,$rows,$rows_count,$var,$value) : ''
							)
							,$src
						);
			}
		}
		return $src;
		/***
			# unit
			$src = text('
						<rt:unit param="abc" var="unit_list" cols="3" offset="2" counter="counter">
						<rt:first>FIRST</rt:first>{$counter}{
						<rt:loop param="unit_list" var="a"><rt:first>first</rt:first>{$a}<rt:last>last</rt:last></rt:loop>
						}
						<rt:last>LAST</rt:last>
						</rt:unit>
					');
			$result = text('
							FIRST1{
							first234last}
							2{
							first567last}
							3{
							first8910last}
							LAST
						');
			$template = new Template();
			$template->vars("abc",array(1,2,3,4,5,6,7,8,9,10));
			eq($result,$template->execute($src));
		*/
		/***
			# rows_fill
			$src = text('<rt:unit param="abc" var="abc_var" cols="3" rows="3">[<rt:loop param="abc_var" var="a" limit="3"><rt:fill>0<rt:else />{$a}</rt:fill></rt:loop>]</rt:unit>');
			$result = '[123][400][000]';
			$template = new Template();			
			$template->vars("abc",array(1,2,3,4));
			eq($result,$template->execute($src));
			
			$src = text('<rt:unit param="abc" var="abc_var" offset="3" cols="3" rows="3">[<rt:loop param="abc_var" var="a" limit="3"><rt:fill>0<rt:else />{$a}</rt:fill></rt:loop>]</rt:unit>');
			$result = '[340][000][000]';
			$template = new Template();
			$template->vars("abc",array(1,2,3,4));
			eq($result,$template->execute($src));

		 */
	}
	final private function rtloop($src){
		if(strpos($src,'rt:loop') !== false){
			while(Tag::setof($tag,$src,'rt:loop')){
				$param = ($tag->is_param('param')) ? $this->variable_string($this->parse_plain_variable($tag->in_param('param'))) : null;
				$offset = ($tag->is_param('offset')) ? (ctype_digit($tag->in_param('offset')) ? $tag->in_param('offset') : $this->variable_string($this->parse_plain_variable($tag->in_param('offset')))) : 1;
				$limit = ($tag->is_param('limit')) ? (ctype_digit($tag->in_param('limit')) ? $tag->in_param('limit') : $this->variable_string($this->parse_plain_variable($tag->in_param('limit')))) : 0;
				if(empty($param) && $tag->is_param('range')){
					list($range_start,$range_end) = explode(',',$tag->in_param('range'),2);
					$range = ($tag->is_param('range_step')) ? sprintf('range(%d,%d,%d)',$range_start,$range_end,$tag->in_param('range_step')) :
																sprintf('range("%s","%s")',$range_start,$range_end);
					$param = sprintf('array_combine(%s,%s)',$range,$range);
				}
				$is_fill = false;
				$uniq = uniqid('');
				$even = $tag->in_param('even_value','even');
				$odd = $tag->in_param('odd_value','odd');
				$evenodd = '$'.$tag->in_param('evenodd','loop_evenodd');

				$first_value = $tag->in_param('first_value','first');
				$first = '$'.$tag->in_param('first','_first_'.$uniq);
				$first_flg = '$__isfirst__'.$uniq;
				$last_value = $tag->in_param('last_value','last');
				$last = '$'.$tag->in_param('last','_last_'.$uniq);
				$last_flg = '$__islast__'.$uniq;
				$shortfall = '$'.$tag->in_param('shortfall','_DEFI_'.$uniq);

				$var = '$'.$tag->in_param('var','_var_'.$uniq);
				$key = '$'.$tag->in_param('key','_key_'.$uniq);
				$total = '$'.$tag->in_param('total','_total_'.$uniq);
				$vtotal = '$__vtotal__'.$uniq;				
				$counter = '$'.$tag->in_param('counter','_counter_'.$uniq);
				$loop_counter = '$'.$tag->in_param('loop_counter','_loop_counter_'.$uniq);
				$reverse = (strtolower($tag->in_param('reverse') === 'true'));

				$varname = '$_'.$uniq;
				$countname = '$__count__'.$uniq;
				$lcountname = '$__vcount__'.$uniq;
				$offsetname	= '$__offset__'.$uniq;
				$limitname = '$__limit__'.$uniq;

				$value = $tag->value();
				$empty_value = null;
				while(Tag::setof($subtag,$value,'rt:loop')){
					$value = $this->rtloop($value);
				}
				while(Tag::setof($subtag,$value,'rt:first')){
					$value = str_replace($subtag->plain(),sprintf('<?php if(isset(%s)%s){ ?>%s<?php } ?>',$first
					,(($subtag->in_param('last') === 'false') ? sprintf(' && (%s !== 1) ',$total) : '')
					,preg_replace("/<rt\:else[\s]*\/>/i","<?php }else{ ?>",$this->rtloop($subtag->value()))),$value);
				}
				while(Tag::setof($subtag,$value,'rt:middle')){
					$value = str_replace($subtag->plain(),sprintf('<?php if(!isset(%s) && !isset(%s)){ ?>%s<?php } ?>',$first,$last
					,preg_replace("/<rt\:else[\s]*\/>/i","<?php }else{ ?>",$this->rtloop($subtag->value()))),$value);
				}
				while(Tag::setof($subtag,$value,'rt:last')){
					$value = str_replace($subtag->plain(),sprintf('<?php if(isset(%s)%s){ ?>%s<?php } ?>',$last
					,(($subtag->in_param('first') === 'false') ? sprintf(' && (%s !== 1) ',$vtotal) : '')
					,preg_replace("/<rt\:else[\s]*\/>/i","<?php }else{ ?>",$this->rtloop($subtag->value()))),$value);
				}
				while(Tag::setof($subtag,$value,'rt:fill')){
					$is_fill = true;
					$value = str_replace($subtag->plain(),sprintf('<?php if(%s > %s){ ?>%s<?php } ?>',$lcountname,$total
					,preg_replace("/<rt\:else[\s]*\/>/i","<?php }else{ ?>",$this->rtloop($subtag->value()))),$value);
				}				
				$value = $this->rtif($value);
				if(preg_match("/^(.+)<rt\:else[\s]*\/>(.+)$/ims",$value,$match)){
					list(,$value,$empty_value) = $match;
				}
				$src = str_replace(
							$tag->plain(),
							sprintf("<?php try{ ?>"
									."<?php "
										." %s=%s;"
										." if(is_array(%s)){"
											." if(%s){ krsort(%s); }"
											." %s=%s=sizeof(%s); %s=%s=1; %s=%s; %s=((%s>0) ? (%s + %s) : 0); "
											." %s=%s=false; %s=0; %s=%s=null;"
											." if(%s){ for(\$i=0;\$i<(%s+%s-%s);\$i++){ %s[] = null; } %s=sizeof(%s); }"
											." foreach(%s as %s => %s){"
												." if(%s <= %s){"
													." if(!%s){ %s=true; %s='%s'; }"
													." if((%s > 0 && (%s+1) == %s) || %s===%s){ %s=true; %s='%s'; %s=(%s-%s+1) * -1;}"													
													." %s=((%s %% 2) === 0) ? '%s' : '%s';"
													." %s=%s; %s=%s;"
													." ?>%s<?php "
													." %s=%s=null;"
													." %s++;"
												." }"
												." %s++;"
												." if(%s > 0 && %s >= %s){ break; }"
											." }"
											." if(!%s){ ?>%s<?php } "
											." unset(%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s);"
										." }"
									." ?>"
									."<?php }catch(Exception \$e){ if(!isset(\$_nes_)){print('".self::$exception_str."');} } ?>"
									,$varname,$param
									,$varname
										,(($reverse) ? 'true' : 'false'),$varname
										,$vtotal,$total,$varname,$countname,$lcountname,$offsetname,$offset,$limitname,$limit,$offset,$limit
										,$first_flg,$last_flg,$shortfall,$first,$last
										,($is_fill ? 'true' : 'false'),$offsetname,$limitname,$total,$varname,$vtotal,$varname
										,$varname,$key,$var
											,$offsetname,$lcountname

											
												,$first_flg,$first_flg,$first,str_replace("'","\\'",$first_value)
												,$limitname,$lcountname,$limitname,$lcountname,$vtotal,$last_flg,$last,str_replace("'","\\'",$last_value),$shortfall,$lcountname,$limitname


												,$evenodd,$countname,$even,$odd
												,$counter,$countname,$loop_counter,$lcountname
												,$value
												,$first,$last
												,$countname
											,$lcountname
											,$limitname,$lcountname,$limitname
									,$first_flg,$empty_value
									,$var,$counter,$key,$countname,$lcountname,$offsetname,$limitname,$varname,$first,$first_flg,$last,$last_flg
							)
							,$src
						);
			}
		}
		return $src;
		/***
			$src = text('
						<rt:loop param="abc" loop_counter="loop_counter" key="loop_key" var="loop_var">
						{$loop_counter}: {$loop_key} => {$loop_var}
						</rt:loop>
						hoge
					');
			$result = text('
							1: A => 456
							2: B => 789
							3: C => 010
							hoge
						');
			$template = new Template();
			$template->vars("abc",array("A"=>"456","B"=>"789","C"=>"010"));
			eq($result,$template->execute($src));
		*/
		/***
			$template = new Template();
			$src = text('
						<rt:loop param="abc" offset="2" limit="2" loop_counter="loop_counter" key="loop_key" var="loop_var">
						{$loop_counter}: {$loop_key} => {$loop_var}
						</rt:loop>
						hoge
					');
			$result = text('
							2: B => 789
							3: C => 010
							hoge
						');
			$template = new Template();
			$template->vars("abc",array("A"=>"456","B"=>"789","C"=>"010","D"=>"999"));
			eq($result,$template->execute($src));
		*/
		/***
			# limit
			$template = new Template();
			$src = text('
						<rt:loop param="abc" offset="{$offset}" limit="{$limit}" loop_counter="loop_counter" key="loop_key" var="loop_var">
						{$loop_counter}: {$loop_key} => {$loop_var}
						</rt:loop>
						hoge
					');
			$result = text('
							2: B => 789
							3: C => 010
							4: D => 999
							hoge
						');
			$template = new Template();
			$template->vars("abc",array("A"=>"456","B"=>"789","C"=>"010","D"=>"999","E"=>"111"));
			$template->vars("offset",2);
			$template->vars("limit",3);
			eq($result,$template->execute($src));
		*/
		/***
			# range
			$template = new Template();
			$src = text('<rt:loop range="0,5" var="var">{$var}</rt:loop>');
			$result = text('012345');
			eq($result,$template->execute($src));

			$src = text('<rt:loop range="0,6" range_step="2" var="var">{$var}</rt:loop>');
			$result = text('0246');
			eq($result,$template->execute($src));

			$src = text('<rt:loop range="A,F" var="var">{$var}</rt:loop>');
			$result = text('ABCDEF');
			eq($result,$template->execute($src));
		 */
		/***
			# multi
			$template = new Template();
			$src = text('<rt:loop range="1,2" var="a"><rt:loop range="1,2" var="b">{$a}{$b}</rt:loop>-</rt:loop>');
			$result = text('1112-2122-');
			eq($result,$template->execute($src));
		 */
		/***
			# empty
			$template = new Template();
			$src = text('<rt:loop param="abc">aaa</rt:loop>');
			$result = text('');
			$template->vars("abc",array());
			eq($result,$template->execute($src));
		 */
		/***
			# total
			$template = new Template();
			$src = text('<rt:loop param="abc" total="total">{$total}</rt:loop>');
			$result = text('4444');
			$template->vars("abc",array(1,2,3,4));
			eq($result,$template->execute($src));

			$template = new Template();
			$src = text('<rt:loop param="abc" total="total" offset="2" limit="2">{$total}</rt:loop>');
			$result = text('44');
			$template->vars("abc",array(1,2,3,4));
			eq($result,$template->execute($src));
		 */
		/***
			# evenodd
			$template = new Template();
			$src = text('<rt:loop range="0,5" evenodd="evenodd" counter="counter">{$counter}[{$evenodd}]</rt:loop>');
			$result = text('1[odd]2[even]3[odd]4[even]5[odd]6[even]');
			eq($result,$template->execute($src));
		 */
		/***
			# first_last
			$template = new Template();
			$src = text('<rt:loop param="abc" var="var" first="first" last="last">{$first}{$var}{$last}</rt:loop>');
			$result = text('first12345last');
			$template->vars("abc",array(1,2,3,4,5));
			eq($result,$template->execute($src));

			$template = new Template();
			$src = text('<rt:loop param="abc" var="var" first="first" last="last" offset="2" limit="2">{$first}{$var}{$last}</rt:loop>');
			$result = text('first23last');
			$template->vars("abc",array(1,2,3,4,5));
			eq($result,$template->execute($src));

			$template = new Template();
			$src = text('<rt:loop param="abc" var="var" offset="2" limit="3"><rt:first>F</rt:first>[<rt:middle>{$var}</rt:middle>]<rt:last>L</rt:last></rt:loop>');
			$result = text('F[][3][]L');
			$template->vars("abc",array(1,2,3,4,5,6));
			eq($result,$template->execute($src));
		*/
		/***
			# first_last_block
			$template = new Template();
			$src = text('<rt:loop param="abc" var="var" offset="2" limit="3"><rt:first>F<rt:if param="var" value="1">I<rt:else />E</rt:if><rt:else />nf</rt:first>[<rt:middle>{$var}<rt:else />nm</rt:middle>]<rt:last>L<rt:else />nl</rt:last></rt:loop>');

			$result = text('FE[nm]nlnf[3]nlnf[nm]L');
			$template->vars("abc",array(1,2,3,4,5,6));
			eq($result,$template->execute($src));
		 */
		/***
			# first_in_last
			$template = new Template();
			$src = text('<rt:loop param="abc" var="var"><rt:last>L</rt:last></rt:loop>');
			$template->vars("abc",array(1));
			eq("L",$template->execute($src));

			$template = new Template();
			$src = text('<rt:loop param="abc" var="var"><rt:last first="false">L</rt:last></rt:loop>');
			$template->vars("abc",array(1));
			eq("",$template->execute($src));
		 */
		/***
			# last_in_first
			$template = new Template();
			$src = text('<rt:loop param="abc" var="var"><rt:first>F</rt:first></rt:loop>');
			$template->vars("abc",array(1));
			eq("F",$template->execute($src));

			$template = new Template();
			$src = text('<rt:loop param="abc" var="var"><rt:first last="false">F</rt:first></rt:loop>');
			$template->vars("abc",array(1));
			eq("",$template->execute($src));
		 */
		/***
			# difi
			$template = new Template();
			$src = text('<rt:loop param="abc" limit="10" shortfall="difi" var="var">{$var}{$difi}</rt:loop>');
			$result = text('102030405064');
			$template->vars("abc",array(1,2,3,4,5,6));
			eq($result,$template->execute($src));
		*/
		/***
			# empty
			$template = new Template();
			$src = text('<rt:loop param="abc">aaaaaa<rt:else />EMPTY</rt:loop>');
			$result = text('EMPTY');
			$template->vars("abc",array());
			eq($result,$template->execute($src));
		*/
		/***
			# fill
			$template = new Template();
			$src = text('<rt:loop param="abc" var="a" offset="4" limit="4"><rt:fill>hoge<rt:last>L</rt:last><rt:else /><rt:first>F</rt:first>{$a}</rt:fill></rt:loop>');
			$result = text('F45hogehogeL');
			$template->vars("abc",array(1,2,3,4,5));
			eq($result,$template->execute($src));
			
			$template = new Template();
			$src = text('<rt:loop param="abc" var="a" offset="4" limit="4"><rt:fill><rt:first>f</rt:first>hoge<rt:last>L</rt:last><rt:else /><rt:first>F</rt:first>{$a}</rt:fill><rt:else />empty</rt:loop>');
			$result = text('fhogehogehogehogeL');
			$template->vars("abc",array());
			eq($result,$template->execute($src));			
		*/
		/***
			# fill_no_limit
			$template = new Template();
			$src = text('<rt:loop param="abc" var="a"><rt:fill>hoge<rt:last>L</rt:last><rt:else /><rt:first>F</rt:first>{$a}</rt:fill></rt:loop>');
			$result = text('F12345');
			$template->vars("abc",array(1,2,3,4,5));
			eq($result,$template->execute($src));
		*/
		/***
			# fill_last
			$template = new Template();
			$src = text('<rt:loop param="abc" var="a" limit="3" offset="4"><rt:fill>hoge<rt:else />{$a}</rt:fill><rt:last>Last</rt:last></rt:loop>');
			$result = text('45hogeLast');
			$template->vars("abc",array(1,2,3,4,5));
			eq($result,$template->execute($src));
			
			$template = new Template();
			$src = text('<rt:loop param="abc" var="a" limit="3"><rt:fill>hoge<rt:else />{$a}</rt:fill><rt:last>Last</rt:last></rt:loop>');
			$result = text('123Last');
			$template->vars("abc",array(1,2,3,4,5));
			eq($result,$template->execute($src));
			
			$template = new Template();
			$src = text('<rt:loop param="abc" var="a" offset="6" limit="3"><rt:fill>hoge<rt:else />{$a}</rt:fill><rt:last>Last</rt:last></rt:loop>');
			$result = text('hogehogehogeLast');
			$template->vars("abc",array(1,2,3,4,5));
			eq($result,$template->execute($src));			
		*/
		/***
			# fill_first
			$template = new Template();
			$src = text('<rt:loop param="abc" var="a" limit="3" offset="4"><rt:fill>hoge<rt:else />{$a}</rt:fill><rt:first>First</rt:first></rt:loop>');
			$result = text('4First5hoge');
			$template->vars("abc",array(1,2,3,4,5));
			eq($result,$template->execute($src));
		*/
		/***
			# fill_middle
			$template = new Template();
			$src = text('<rt:loop param="abc" var="a" limit="4" offset="4"><rt:fill>hoge<rt:else />{$a}</rt:fill><rt:middle>M</rt:middle></rt:loop>');
			$result = text('45MhogeMhoge');
			$template->vars("abc",array(1,2,3,4,5));
			eq($result,$template->execute($src));
		*/
	}
	final private function rtif($src){
		if(strpos($src,'rt:if') !== false){
			while(Tag::setof($tag,$src,'rt:if')){
				if(!$tag->is_param('param')) throw new LogicException('if');
				$arg1 = $this->variable_string($this->parse_plain_variable($tag->in_param('param')));

				if($tag->is_param('value')){
					$arg2 = $this->parse_plain_variable($tag->in_param('value'));
					if($arg2 == 'true' || $arg2 == 'false' || ctype_digit(Text::str($arg2))){
						$cond = sprintf('<?php if(%s === %s || %s === "%s"){ ?>',$arg1,$arg2,$arg1,$arg2);
					}else{
						if($arg2 === '' || $arg2[0] != '$') $arg2 = '"'.$arg2.'"';
						$cond = sprintf('<?php if(%s === %s){ ?>',$arg1,$arg2);
					}
				}else{
					$uniq = uniqid('$I');
					$cond = sprintf('<?php try{ %s=%s; }catch(\\Exception $e){ %s=null; } ?>',$uniq,$arg1,$uniq)
								.sprintf('<?php if(%s !== null && %s !== false && ( (!is_string(%s) && !is_array(%s)) || (is_string(%s) && %s !== "") || (is_array(%s) && !empty(%s)) ) ){ ?>',$uniq,$uniq,$uniq,$uniq,$uniq,$uniq,$uniq,$uniq);
				}
				$src = str_replace(
							$tag->plain()
							,'<?php try{ ?>'.$cond
								.preg_replace("/<rt\:else[\s]*\/>/i","<?php }else{ ?>",$tag->value())
							."<?php } ?>"."<?php }catch(Exception \$e){ if(!isset(\$_nes_)){print('".self::$exception_str."');} } ?>"
							,$src
						);
			}
		}
		return $src;
		/***
			$src = text('<rt:if param="abc">hoge</rt:if>');
			$result = text('hoge');
			$template = new Template();
			$template->vars("abc",true);
			eq($result,$template->execute($src));

			$src = text('<rt:if param="abc" value="xyz">hoge</rt:if>');
			$result = text('hoge');
			$template = new Template();
			$template->vars("abc","xyz");
			eq($result,$template->execute($src));

			$src = text('<rt:if param="abc" value="1">hoge</rt:if>');
			$result = text('hoge');
			$template = new Template();
			$template->vars("abc",1);
			eq($result,$template->execute($src));

			$src = text('<rt:if param="abc" value="1">bb<rt:else />aa</rt:if>');
			$result = text('bb');
			$template = new Template();
			$template->vars("abc",1);
			eq($result,$template->execute($src));

			$src = text('<rt:if param="abc" value="1">bb<rt:else />aa</rt:if>');
			$result = text('aa');
			$template = new Template();
			$template->vars("abc",2);
			eq($result,$template->execute($src));

			$src = text('<rt:if param="abc" value="{$a}">bb<rt:else />aa</rt:if>');
			$result = text('bb');
			$template = new Template();
			$template->vars("abc",2);
			$template->vars("a",2);
			eq($result,$template->execute($src));
			
			$src = text('<rt:loop range="1,5" var="c"><rt:if param="{$c}" value="{$a}">A<rt:else />{$c}</rt:if></rt:loop>');
			$result = text('1A345');
			$template = new Template();
			$template->vars("abc",2);
			$template->vars("a",2);
			eq($result,$template->execute($src));			
		*/
	}
	final private function rtpager($src){
		return $this->rtpaginator($this->rtpaginator($src,'rt:pager'),'rt:paginator');
	}
	final private function rtpaginator($src,$rtname){
		if(strpos($src,'rt:pager') !== false){
			while(Tag::setof($tag,$src,$rtname)){
				$param = $this->variable_string($this->parse_plain_variable($tag->in_param('param','paginator')));
				$func = sprintf('<?php try{ ?><?php if(%s instanceof Paginator){ ?>',$param);
				if($tag->is_value()){
					$func .= $tag->value();
				}else{
					$uniq = uniqid('');
					$name = '$__pager__'.$uniq;
					$counter_var = '$__counter__'.$uniq;
					$tagtype = $tag->in_param('tag');
					$href = $tag->in_param('href','?');
					$stag = (empty($tagtype)) ? '' : '<'.$tagtype.' class="%s">';
					$etag = (empty($tagtype)) ? '' : '</'.$tagtype.'>';
					$navi = array_change_key_case(array_flip(explode(',',$tag->in_param('navi','prev,next,first,last,counter'))));
					$counter = $tag->in_param('counter',50);
					$total = '$__pagertotal__'.$uniq;
					if(isset($navi['prev'])) $func .= sprintf('<?php if(%s->is_prev()){ ?>%s<a href="%s{%s.query_prev()}">%s</a>%s<?php } ?>',$param,sprintf($stag,'prev'),$href,$param,Gettext::trans('prev'),$etag);
					if(isset($navi['first'])) $func .= sprintf('<?php if(%s->is_first(%d)){ ?>%s<a href="%s{%s.query(%s.first())}">{%s.first()}</a>%s%s...%s<?php } ?>',$param,$counter,sprintf($stag,'first'),$href,$param,$param,$param,$etag,sprintf($stag,'first_gt'),$etag);
					if(isset($navi['counter'])){
						$func .= sprintf('<?php %s = %s; if(!empty(%s)){ ?>',$total,$param,$total);
						$func .= sprintf('<?php for(%s=%s->which_first(%d);%s<=%s->which_last(%d);%s++){ ?>',$counter_var,$param,$counter,$counter_var,$param,$counter,$counter_var);
						$func .= sprintf('%s<?php if(%s == %s->current()){ ?><strong>{%s}</strong><?php }else{ ?><a href="%s{%s.query(%s)}">{%s}</a><?php } ?>%s',sprintf($stag,'count'),$counter_var,$param,$counter_var,$href,$param,$counter_var,$counter_var,$etag);
						$func .= '<?php } ?>';
						$func .= '<?php } ?>';
					}
					if(isset($navi['last'])) $func .= sprintf('<?php if(%s->is_last(%d)){ ?>%s...%s%s<a href="%s{%s.query(%s.last())}">{%s.last()}</a>%s<?php } ?>',$param,$counter,sprintf($stag,'last_lt'),$etag,sprintf($stag,'last'),$href,$param,$param,$param,$etag);
					if(isset($navi['next'])) $func .= sprintf('<?php if(%s->is_next()){ ?>%s<a href="%s{%s.query_next()}">%s</a>%s<?php } ?>',$param,sprintf($stag,'next'),$href,$param,Gettext::trans('next'),$etag);
				}
				$func .= "<?php } ?><?php }catch(Exception \$e){ if(!isset(\$_nes_)){print('".self::$exception_str."');} } ?>";
				$src = str_replace($tag->plain(),$func,$src);
			}
		}
		return $this->rtloop($src);
		/***
			$template = new Template();

			$template->vars("paginator",new Paginator(10,2,100));
			$src = '<rt:pager param="paginator" counter="3" tag="span" />';
			$result = text('<span class="prev"><a href="?page=1">prev</a></span><span class="first"><a href="?page=1">1</a></span><span class="first_gt">...</span><span class="count"><a href="?page=1">1</a></span><span class="count"><strong>2</strong></span><span class="count"><a href="?page=3">3</a></span><span class="last_lt">...</span><span class="last"><a href="?page=10">10</a></span><span class="next"><a href="?page=3">next</a></span>');
			eq($result,$template->execute($src));

			$template->vars("paginator",new Paginator(10,1,100));
			$src = '<rt:pager param="paginator" counter="3" />';
			$result = text('<strong>1</strong><a href="?page=2">2</a><a href="?page=3">3</a>...<a href="?page=10">10</a><a href="?page=2">next</a>');
			eq($result,$template->execute($src));

			$template->vars("paginator",new Paginator(10,10,100));
			$src = '<rt:pager param="paginator" counter="3" tag="span" />';
			$result = text('<span class="prev"><a href="?page=9">prev</a></span><span class="first"><a href="?page=1">1</a></span><span class="first_gt">...</span><span class="count"><a href="?page=8">8</a></span><span class="count"><a href="?page=9">9</a></span><span class="count"><strong>10</strong></span>');
			eq($result,$template->execute($src));
		*/
	}
	final private function rtinvalid($src){
		if(strpos($src,'rt:invalid') !== false){
			while(Tag::setof($tag,$src,'rt:invalid')){
				$param = $this->parse_plain_variable($tag->in_param('param'));
				$var = $this->parse_plain_variable($tag->in_param('var','rtinvalid_var'.uniqid('')));
				$messages = $this->parse_plain_variable($tag->in_param('messages','rtinvalid_mes'.uniqid('')));
				if(!isset($param[0]) || $param[0] !== '$') $param = '"'.$param.'"';
				$value = $tag->value();
				$tagtype = $tag->in_param('tag');
				$stag = (empty($tagtype)) ? '' : '<'.$tagtype.' class="%s">';
				$etag = (empty($tagtype)) ? '' : '</'.$tagtype.'>';

				if(empty($value)){
					$varnm = 'rtinvalid_varnm'.uniqid('');
					$value = sprintf("<rt:loop param=\"%s\" var=\"%s\">\n"
										."%s{\$%s}%s"
									."</rt:loop>\n",$messages,$varnm,sprintf($stag,'exception'),$varnm,$etag);
				}
				$src = str_replace(
							$tag->plain(),
							sprintf("<?php if(Exceptions::has(%s)){ ?>"
										."<?php \$%s = Exceptions::gets(%s); ?>"
										."<?php \$%s = Exceptions::messages(%s); ?>"
										."%s"
									."<?php } ?>",$param,$var,$param,$messages,$param,$value),
							$src);
			}
		}
		return $src;
	}
	final private function parse_print_variable($src){
		foreach($this->match_variable($src) as $variable){
			$name = $this->parse_plain_variable($variable);
			$value = "<?php try{ ?>"."<?php @print(".$name."); ?>"."<?php }catch(Exception \$e){ if(!isset(\$_nes_)){print('".self::$exception_str."');} } ?>";
			$src = str_replace(array($variable."\n",$variable),array($value."\n\n",$value),$src);
		}
		return $src;
	}
	final private function match_variable($src){
		$hash = array();
		while(preg_match("/({(\\$[\$\w][^\t]*)})/s",$src,$vars,PREG_OFFSET_CAPTURE)){
			list($value,$pos) = $vars[1];
			if($value == "") break;
			if(substr_count($value,'}') > 1){
				for($i=0,$start=0,$end=0;$i<strlen($value);$i++){
					if($value[$i] == '{'){
						$start++;
					}else if($value[$i] == '}'){
						if($start == ++$end){
							$value = substr($value,0,$i+1);
							break;
						}
					}
				}
			}
			$length	= strlen($value);
			$src = substr($src,$pos + $length);
			$hash[sprintf('%03d_%s',$length,$value)] = $value;
		}
		krsort($hash,SORT_STRING);
		return $hash;
	}
	final private function parse_plain_variable($src){
		while(true){
			$array = $this->match_variable($src);
			if(sizeof($array) <= 0)	break;
			foreach($array as $v){
				$tmp = $v;
				if(preg_match_all("/([\"\'])([^\\1]+?)\\1/",$v,$match)){
					foreach($match[2] as $value) $tmp = str_replace($value,str_replace('.','__PERIOD__',$value),$tmp);
				}
				$src = str_replace($v,str_replace('.','->',substr($tmp,1,-1)),$src);
			}
		}
		return str_replace('[]','',str_replace('__PERIOD__','.',$src));
	}

	final private function variable_string($src){
		return (empty($src) || isset($src[0]) && $src[0] == '$') ? $src : '$'.$src;
	}
	final private function html_reform($src){
		$bool = false;
		foreach(Tag::anyhow($src)->in('form') as $obj){
			if(($obj->in_param('rt:aref') === 'true')){
				$form = $obj->value();
				foreach($obj->in(array('input','select')) as $tag){
					if($tag->is_param('name') || $tag->is_param('id')){
						$name = $this->parse_plain_variable($this->form_variable_name($tag->in_param('name',$tag->in_param('id'))));
						switch(strtolower($tag->name())){
							case 'input':
								switch(strtolower($tag->in_param('type'))){
									case 'radio':
									case 'checkbox':
										$tag->attr($this->check_selected($name,sprintf("'%s'",$this->parse_plain_variable($tag->in_param('value','true'))),'checked'));
										$form = str_replace($tag->plain(),$tag->get(),$form);
										$bool = true;
								}
								break;
							case 'select':
								$select = $tag->value();
								foreach($tag->in('option') as $option){
									$option->attr($this->check_selected($name,sprintf("'%s'",$this->parse_plain_variable($option->in_param('value'))),'selected'));
									$select = str_replace($option->plain(),$option->get(),$select);
								}
								$tag->value($select);
								$form = str_replace($tag->plain(),$tag->get(),$form);
								$bool = true;
						}
					}
				}
				$obj->rm_param('rt:aref');
				$obj->value($form);
				$src = str_replace($obj->plain(),$obj->get(),$src);
			}
		}
		return ($bool) ? $this->exec($src) : $src;
	}
	final private function html_form($src){
		$tag = Tag::anyhow($src);
		foreach($tag->in('form') as $obj){
			if($this->is_reference($obj)){
				foreach($obj->in(array('input','select','textarea')) as $tag){
					if(!$tag->is_param('rt:ref') && ($tag->is_param('name') || $tag->is_param('id'))){
						switch(strtolower($tag->in_param('type','text'))){
							case 'button':
							case 'submit':
								break;
							case 'file':
								$obj->param('enctype','multipart/form-data');
								$obj->param('method','post');
								break;
							default:
								$tag->param('rt:ref','true');
								$obj->value(str_replace($tag->plain(),$tag->get(),$obj->value()));
						}
					}
				}
			}
			$src = str_replace($obj->plain(),$obj->get(),$src);
		}
		return $this->html_input($src);
	}
	final private function no_exception_str($value){
		return '<?php $_nes_=1; ?>'.$value.'<?php $_nes_=null; ?>';
	}
	final private function html_input($src){
		$tag = Tag::anyhow($src);
		foreach($tag->in(array('input','textarea','select')) as $obj){
			if('' != ($originalName = $obj->in_param('name',$obj->in_param('id','')))){
				$type = strtolower($obj->in_param('type','text'));
				$name = $this->parse_plain_variable($this->form_variable_name($originalName));
				$lname = strtolower($obj->name());
				$change = false;
				$uid = uniqid();

				if(substr($originalName,-2) !== '[]'){
					if($type == 'checkbox'){
						if($obj->in_param('rt:multiple','true') === 'true') $obj->param('name',$originalName.'[]');
						$obj->rm_param('rt:multiple');
						$change = true;
					}else if($obj->is_attr('multiple') || $obj->in_param('multiple') === 'multiple'){
						$obj->param('name',$originalName.'[]');
						$obj->rm_attr('multiple');
						$obj->param('multiple','multiple');
						$change = true;
					}
				}else if($obj->in_param('name') !== $originalName){
					$obj->param('name',$originalName);
					$change = true;
				}
				if($obj->is_param('rt:param') || $obj->is_param('rt:range')){
					switch($lname){
						case 'select':
							$value = sprintf('<rt:loop param="%s" var="%s" counter="%s" key="%s" offset="%s" limit="%s" reverse="%s" evenodd="%s" even_value="%s" odd_value="%s" range="%s" range_step="%s">'
											.'<option value="{$_t_.primary($%s,$%s)}">{$%s}</option>'
											.'</rt:loop>'
											,$obj->in_param('rt:param'),$obj->in_param('rt:var','loop_var'.$uid),$obj->in_param('rt:counter','loop_counter'.$uid)
											,$obj->in_param('rt:key','loop_key'.$uid),$obj->in_param('rt:offset','0'),$obj->in_param('rt:limit','0')
											,$obj->in_param('rt:reverse','false')
											,$obj->in_param('rt:evenodd','loop_evenodd'.$uid),$obj->in_param('rt:even_value','even'),$obj->in_param('rt:odd_value','odd')
											,$obj->in_param('rt:range'),$obj->in_param('rt:range_step',1)
											,$obj->in_param('rt:var','loop_var'.$uid),$obj->in_param('rt:key','loop_key'.$uid),$obj->in_param('rt:var','loop_var'.$uid)
							);
							$obj->value($this->rtloop($value));
							if($obj->is_param('rt:null')) $obj->value('<option value="">'.$obj->in_param('rt:null').'</option>'.$obj->value());
					}
					$obj->rm_param('rt:param','rt:key','rt:var','rt:counter','rt:offset','rt:limit','rt:null','rt:evenodd'
									,'rt:range','rt:range_step','rt:even_value','rt:odd_value');
					$change = true;
				}
				if($this->is_reference($obj)){
					switch($lname){
						case 'textarea':
							$obj->value($this->no_exception_str(sprintf('{$_t_.htmlencode(%s)}',((preg_match("/^{\$(.+)}$/",$originalName,$match)) ? '{$$'.$match[1].'}' : '{$'.$originalName.'}'))));
							break;
						case 'select':
							$select = $obj->value();
							foreach($obj->in('option') as $option){
								$value = $this->parse_plain_variable($option->in_param('value'));
								if(empty($value) || $value[0] != '$') $value = sprintf("'%s'",$value);
								$option->rm_attr('selected');
								$option->rm_param('selected');
								$option->attr($this->check_selected($name,$value,'selected'));
								$select = str_replace($option->plain(),$option->get(),$select);
							}
							$obj->value($select);
							break;
						case 'input':
							switch($type){
								case 'checkbox':
								case 'radio':
									$value = $this->parse_plain_variable($obj->in_param('value','true'));
									$value = (substr($value,0,1) != '$') ? sprintf("'%s'",$value) : $value;
									$obj->rm_attr('checked');
									$obj->rm_param('checked');
									$obj->attr($this->check_selected($name,$value,'checked'));
									break;
								case 'text':
								case 'hidden':
								case 'password':
								case 'search':
								case 'url':
								case 'email':
								case 'tel':
								case 'datetime':
								case 'date':
								case 'month':
								case 'week':
								case 'time':
								case 'datetime-local':
								case 'number':
								case 'range':
								case 'color':
									$obj->param('value',$this->no_exception_str(sprintf('{$_t_.htmlencode(%s)}',
																((preg_match("/^\{\$(.+)\}$/",$originalName,$match)) ?
																	'{$$'.$match[1].'}' :
																	'{$'.$originalName.'}'))));
									break;
							}
							break;
					}
					$change = true;
				}else if($obj->is_param('rt:ref')){
					$obj->rm_param('rt:ref');
					$change = true;
				}
				if($change){
					switch($lname){
						case 'textarea':
						case 'select':
							$obj->close_empty(false);
					}
					$src = str_replace($obj->plain(),$obj->get(),$src);
				}
			}
		}
		return $src;
		/***
			#input
			$src = text('
						<form rt:ref="true">
							<input type="text" name="aaa" />
							<input type="checkbox" name="bbb" value="hoge" />hoge
							<input type="checkbox" name="bbb" value="fuga" checked="checked" />fuga
							<input type="checkbox" name="eee" value="true" checked />foo
							<input type="checkbox" name="fff" value="false" />foo
							<input type="submit" />
							<textarea name="aaa"></textarea>

							<select name="ddd" size="5" multiple>
								<option value="123" selected="selected">123</option>
								<option value="456">456</option>
								<option value="789" selected>789</option>
							</select>
							<select name="XYZ" rt:param="xyz"></select>
						</form>
					');
			$result = text('
						<form>
							<input type="text" name="aaa" value="hogehoge" />
							<input type="checkbox" name="bbb[]" value="hoge" checked="checked" />hoge
							<input type="checkbox" name="bbb[]" value="fuga" />fuga
							<input type="checkbox" name="eee[]" value="true" checked="checked" />foo
							<input type="checkbox" name="fff[]" value="false" checked="checked" />foo
							<input type="submit" />
							<textarea name="aaa">hogehoge</textarea>

							<select name="ddd[]" size="5" multiple="multiple">
								<option value="123">123</option>
								<option value="456" selected="selected">456</option>
								<option value="789" selected="selected">789</option>
							</select>
							<select name="XYZ"><option value="A">456</option><option value="B" selected="selected">789</option><option value="C">010</option></select>
						</form>
						');
			$template = new Template();
			$template->vars("aaa","hogehoge");
			$template->vars("bbb","hoge");
			$template->vars("XYZ","B");
			$template->vars("xyz",array("A"=>"456","B"=>"789","C"=>"010"));
			$template->vars("ddd",array("456","789"));
			$template->vars("eee",true);
			$template->vars("fff",false);
			eq($result,$template->execute($src));

			$src = text('
						<form rt:ref="true">
							<select name="ddd" rt:param="abc">
							</select>
						</form>
					');
			$result = text('
						<form>
							<select name="ddd"><option value="123">123</option><option value="456" selected="selected">456</option><option value="789">789</option></select>
						</form>
						');
			$template = new Template();
			$template->vars("abc",array(123=>123,456=>456,789=>789));
			$template->vars("ddd","456");
			eq($result,$template->execute($src));

			$src = text('
						<form rt:ref="true">
						<rt:loop param="abc" var="v">
						<input type="checkbox" name="ddd" value="{$v}" />
						</rt:loop>
						</form>
					');
			$result = text('
							<form>
							<input type="checkbox" name="ddd[]" value="123" />
							<input type="checkbox" name="ddd[]" value="456" checked="checked" />
							<input type="checkbox" name="ddd[]" value="789" />
							</form>
						');
			$template = new Template();
			$template->vars("abc",array(123=>123,456=>456,789=>789));
			$template->vars("ddd","456");
			eq($result,$template->execute($src));

		*/
		/***
			# textarea
			$src = text('
							<form>
								<textarea name="hoge"></textarea>
							</form>
						');
			$template = new Template();
			eq($src,$template->execute($src));
		 */
		/***
			#select
			$src = '<form><select name="abc" rt:param="abc"></select></form>';
			$template = new Template();
			$template->vars("abc",array(123=>123,456=>456));
			eq('<form><select name="abc"><option value="123">123</option><option value="456">456</option></select></form>',$template->execute($src));
		 */
		/***
			#select_obj
			
			$name1 = create_class('
				protected $abc;
				protected function __str__(){
					return "s".$this->abc;
				}
			',null,'
				@var serial $abc
			');
			$src = '<form><select name="abc" rt:param="abc"></select></form>';
			$template = new Template();
			$template->vars("abc",array(new $name1("abc=123"),new $name1("abc=456")));
			eq('<form><select name="abc"><option value="123">s123</option><option value="456">s456</option></select></form>',$template->execute($src));
			
			$name1 = create_class('
				protected $abc;
				protected $def;
				protected function __str__(){
					return "s".$this->abc;
				}
			',null,'
				@var integer $abc @{"primary":true}
				@var string $def @{"primary":true}
			');
			$src = '<form><select name="abc" rt:param="abc"></select></form>';
			$template = new Template();
			$template->vars("abc",array(new $name1("abc=123,def=D"),new $name1("abc=456,def=E")));
			eq('<form><select name="abc"><option value="123_D">s123</option><option value="456_E">s456</option></select></form>',$template->execute($src));			
		 */
		/***
			#multiple
			$src = '<form><input name="abc" type="checkbox" /></form>';
			$template = new Template();
			eq('<form><input name="abc[]" type="checkbox" /></form>',$template->execute($src));

			$src = '<form><input name="abc" type="checkbox" rt:multiple="false" /></form>';
			$template = new Template();
			eq('<form><input name="abc" type="checkbox" /></form>',$template->execute($src));
		 */
		/***
			# input_exception
			self::config_exception('EXCEPTION');
			$src = text('{$hoge}');
			$template = new Template();
			eq('EXCEPTION',$template->execute($src));

			$src = text('<form rt:ref="true"><input type="text" name="hoge" /></form>');
			$template = new Template();
			eq('<form><input type="text" name="hoge" value="" /></form>',$template->execute($src));

			$src = text('<form rt:ref="true"><input type="password" name="hoge" /></form>');
			$template = new Template();
			eq('<form><input type="password" name="hoge" value="" /></form>',$template->execute($src));
			
			$src = text('<form rt:ref="true"><input type="hidden" name="hoge" /></form>');
			$template = new Template();
			eq('<form><input type="hidden" name="hoge" value="" /></form>',$template->execute($src));

			$src = text('<form rt:ref="true"><input type="checkbox" name="hoge" /></form>');
			$template = new Template();
			eq('<form><input type="checkbox" name="hoge[]" /></form>',$template->execute($src));
			
			$src = text('<form rt:ref="true"><input type="radio" name="hoge" /></form>');
			$template = new Template();
			eq('<form><input type="radio" name="hoge" /></form>',$template->execute($src));

			$src = text('<form rt:ref="true"><textarea name="hoge"></textarea></form>');
			$template = new Template();
			eq('<form><textarea name="hoge"></textarea></form>',$template->execute($src));

			$src = text('<form rt:ref="true"><select name="hoge"><option value="1">1</option><option value="2">2</option></select></form>');
			$template = new Template();
			eq('<form><select name="hoge"><option value="1">1</option><option value="2">2</option></select></form>',$template->execute($src));

			self::config_exception('');
		 */
		/***
			#html5
			$src = text('
							<form rt:ref="true">
								<input type="search" name="search" />
								<input type="tel" name="tel" />
								<input type="url" name="url" />
								<input type="email" name="email" />
								<input type="datetime" name="datetime" />
								<input type="datetime-local" name="datetime_local" />
								<input type="date" name="date" />
								<input type="month" name="month" />
								<input type="week" name="week" />
								<input type="time" name="time" />
								<input type="number" name="number" />
								<input type="range" name="range" />
								<input type="color" name="color" />
							</form>
						');
			$rslt = text('
							<form>
								<input type="search" name="search" value="hoge" />
								<input type="tel" name="tel" value="000-000-0000" />
								<input type="url" name="url" value="http://rhaco.org" />
								<input type="email" name="email" value="hoge@hoge.hoge" />
								<input type="datetime" name="datetime" value="1970-01-01T00:00:00.0Z" />
								<input type="datetime-local" name="datetime_local" value="1970-01-01T00:00:00.0Z" />
								<input type="date" name="date" value="1970-01-01" />
								<input type="month" name="month" value="1970-01" />
								<input type="week" name="week" value="1970-W15" />
								<input type="time" name="time" value="12:30" />
								<input type="number" name="number" value="1234" />
								<input type="range" name="range" value="7" />
								<input type="color" name="color" value="#ff0000" />
							</form>
						');
			$template = new Template();
			$template->vars("search","hoge");
			$template->vars("tel","000-000-0000");
			$template->vars("url","http://rhaco.org");
			$template->vars("email","hoge@hoge.hoge");
			$template->vars("datetime","1970-01-01T00:00:00.0Z");
			$template->vars("datetime_local","1970-01-01T00:00:00.0Z");
			$template->vars("date","1970-01-01");
			$template->vars("month","1970-01");
			$template->vars("week","1970-W15");
			$template->vars("time","12:30");
			$template->vars("number","1234");
			$template->vars("range","7");
			$template->vars("color","#ff0000");

			eq($rslt,$template->execute($src));
		 */
	}
	final private function check_selected($name,$value,$selected){
		return sprintf('<?php if('
					.'isset(%s) && (%s === %s '
										.' || (ctype_digit(Text::str(%s)) && %s == %s)'
										.' || ((%s == "true" || %s == "false") ? (%s === (%s == "true")) : false)'
										.' || in_array(%s,((is_array(%s)) ? %s : (is_null(%s) ? array() : array(%s))),true) '
									.') '
					.'){print(" %s=\"%s\"");} ?>'
					,$name,$name,$value
					,$name,$name,$value
					,$value,$value,$name,$value
					,$value,$name,$name,$name,$name
					,$selected,$selected
				);
	}
	final private function html_list($src){
		if(preg_match_all('/<(table|ul|ol)\s[^>]*rt\:/i',$src,$m,PREG_OFFSET_CAPTURE)){
			$tags = array();
			foreach($m[1] as $k => $v){
				if(Tag::setof($tag,substr($src,$v[1]-1),$v[0])) $tags[] = $tag;
			}
			foreach($tags as $obj){
				$name = strtolower($obj->name());
				$param = $obj->in_param('rt:param');
				$null = strtolower($obj->in_param('rt:null'));
				$value = sprintf('<rt:loop param="%s" var="%s" counter="%s" '
									.'key="%s" offset="%s" limit="%s" '
									.'reverse="%s" '
									.'evenodd="%s" even_value="%s" odd_value="%s" '
									.'range="%s" range_step="%s" '
									.'shortfall="%s">'
								,$param,$obj->in_param('rt:var','loop_var'),$obj->in_param('rt:counter','loop_counter')
								,$obj->in_param('rt:key','loop_key'),$obj->in_param('rt:offset','0'),$obj->in_param('rt:limit','0')
								,$obj->in_param('rt:reverse','false')
								,$obj->in_param('rt:evenodd','loop_evenodd'),$obj->in_param('rt:even_value','even'),$obj->in_param('rt:odd_value','odd')
								,$obj->in_param('rt:range'),$obj->in_param('rt:range_step',1)
								,$tag->in_param('rt:shortfall','_DEFI_'.uniqid())
							);
				$rawvalue = $obj->value();
				if($name == 'table' && Tag::setof($t,$rawvalue,'tbody')){
					$t->value($value.$this->table_tr_even_odd($t->value(),(($name == 'table') ? 'tr' : 'li'),$obj->in_param('rt:evenodd','loop_evenodd')).'</rt:loop>');
					$value = str_replace($t->plain(),$t->get(),$rawvalue);
				}else{
					$value = $value.$this->table_tr_even_odd($rawvalue,(($name == 'table') ? 'tr' : 'li'),$obj->in_param('rt:evenodd','loop_evenodd')).'</rt:loop>';
				}
				$obj->value($this->html_list($value));
				$obj->rm_param('rt:param','rt:key','rt:var','rt:counter','rt:offset','rt:limit','rt:null','rt:evenodd','rt:range'
								,'rt:range_step','rt:even_value','rt:odd_value','rt:shortfall');
				$src = str_replace($obj->plain(),
						($null === 'true') ? $this->rtif(sprintf('<rt:if param="%s">',$param).$obj->get().'</rt:if>') : $obj->get(),
						$src);
			}
		}
		return $src;
		/***
		 	$src = text('
						<table><tr><td><table rt:param="xyz" rt:var="o">
						<tr class="odd"><td>{$o["B"]}</td></tr>
						</table></td></tr></table>
					');
			$result = text('
							<table><tr><td><table><tr class="odd"><td>222</td></tr>
							<tr class="even"><td>444</td></tr>
							<tr class="odd"><td>666</td></tr>
							</table></td></tr></table>
						');
			$template = new Template();
			$template->vars("xyz",array(array("A"=>"111","B"=>"222"),array("A"=>"333","B"=>"444"),array("A"=>"555","B"=>"666")));
			eq($result,$template->execute($src));
		 */
		/***
		 	$src = text('
						<table rt:param="abc" rt:var="a"><tr><td><table rt:param="a" rt:var="x"><tr><td>{$x}</td></tr></table></td></td></table>
					');
			$result = text('
						<table><tr><td><table><tr><td>A</td></tr><tr><td>B</td></tr></table></td></td><tr><td><table><tr><td>C</td></tr><tr><td>D</td></tr></table></td></td></table>
						');
			$template = new Template();
			$template->vars("abc",array(array("A","B"),array("C","D")));
			eq($result,$template->execute($src));
		 */
		/***
		 	$src = text('
						<ul rt:param="abc" rt:var="a"><li><ul rt:param="a" rt:var="x"><li>{$x}</li></ul></li></ul>
					');
			$result = text('
						<ul><li><ul><li>A</li><li>B</li></ul></li><li><ul><li>C</li><li>D</li></ul></li></ul>
						');
			$template = new Template();
			$template->vars("abc",array(array("A","B"),array("C","D")));
			eq($result,$template->execute($src));
		 */
		/***
		 	$src = text('
						<table rt:param="xyz" rt:var="o">
						<tr class="odd"><td>{$o["B"]}</td></tr>
						</table>
					');
			$result = text('
							<table><tr class="odd"><td>222</td></tr>
							<tr class="even"><td>444</td></tr>
							<tr class="odd"><td>666</td></tr>
							</table>
						');
			$template = new Template();
			$template->vars("xyz",array(array("A"=>"111","B"=>"222"),array("A"=>"333","B"=>"444"),array("A"=>"555","B"=>"666")));
			eq($result,$template->execute($src));
		*/
		/***
		 	$src = text('
						<table rt:param="xyz" rt:var="o">
						<tr><td>{$o["B"]}</td></tr>
						</table>
					');
			$result = text('
							<table><tr><td>222</td></tr>
							<tr><td>444</td></tr>
							<tr><td>666</td></tr>
							</table>
						');
			$template = new Template();
			$template->vars("xyz",array(array("A"=>"111","B"=>"222"),array("A"=>"333","B"=>"444"),array("A"=>"555","B"=>"666")));
			eq($result,$template->execute($src));
		*/
		/***
		 	$src = text('
						<table rt:param="xyz" rt:var="o" rt:offset="1" rt:limit="1">
						<tr><td>{$o["B"]}</td></tr>
						</table>
					');
			$result = text('
							<table><tr><td>222</td></tr>
							</table>
						');
			$template = new Template();
			$template->vars("xyz",array(array("A"=>"111","B"=>"222"),array("A"=>"333","B"=>"444"),array("A"=>"555","B"=>"666")));
			eq($result,$template->execute($src));
		*/
		/***
		 	$src = text('
						<table rt:param="xyz" rt:var="o" rt:offset="1" rt:limit="1">
						<thead>
							<tr><th>hoge</th></tr>
						</thead>
						<tbody>
							<tr><td>{$o["B"]}</td></tr>
						</tbody>
						</table>
					');
			$result = text('
							<table>
							<thead>
								<tr><th>hoge</th></tr>
							</thead>
							<tbody>	<tr><td>222</td></tr>
							</tbody>
							</table>
						');
			$template = new Template();
			$template->vars("xyz",array(array("A"=>"111","B"=>"222"),array("A"=>"333","B"=>"444"),array("A"=>"555","B"=>"666")));
			eq($result,$template->execute($src));
		*/
		/***
		 	$src = text('
						<table rt:param="xyz" rt:null="true">
						<tr><td>{$o["B"]}</td></tr>
						</table>
					');
			$template = new Template();
			$template->vars("xyz",array());
			eq("",$template->execute($src));
		*/
		/***
		 	$src = text('
						<ul rt:param="xyz" rt:var="o">
							<li class="odd">{$o["B"]}</li>
						</ul>
					');
			$result = text('
							<ul>	<li class="odd">222</li>
								<li class="even">444</li>
								<li class="odd">666</li>
							</ul>
						');
			$template = new Template();
			$template->vars("xyz",array(array("A"=>"111","B"=>"222"),array("A"=>"333","B"=>"444"),array("A"=>"555","B"=>"666")));
			eq($result,$template->execute($src));
		*/
		/***
			# abc
		 	$src = text('
						<rt:loop param="abc" var="a">
						<ul rt:param="{$a}" rt:var="b">
						<li>
						<ul rt:param="{$b}" rt:var="c">
						<li>{$c}<rt:loop param="xyz" var="z">{$z}</rt:loop></li>
						</ul>
						</li>
						</ul>
						</rt:loop>
					');
			$result = text('
							<ul><li>
							<ul><li>A12</li>
							<li>B12</li>
							</ul>
							</li>
							</ul>
							<ul><li>
							<ul><li>C12</li>
							<li>D12</li>
							</ul>
							</li>
							</ul>

						');
			$template = new Template();
			$template->vars("abc",array(array(array("A","B")),array(array("C","D"))));
			$template->vars("xyz",array(1,2));
			eq($result,$template->execute($src));
		*/
		/***
			# range
		 	$src = text('<ul rt:range="1,3" rt:var="o"><li>{$o}</li></ul>');
			$result = text('<ul><li>1</li><li>2</li><li>3</li></ul>');
			$template = new Template();
			eq($result,$template->execute($src));
		*/
		/***
			# nest_table
			$src = text('<table rt:param="object_list" rt:var="obj"><tr><td><table rt:param="obj" rt:var="o"><tr><td>{$o}</td></tr></table></td></tr></table>');
			$template = new Template();
			$template->vars("object_list",array(array("A1","A2","A3"),array("B1","B2","B3")));
			eq('<table><tr><td><table><tr><td>A1</td></tr><tr><td>A2</td></tr><tr><td>A3</td></tr></table></td></tr><tr><td><table><tr><td>B1</td></tr><tr><td>B2</td></tr><tr><td>B3</td></tr></table></td></tr></table>',$template->execute($src));
		*/
		/***
			# nest_ul
			$src = text('<ul rt:param="object_list" rt:var="obj"><li><ul rt:param="obj" rt:var="o"><li>{$o}</li></ul></li></ul>');
			$template = new Template();
			$template->vars("object_list",array(array("A1","A2","A3"),array("B1","B2","B3")));
			eq('<ul><li><ul><li>A1</li><li>A2</li><li>A3</li></ul></li><li><ul><li>B1</li><li>B2</li><li>B3</li></ul></li></ul>',$template->execute($src));
		*/
		/***
			# nest_ol
			$src = text('<ol rt:param="object_list" rt:var="obj"><li><ol rt:param="obj" rt:var="o"><li>{$o}</li></ol></li></ol>');
			$template = new Template();
			$template->vars("object_list",array(array("A1","A2","A3"),array("B1","B2","B3")));
			eq('<ol><li><ol><li>A1</li><li>A2</li><li>A3</li></ol></li><li><ol><li>B1</li><li>B2</li><li>B3</li></ol></li></ol>',$template->execute($src));
		*/
		/***
			# nest_olul
			$src = text('<ol rt:param="object_list" rt:var="obj"><li><ul rt:param="obj" rt:var="o"><li>{$o}</li></ul></li></ol>');
			$template = new Template();
			$template->vars("object_list",array(array("A1","A2","A3"),array("B1","B2","B3")));
			eq('<ol><li><ul><li>A1</li><li>A2</li><li>A3</li></ul></li><li><ul><li>B1</li><li>B2</li><li>B3</li></ul></li></ol>',$template->execute($src));
		*/
		/***
			# nest_tableul
			$src = text('<table rt:param="object_list" rt:var="obj"><tr><td><ul rt:param="obj" rt:var="o"><li>{$o}</li></ul></td></tr></table>');
			$template = new Template();
			$template->vars("object_list",array(array("A1","A2","A3"),array("B1","B2","B3")));
			eq('<table><tr><td><ul><li>A1</li><li>A2</li><li>A3</li></ul></td></tr><tr><td><ul><li>B1</li><li>B2</li><li>B3</li></ul></td></tr></table>',$template->execute($src));
		*/
	}
	final private function table_tr_even_odd($src,$name,$even_odd){
		$tag = Tag::anyhow($src);
		foreach($tag->in($name) as $tr){
			$class = ' '.$tr->in_param('class').' ';
			if(preg_match('/[\s](even|odd)[\s]/',$class,$match)){
				$tr->param('class',trim(str_replace($match[0],' {$'.$even_odd.'} ',$class)));
				$src = str_replace($tr->plain(),$tr->get(),$src);				
			}
		}
		return $src;
	}
	final private function form_variable_name($name){
		return (strpos($name,'[') && preg_match("/^(.+)\[([^\"\']+)\]$/",$name,$match)) ?
			'{$'.$match[1].'["'.$match[2].'"]'.'}' : '{$'.$name.'}';
	}
	final private function is_reference(&$tag){
		$bool = ($tag->in_param('rt:ref') === 'true');
		$tag->rm_param('rt:ref');
		return $bool;
	}
	private function exec($src){
		/**
		 * 実行前処理
		 * @param string $src
		 * @param self $this
		 */
		$this->call_module('before_exec_template',$src,$this);
		$this->vars('_t_',new Templf());
		$__template_eval_src__ = $src;
		ob_start();
			if(is_array($this->vars) && !empty($this->vars)) extract($this->vars);
			eval('?>'.$__template_eval_src__);
			unset($__template_eval_src__);
		$src = ob_get_clean();
		/**
		 * 実行後処理
		 * @param string $src
		 * @param self $this
		 */
		$this->call_module('after_exec_template',$src,$this);
		return $src;
	}
	/***
		# under_var
		$src = text('{$_hoge}');
		$template = new self();
		$template->vars("_hoge","hogehoge");
		eq('hogehoge',$template->execute($src));
	*/	
}