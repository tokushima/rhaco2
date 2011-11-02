<?php
/**
 * gettext
 * @author tokushima
 */
class Gettext{
	static private $lang;
	static private $messages = array();
	static private $messages_path = array();
	static private $message_head = array();
	private $search_messages = array();

	/**
	 * 対象文字列を検索する
	 * @param string $path 検索対象のパス
	 * @param string $base 基点となるパス、コメントで使用する
	 * @return $this
	 */
	public function search($path,$base=null){
		$path = str_replace("\\",'/',$path);
		if(is_dir($path) && ($handle = opendir($path))){
			if(empty($base)) $base = $path;
			if(substr($base,-1) != '/') $base .= '/';
			while($pointer = readdir($handle)){
				if($pointer != '.' && $pointer != '..' && $pointer[0] != '.'){
					$filename = sprintf("%s/%s",$path,$pointer);
					if(is_file($filename)){
						if(sprintf('%u',@filesize($filename)) < (1024 * 1024)){
							$src = str_replace(array("\\\\","\\\"","\\'"),array('__ESC_DESC__','__ESC_DQ__','__ESC_SQ__'),File::read($filename));
							foreach(explode("\n",$src) as $line => $value){
								if(preg_match_all("/trans\(([\"\'])(.+?)\\1([^\)\s]*)/",$value,$match)){
									foreach($match[2] as $k => $msg){
										$msg = str_replace(array('__ESC_DESC__','__ESC_DQ__','__ESC_SQ__'),array("\\","\"","'"),$msg);
										$this->add($msg,str_replace($base,"",$filename),($line + 1),!empty($match[3][$k]));
									}
								}
							}
						}
					}else if(is_dir($filename)){
						$this->search($filename,$base);
					}
				}
			}
			closedir($handle);
		}
		return $this;
	}
	/**
	 * メッセージを追加する
	 * @param string $msg メッセージ
	 * @param string $filename メッセージを含むファイルパス
	 * @param integer $line メッセージを含む行番号
	 * @param boolean $plural 複数形か
	 */
	public function add($msg,$filename,$line=0,$plural=false){
		$this->search_messages[$msg]['#: '.$filename.(($line > 0) ? (':'.$line) : '')] = $plural;
		return $this;
	}
	/**
	 * 検索されたメッセージ配列を返す
	 * @return array
	 */
	public function messages(){
		return $this->search_messages;
	}
	/**
	 * LANGを設定、取得する
	 * @param string $lang 言語コード
	 * @return string
	 */
	static public function lang($lang=null){
		if(!empty($lang)){
			self::$lang = $lang;
			self::$messages = array();
			self::$message_head = array();
			foreach(self::$messages_path as $dir_name => $null) self::set($dir_name);
		}
		return self::$lang;
	}
	/**
	 * 国際化メッセージを設定する
	 * @param string $dir_name メッセージファイルのあるフォルダ
	 */
	static public function set($dir_name){
		if(is_dir($dir_name)){
			self::$messages_path[$dir_name] = true;
			$dir_name = str_replace("\\","/",$dir_name);
			if(substr($dir_name,-1) != '/') $dir_name .= '/';
			
			$mo_filename = $dir_name.'messages-'.self::$lang.'.mo';
			if(!is_file($mo_filename)) return;
			$bin = file_get_contents($mo_filename);
			$values = array();
			$head_no = sizeof(self::$message_head) + 1;
			self::$message_head[$head_no] = null;
	
			list(,$magick) = unpack('L',substr($bin,0,4));
			list(,$count) = unpack('l',substr($bin,8,4));
			list(,$id_length) = unpack('l',substr($bin,16,4));
	
			for($i=0,$y=28,$z=$id_length;$i<$count;$i++,$y+=8,$z+=8){
				list(,$key_len) = unpack('l',substr($bin,$y,4));
				list(,$key_offset) = unpack('l',substr($bin,$y+4,4));
	
				list(,$value_len) = unpack('l',substr($bin,$z,4));
				list(,$value_offset) = unpack('l',substr($bin,$z+4,4));
	
				$key = substr($bin,$key_offset,$key_len);
				if($key === ''){
					$header = explode("\n",substr($bin,$value_offset,$value_len));
					foreach($header as $head){
						list($name,$value) = explode(':',$head,2);
						if(strtolower(trim($name)) === 'plural-forms'){
							self::$message_head[$head_no] = str_replace("n","\$n",preg_replace("/^.*plural[\s]*=(.*)[;]*$/","\\1",$value));
							break;
						}
					}
				}else{
					$values[$key][0] = $head_no;
					$values[$key][1] = explode("\0",substr($bin,$value_offset,$value_len));
				}
			}
			foreach($values as $key => $value){
				if(!isset(self::$messages[$key])) self::$messages[$key] = $value;
			}
		}
	}
	/**
	 * 対象のパスを検索し、poファイルを書き出す
	 * @param string $path 検索対象のパス
	 * @param string $output_path poファイルのパス
	 */
	static public function po($path,$output_path){
		$self = new self();
		$self->search($path);
		for($i=2;$i<func_num_args();$i++){
			$arg = func_get_arg($i);
			if($arg instanceof self) $arg = $arg->messages();
		}
		$self->write($output_path);
	}
	/**
	 * poからmoを生成する
	 * @param stirng $po_filename
	 * @param string $mo_filename
	 */
	static public function mo($po_filename,$mo_filename=null){
		if(!is_file($po_filename)) throw new InvalidArgumentException($po_filename.": no such file");		
		$output_path = empty($mo_filename) ? preg_replace("/^(.+\.)po$/","\\1mo",$po_filename) : $mo_filename;
		$read_po_list = self::read($po_filename);
		$po_list = array();
		foreach($read_po_list as $id => $values){
			$c = array_flip(array_values($values));
			if(!(sizeof($c) <= 1 && key($c) === "")){
				$po_list[$id] = $values;
			}
		}
		$count = sizeof($po_list);
		$ids = implode("\0",array_keys($po_list))."\0";
		$keyoffset = 28 + 16 * $count;
		$valueoffset = $keyoffset + strlen($ids);
		$value_src = "";

		$output_src = pack('Lllllll',0x950412de,0,$count,28,(28 + ($count * 8)),0,0);
		$output_values = array();
		foreach($po_list as $id => $values){
			$len = strlen($id);
			$output_src .= pack("l",$len);
			$output_src .= pack("l",$keyoffset);
			$keyoffset += $len + 1;

			$value = implode("\0",$values);
			$len = strlen($value);
			$value_src .= pack("l",$len);
			$value_src .= pack("l",$valueoffset);
			$valueoffset += $len + 1;

			$output_values[] = $value;
		}
		$output_src .= $value_src;
		$output_src .= $ids;
		$output_src .= implode("\0",$output_values)."\0";
		if(!is_dir(dirname($output_path))) mkdir(dirname($output_path),0744,true);
		file_put_contents($output_path,$output_src,LOCK_EX);
		return $output_path;
	}
	/**
	 * poファイルとして書き出す
	 * @param string $output_path
	 * @return string 書き出したファイルパス
	 */
	public function write($output_path){
		$read_messages = is_file($output_path) ? self::read($output_path) : array();		
		ksort($this->search_messages,SORT_STRING);
		$output_src = sprintf(implode("\n",array(
						'# SOME DESCRIPTIVE TITLE.'
						,'msgid ""'
						,'msgstr ""'
						,'"Project-Id-Version: PACKAGE VERSION\n"'
						,'"Report-Msgid-Bugs-To: \n"'
						,'"POT-Creation-Date: %s\n"'
						,'"PO-Revision-Date: YEAR-MO-DA HO:MI+ZONE\n"'
						,'"Last-Translator: FULL NAME <EMAIL@ADDRESS>\n"'
						,'"Language-Team: LANGUAGE <team@exsample.com>\n"'
						,'"Plural-Forms: nplurals=1; plural=0;\n"'))
				,date("Y-m-d H:iO"))."\n\n";
		foreach($this->search_messages as $str => $lines){
			$output_src .= "\n".implode("\n",array_keys($lines))."\n";
			$output_src .= "msgid \"".str_replace(array("\\","\""),array("\\\\","\\\""),$str)."\"\n";
			$msg = isset($read_messages[$str]) ? $read_messages[$str] : array(null);
			
			if(sizeof($msg) > 1){
				foreach($msg as $k => $m) $output_src .= "msgstr[".$k."] \"".str_replace(array("\\","\""),array("\\\\","\\\""),$m)."\"\n";
			}else{
				foreach($msg as $m) $output_src .= "msgstr \"".str_replace(array("\\","\""),array("\\\\","\\\""),$m)."\"\n";
			}
		}
		if(!is_dir(dirname($output_path))) mkdir(dirname($output_path),0744,true);
		file_put_contents($output_path,$output_src,LOCK_EX);
		return $output_path;
	}
	/**
	 * poからメッセージ配列を取得
	 * @param $po_filename
	 * @return array
	 */
	static public function read($po_filename){
		$po_list = array();
		$msgId = "";
		$isId = false;
		$plural_no = 0;

		$src = str_replace(array("\\\\","\\\"","\\'"),array('__ESC_DESC__','__ESC_DQ__','__ESC_SQ__'),File::read($po_filename));
		foreach(explode("\n",$src) as $line){
			if(!preg_match("/^[\s]*#/",$line)){
				if(preg_match("/msgid_plural[\s]+([\"\'])(.+)\\1/",$line,$match)){
					$msgId = self::unescape($match[2]);
					$isId = true;
					$plural_no = 0;
				}else if(preg_match("/msgid[\s]+([\"\'])(.*?)\\1/",$line,$match)){
					$msgId = self::unescape($match[2]);
					$isId = true;
					$plural_no = 0;
				}else if(preg_match("/msgstr\[(\d+)\][\s]+([\"\'])(.*?)\\2/",$line,$match)){
					$plural_no = (int)$match[1];
					$po_list[$msgId][$plural_no] = self::unescape($match[3]);
					$isId = false;
					ksort($po_list[$msgId]);
				}else if(preg_match("/msgstr[\s]+([\"\'])(.*?)\\1/",$line,$match)){
					$po_list[$msgId][$plural_no] = self::unescape($match[2]);
					$isId = false;
				}else if(preg_match("/([\"\'])(.+)\\1/",$line,$match)){
					if($isId){
						$msgId .= self::unescape($match[2]);
					}else{
						if(!isset($po_list[$msgId][$plural_no])) $po_list[$msgId][$plural_no] = '';
						$po_list[$msgId][$plural_no] .= self::unescape($match[2]);
					}
				}
			}
		}
		ksort($po_list,SORT_STRING);
		return $po_list;
	}
	static private function unescape($src){
		return str_replace(array('__ESC_DQ__','__ESC_SQ__','__ESC_DESC__',"\\n"),array("\"","'","\\","\n"),$src);
	}
	/**
	 * 国際化文字列を返す
	 * @param string $key 国際化する文字列
	 * @return string
	 */
	static public function trans($key){
		$args = func_get_args();
		$argsize = func_num_args();
		$key = array_shift($args);
		$message = $key;

		if(isset(self::$messages[$key])){
			$message = self::$messages[$key][1][0];
			if(!empty($args) && sizeof(self::$messages[$key][1]) > 1){
				$plural_param = (int)array_shift($args);
				if(isset(self::$message_head[self::$messages[$key][0]])){
					$n = $plural_param;
					$message = self::$messages[$key][1][(int)self::$message_head[self::$messages[$key][0]]];
				}
			}
		}
		if(strpos($message,'{') !== false && preg_match_all("/\{([\d]+)\}/",$message,$match)){
			$args = array_map(array(__CLASS__,'trans'),$args);
			foreach($match[1] as $k => $v){
				$i = ((int)$v) - 1;
				$message = str_replace($match[0][$k],isset($args[$i]) ? $args[$i] : '',$message);
			}
		}
		return $message;
		/***
			eq("hoge",self::trans("hoge"));
		 */
	}	
}