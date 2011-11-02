<?php
/**
 * ファイル処理
 * @author tokushima
 * @var integer $error エラーコード
 * @var string $directory フォルダパス
 * @var string $fullname ファイルパス
 * @var string $name ファイル名
 * @var string $oname 拡張子がつかないファイル名
 * @var string $ext 拡張子
 * @var string $mime ファイルのコンテントタイプ
 * @var string $tmp 一時ファイルパス
 * @var text $value 内容
 */
class File extends Object{
	protected $fullname;
	protected $value;
	protected $mime;

	protected $tmp;
	protected $error;

	protected $directory;
	protected $name;
	protected $oname;
	protected $ext;

	static private $dir_permission = 0755;
	static private $file_permission = 0644;
	static private $lock = true;

	/**
	 * デフォルトの権限のを定義する
	 * @param integer $file_permission モード　8進数(0644)
	 * @param integer $dir_permission モード　8進数(0644)
	 */
	final static public function config_permission($file_permission,$dir_permission=null){
		if($file_permission !== null) self::$file_permission = $file_permission;
		if($dir_permission !== null) self::$dir_permission = $dir_permission;
	}
	/**
	 * ロックの動作を定義する
	 * @param boolean $boolean nfs等ネットワークドライブでロックが出来ない場合にfalseにする
	 */
	final static public function config_lock($boolean){
		self::$lock = (boolean)$boolean;
	}
	final protected function __new__($fullname=null,$value=null){
		$this->fullname	= str_replace("\\",'/',$fullname);
		$this->value = $value;
		$this->parse_fullname();
	}
	final protected function __cp__($dest,$file_permission=null,$dir_permission=null){
		return self::copy($this,$dest,$file_permission,$dir_permission);
	}
	final protected function __str__(){
		return $this->fullname;
	}
	final protected function __is_ext__($ext){
		return ('.'.strtolower($ext) === strtolower($this->ext()));
	}
	final protected function __is_fullname__(){
		return is_file($this->fullname);
	}
	final protected function __is_tmp__(){
		return is_file($this->tmp);
	}
	final protected function __is_error__(){
		return (intval($this->error) > 0);
	}
	final protected function __set_value__($value){
		$this->value = $value;
		$this->size = sizeof($value);
	}
	/**
	 * 一時ファイルから移動する
	 * HTMLでのファイル添付の場合に使用
	 * @param string $filename ファイルパス
	 * @param integer $file_permission モード　8進数(0644)
	 * @param integer $dir_permission モード　8進数(0644)
	 * @return $this
	 */
	public function generate($filename,$file_permission=null,$dir_permission=null){
		if(self::copy($this->tmp,$filename,$file_permission,$dir_permission)){
			if(unlink($this->tmp)){
				$this->fullname = $filename;
				$this->parse_fullname();
				return $this;
			}
		}
		throw new InvalidArgumentException(sprintf('permission denied `%s`',$filename));
	}
	/**
	 * 標準出力に出力する
	 */
	public function output(){
		if(empty($this->value) && @is_file($this->fullname)){
			$fp = fopen($this->fullname,'rb');
			while(!feof($fp)){
				echo(fread($fp,8192));
				flush();
			}
			fclose($fp);
		}else{
			print($this->value);
		}
		exit;
	}
	/**
	 * 内容を取得する
	 * @return string
	 */
	public function get(){
		if($this->value !== null) return $this->value;
		if(is_file($this->fullname)) return file_get_contents($this->fullname);
		if(is_file($this->tmp)) return file_get_contents($this->tmp);
		throw new InvalidArgumentException(sprintf('permission denied `%s`',$this->fullname));
	}
	public function update(){
		return (@is_file($this->fullname)) ? @filemtime($this->fullname) : time();
	}
	public function size(){
		return (@is_file($this->fullname)) ? @filesize($this->fullname) : strlen($this->value);
	}
	private function parse_fullname(){
		$fullname = str_replace("\\",'/',$this->fullname);
		if(preg_match("/^(.+[\/]){0,1}([^\/]+)$/",$fullname,$match)){
			$this->directory = empty($match[1]) ? "./" : $match[1];
			$this->name = $match[2];
		}
		if(false !== ($p = strrpos($this->name,'.'))){
			$this->ext = '.'.substr($this->name,$p+1);
			$filename = substr($this->name,0,$p);
		}
		$this->oname = @basename($this->name,$this->ext);

		if(empty($this->mime)){
			$ext = strtolower(substr($this->ext,1));
			switch($ext){
				case 'jpg':
				case 'jpeg': $ext = 'jpeg';
				case 'png':
				case 'gif':
				case 'bmp':
				case 'tiff': $this->mime = 'image/'.$ext; break;
				case 'css': $this->mime = 'text/css'; break;
				case 'txt': $this->mime = 'text/plain'; break;
				case 'html': $this->mime = 'text/html'; break;
				case 'xml': $this->mime = 'application/xml'; break;
				case 'js': $this->mime = 'text/javascript'; break;
				case 'flv':
				case 'swf': $this->mime = 'application/x-shockwave-flash'; break;
				case '3gp': $this->mime = 'video/3gpp'; break;
				case 'gz':
				case 'tgz':
				case 'tar':
				case 'gz':  $this->mime = 'application/x-compress'; break;
				default:
					/**
					 * MIMEタイプを設定する
					 * @param self $this
					 * @return string mime-type
					 */
					$this->mime = (string)(Object::C(__CLASS__)->call_module('parse_mime_type',$this));
					if(empty($this->mime)) $this->mime = 'application/octet-stream';
			}
		}
	}
	/**
	 * クラスファイルか
	 * @return boolean
	 */
	final public function is_class(){
		return (!empty($this->oname) && $this->is_ext('php') && ctype_upper($this->oname[0]));
	}
	/**
	 * 不過視ファイルか
	 * @return boolean
	 */
	final public function is_invisible(){
		return (!empty($this->oname) && ($this->oname[0] == '.' || strpos($this->fullname,'/.') !== false));
	}
	/**
	 * privateファイルか
	 * @return boolean
	 */
	final public function is_private(){
		return (!empty($this->oname) && $this->oname[0] == '_');
	}

	
	/**
	 * ファイルパスを生成する
	 * @param string $base ベースとなるファイルパス
	 * @param string $path ファイルパス
	 * @return string
	 */
	static public function path($base,$path=''){
		/***
		 * eq("/abc/def/hig.php",File::path("/abc/def","hig.php"));
		 * eq("/xyz/abc/hig.php",File::path("/xyz/","/abc/hig.php"));
		 */
		if(!empty($path)){
			$path = self::parse_filename($path);
			if(preg_match("/^[\/]/",$path,$null)) $path = substr($path,1);
		}
		return self::absolute(self::parse_filename($base),self::parse_filename($path));
	}
	/**
	 * フォルダを作成する
	 * @param string $source 作成するフォルダパス
	 * @param integer $dir_permission モード　8進数(0644)
	 */
	static public function mkdir($source,$dir_permission=null){
		if(!is_dir($source)){
			try{
				mkdir($source,(($dir_permission === null) ? self::$dir_permission : $dir_permission),true);
			}catch(ErrorException $e){
				throw new InvalidArgumentException(sprintf('permission denied `%s`',$source));
			}
		}
	}
	/**
	 * ファイル、またはフォルダが存在しているか
	 * @param string $filename ファイルパス
	 * @return boolean
	 */
	static public function exist($filename){
		return (is_readable($filename) && (is_file($filename) || is_dir($filename) || is_link($filename)));
	}
	/**
	 * 移動
	 * @param string $source 移動もとのファイルパス
	 * @param string $dest 移動後のファイルパス
	 * @param integer $dir_permission モード　8進数(0644)
	 * @return boolean 移動に成功すればtrue
	 */
	static public function mv($source,$dest,$dir_permission=null){
		$source = self::parse_filename($source);
		$dest = self::parse_filename($dest);
		if(self::exist($source)){
			self::mkdir(dirname($dest),$dir_permission);
			return rename($source,$dest);
		}
		throw new InvalidArgumentException(sprintf('permission denied `%s`',$source));
	}
	/**
	 * 最終更新時間を取得
	 * @param string $filename ファイルパス
	 * @param boolean $clearstatcache ファイルのステータスのキャッシュをクリアするか
	 * @return integer
	 */
	static public function last_update($filename,$clearstatcache=false){
		if($clearstatcache) clearstatcache();
		if(is_dir($filename)){
			$last_update = null;
			foreach(File::ls($filename,true) as $file){
				if($last_update < $file->update()) $last_update = $file->update();
			}
			return $last_update;
		}
		return (is_readable($filename) && is_file($filename)) ? filemtime($filename) : null;
	}
	/**
	 * 削除
	 * $sourceがフォルダで$inc_selfがfalseの場合は$sourceフォルダ以下のみ削除
	 * @param string $source 削除するパス
	 * @param boolean $inc_self $sourceも削除するか
	 * @return boolean
	 */
	static public function rm($source,$inc_self=true){
		if($source instanceof self) $source = $source->fullname();
		$source	= self::parse_filename($source);

		if(!$inc_self){
			foreach(self::dir($source) as $d) self::rm($d);
			foreach(self::ls($source) as $f) self::rm($f);
			return true;
		}
		if(!self::exist($source)) return true;
		if(is_writable($source)){
			if(is_dir($source)){
				if($handle = opendir($source)){
					$list = array();
					while($pointer = readdir($handle)){
						if($pointer != '.' && $pointer != '..'){
							$list[] = sprintf('%s/%s',$source,$pointer);
						}
					}
					closedir($handle);
					foreach($list as $path){
						if(!self::rm($path)) return false;
					}
				}
				if(rmdir($source)){
					clearstatcache();
					return true;
				}
			}else if(is_file($source) && unlink($source)){
				clearstatcache();
				return true;
			}
		}
		throw new InvalidArgumentException(sprintf('permission denied `%s`',$source));
	}
	/**
	 * コピー
	 * $sourceがフォルダの場合はそれ以下もコピーする
	 * @param string $source コピー元のファイルパス
	 * @param string $dest コピー先のファイルパス
	 * @param integer $file_permission モード　8進数(0644)
	 * @param integer $dir_permission モード　8進数(0644)
	 * @return boolean 成功時true
	 */
	static public function copy($source,$dest,$file_permission=null,$dir_permission=null){
		$source	= self::parse_filename($source);
		$dest = self::parse_filename($dest);
		$dir = (preg_match("/^(.+)\/[^\/]+$/",$dest,$tmp)) ? $tmp[1] : $dest;

		if(!self::exist($source)) throw new InvalidArgumentException(sprintf('permission denied `%s`',$source));
		self::mkdir($dir,$dir_permission);
		if(is_dir($source)){
			$bool = true;
			if($handle = opendir($source)){
				while($pointer = readdir($handle)){
					if($pointer != '.' && $pointer != '..'){
						$srcname = sprintf('%s/%s',$source,$pointer);
						$destname = sprintf('%s/%s',$dest,$pointer);
						if(false === ($bool = self::copy($srcname,$destname,$file_permission,$dir_permission))) break;
					}
				}
				closedir($handle);
			}
			return $bool;
		}else{
			$filename = (preg_match("/^.+(\/[^\/]+)$/",$source,$tmp)) ? $tmp[1] : '';
			$dest = (is_dir($dest))	? $dest.$filename : $dest;
			if(is_writable(dirname($dest))){
				copy($source,$dest);
				chmod($dest,(($file_permission === null) ? self::$file_permission : $file_permission));
			}
			return self::exist($dest);
		}
	}
	/**
	 * ファイルから取得する
	 * @param string $filename ファイルパス
	 * @return string
	 */
	static public function read($filename){
		if($filename instanceof self) $filename = ($filename->is_fullname()) ? $filename->fullname() : $filename->tmp();
		if(!is_readable($filename) || !is_file($filename)) throw new InvalidArgumentException(sprintf('permission denied `%s`',$filename));
		return file_get_contents($filename);
	}
	/**
	 * ファイルから行分割して配列で返す
	 *
	 * @param string $filename ファイルパス
	 * @return string
	 */
	static public function lines($filename){
		return explode("\n",str_replace(array("\r\n","\r"),"\n",self::read($filename)));
	}
	/**
	 * ファイルに書き出す
	 * @param string $filename ファイルパス
	 * @param string $src 内容
	 * @param integer $file_permission モード　8進数(0644)
	 * @param integer $dir_permission モード　8進数(0644)
	 */
	static public function write($filename,$src=null,$file_permission=null,$dir_permission=null){
		if($filename instanceof self) $filename = $filename->fullname;
		if(empty($filename)) throw new InvalidArgumentException(sprintf('permission denied `%s`',$filename));
		self::mkdir(dirname($filename),$dir_permission);
		if(false === file_put_contents($filename,Text::str($src),((self::$lock) ? LOCK_EX : 0))) throw new InvalidArgumentException(sprintf('permission denied `%s`',$filename));
		chmod($filename,(($file_permission === null) ? self::$file_permission : $file_permission));
	}
	/**
	 * ファイルに追記する
	 * @param string $filename ファイルパス
	 * @param string $src 追加する内容
	 * @param integer $dir_permission モード　8進数(0644)
	 */
	static public function append($filename,$src=null,$dir_permission=null){
		if($filename instanceof self) $filename = $filename->fullname;
		self::mkdir(dirname($filename),$dir_permission);
		if(false === file_put_contents($filename,Text::str($src),FILE_APPEND|((self::$lock) ? LOCK_EX : 0))) throw new InvalidArgumentException(sprintf('permission denied `%s`',$filename));
	}
	/**
	 * ファイルから取得する
	 * @param string $filename ファイルパス
	 * @return string
	 */
	static public function gzread($filename){
		if($filename instanceof self) $filename = ($filename->is_fullname()) ? $filename->fullname() : $filename->tmp();
		if(strpos($filename,'://') === false && (!is_readable($filename) || !is_file($filename))) throw new InvalidArgumentException(sprintf('permission denied `%s`',$filename));
		try{
			$fp = gzopen($filename,'rb');
			$buf = null;
			while(!gzeof($fp)) $buf .= gzread($fp,4096);
			gzclose($fp);
			return $buf;
		}catch(Exception $e){
			throw new InvalidArgumentException(sprintf('permission denied `%s`',$filename));
		}
	}
	/**
	 * gz圧縮でファイルに書き出す
	 * @param string $filename ファイルパス
	 * @param string $src 内容
	 * @param integer $file_permission モード　8進数(0644)
	 * @param integer $dir_permission モード　8進数(0644)
	 */
	static public function gzwrite($filename,$src,$file_permission=null,$dir_permission=null){
		if($filename instanceof self) $filename = $filename->fullname;
		self::mkdir(dirname($filename),$dir_permission);
		try{
			$fp = gzopen($filename,'wb9');
			gzwrite($fp,$src);
			gzclose($fp);
			chmod($filename,(($file_permission === null) ? self::$file_permission : $file_permission));
		}catch(Exception $e){
			throw new InvalidArgumentException(sprintf('permission denied `%s`',$filename));
		}
	}
	/**
	 * ファイル、またはディレクトリからtar圧縮のデータを作成する
	 * @param string $path 圧縮するファイルパス
	 * @param string $base_dir tarのヘッダ情報をこのファイルパスを除く相対パスとして作成する
	 * @param string $ignore_pattern 除外パターン
	 * @param boolean $endpoint エンドポイントとするか
	 * @return string
	 */
	static public function tar($path,$base_dir=null,$ignore_pattern=null,$endpoint=true){
		$result = null;
		$files = array();
		$path = self::parse_filename($path);
		$base_dir = self::parse_filename(empty($base_dir) ? (is_dir($path) ? $path : dirname($path)) : $base_dir);
		$ignore = (!empty($ignore_pattern));
		if(substr($base_dir,0,-1) != '/') $base_dir .= '/';
		$filepath = self::absolute($base_dir,$path);

		if(is_dir($filepath)){
			foreach(self::dir($filepath,true) as $dir) $files[$dir] = 5;
			foreach(self::ls($filepath,true) as $file) $files[$file->fullname()] = 0;
		}else{
			$files[$filepath] = 0;
		}
		foreach($files as $filename => $type){
			$target_filename = str_replace($base_dir,'',$filename);
			$bool = true;
			if($ignore){
				$ignore_pattern = (is_array($ignore_pattern)) ? $ignore_pattern : array($ignore_pattern);
				foreach($ignore_pattern as $p){
					if(preg_match('/'.str_replace(array("\/",'/','__SLASH__'),array('__SLASH__',"\/","\/"),$p).'/',$target_filename)){
						$bool = false;
						break;
					}
				}
			}
			if(!$ignore || $bool){
				switch($type){
					case 0:
						$info = stat($filename);
						$rp = fopen($filename,'rb');
							$result .= self::tar_head($type,$target_filename,filesize($filename),fileperms($filename),$info[4],$info[5],filemtime($filename));
							while(!feof($rp)){
								$buf = fread($rp,512);
								if($buf !== '') $result .= pack('a512',$buf);
							}
						fclose($rp);
						break;
					case 5:
						$result .= self::tar_head($type,$target_filename);
						break;
				}
			}
		}
		if($endpoint) $result .= pack("a1024",null);
		return $result;
	}
	static private function tar_head($type,$filename,$filesize=0,$fileperms=0777,$uid=0,$gid=0,$update_date=null){
		if(strlen($filename) > 99) throw new InvalidArgumentException('invalid filename (max length 100) `'.$filename.'`');
		if($update_date === null) $update_date = time();
		$checksum = 256;
		$first = pack('a100a8a8a8a12A12',$filename,
						sprintf('%06s ',decoct($fileperms)),sprintf('%06s ',decoct($uid)),sprintf('%06s ',decoct($gid)),
						sprintf('%011s ',decoct(($type === 0) ? $filesize : 0)),sprintf('%11s',decoct($update_date)));
		$last = pack('a1a100a6a2a32a32a8a8a155a12',$type,null,null,null,null,null,null,null,null,null);
		for($i=0;$i<strlen($first);$i++) $checksum += ord($first[$i]);
		for($i=0;$i<strlen($last);$i++) $checksum += ord($last[$i]);
		return $first.pack('a8',sprintf('%6s ',decoct($checksum))).$last;
	}
	/**
	 * tarを解凍する
	 * @param string $src tar文字列
	 * @param string $outpath 展開先のファイルパス
	 * @param integer $file_permission モード　8進数(0644)
	 * @param integer $dir_permission モード　8進数(0644)
	 * @return string{} 展開されたファイル情報
	 */
	static public function untar($src,$outpath=null,$file_permission=null,$dir_permission=null){
		$result = array();
		$isout = !empty($outpath);
		for($pos=0,$vsize=0,$cur='';;){
			$buf = substr($src,$pos,512);
			if(strlen($buf) < 512) break;
			$data = unpack('a100name/a8mode/a8uid/a8gid/a12size/a12mtime/'
							.'a8chksum/'
							.'a1typeflg/a100linkname/a6magic/a2version/a32uname/a32gname/a8devmajor/a8devminor/a155prefix',
							 $buf);
			$pos += 512;
			if(!empty($data['name'])){
				$obj = new stdClass();
				$obj->type = (int)$data['typeflg'];
				$obj->path = $data['name'];
				$obj->update = base_convert($data['mtime'],8,10);

				switch($obj->type){
					case 0:
						$obj->size = base_convert($data['size'],8,10);
						$obj->content = substr($src,$pos,$obj->size);
						$pos += (ceil($obj->size / 512) * 512);
						if($isout){
							$p = self::absolute($outpath,$obj->path);
							self::write($p,$obj->content,$file_permission,$dir_permission);
							touch($p,$obj->update);
						}
						break;
					case 5:
						if($isout) self::mkdir(self::absolute($outpath,$obj->path),$dir_permission);
						break;
				}
				if(!$isout) $result[$obj->path] = $obj;
			}
		}
		return $result;
	}
	/**
	 * tar.gz(tgz)圧縮してファイル書き出しを行う
	 *
	 * @param string $tgz_filename
	 * @param string $path
	 * @param string $base_dir
	 * @param string $ignore_pattern
	 * @param integer $file_permission モード　8進数(0644)
	 * @param integer $dir_permission モード　8進数(0644)
	 */
	static public function tgz($tgz_filename,$path,$base_dir=null,$ignore_pattern=null,$file_permission=null,$dir_permission=null){
		self::gzwrite($tgz_filename,self::tar($path,$base_dir,$ignore_pattern),$file_permission,$dir_permission);
	}
	/**
	 * tar.gz(tgz)を解凍してファイル書き出しを行う
	 * @param string $inpath 解凍するファイルパス
	 * @param string $outpath 解凍先のファイルパス
	 * @param integer $file_permission モード　8進数(0644)
	 * @param integer $dir_permission モード　8進数(0644)
	 */
	static public function untgz($inpath,$outpath,$file_permission=null,$dir_permission=null){
		$tmp = false;
		if(strpos($inpath,'://') !== false && (boolean)ini_get('allow_url_fopen')){
			$tmpname = self::absolute($outpath,self::temp_path($outpath));
			$http = new Http();
			try{
				$http->do_download($inpath,$tmpname);
				if($http->status() !== 200) throw new InvalidArgumentException(sprintf('permission denied `%s`',$inpath));
			}catch(ErrorException $e){
				 throw new InvalidArgumentException(sprintf('permission denied `%s`',$tmpname));
			}
			$inpath = $tmpname;
			$tmp = true;
		}
		self::untar(self::gzread($inpath),$outpath,$file_permission,$dir_permission);
		if($tmp) self::rm($inpath);
	}
	static private function parse_filename($filename){
		$filename = preg_replace("/[\/]+/",'/',str_replace("\\",'/',trim($filename)));
		return (substr($filename,-1) == '/') ? substr($filename,0,-1) : $filename;
	}
	/**
	 * 絶対パスを取得
	 * @param string $baseUrl ベースとなるパス
	 * @param string $targetUrl 対象となる相対パス
	 * @return string
	 */
	static public function absolute($bu,$tu){
		/***
			eq("http://www.rhaco.org/doc/ja/index.html",File::absolute("http://www.rhaco.org/","/doc/ja/index.html"));
			eq("http://www.rhaco.org/doc/ja/index.html",File::absolute("http://www.rhaco.org/","../doc/ja/index.html"));
			eq("http://www.rhaco.org/doc/ja/index.html",File::absolute("http://www.rhaco.org/","./doc/ja/index.html"));
			eq("http://www.rhaco.org/doc/ja/index.html",File::absolute("http://www.rhaco.org/doc/ja/","./index.html"));
			eq("http://www.rhaco.org/doc/index.html",File::absolute("http://www.rhaco.org/doc/ja","./index.html"));
			eq("http://www.rhaco.org/doc/index.html",File::absolute("http://www.rhaco.org/doc/ja/","../index.html"));
			eq("http://www.rhaco.org/index.html",File::absolute("http://www.rhaco.org/doc/ja/","../../index.html"));
			eq("http://www.rhaco.org/index.html",File::absolute("http://www.rhaco.org/doc/ja/","../././.././index.html"));
			eq("/www.rhaco.org/doc/index.html",File::absolute("/www.rhaco.org/doc/ja/","../index.html"));
			eq("/www.rhaco.org/index.html",File::absolute("/www.rhaco.org/doc/ja/","../../index.html"));
			eq("/www.rhaco.org/index.html",File::absolute("/www.rhaco.org/doc/ja/","../././.././index.html"));
			eq("c:/www.rhaco.org/doc/index.html",File::absolute("c:/www.rhaco.org/doc/ja/","../index.html"));
			eq("http://www.rhaco.org/index.html",File::absolute("http://www.rhaco.org/doc/ja","/index.html"));

			eq("/www.rhaco.org/doc/ja/action.html/index.html",File::absolute('/www.rhaco.org/doc/ja/action.html', 'index.html'));
			eq("http://www.rhaco.org/doc/ja/index.html",File::absolute('http://www.rhaco.org/doc/ja/action.html', 'index.html'));
			eq("http://www.rhaco.org/doc/ja/sample.cgi?param=test",File::absolute('http://www.rhaco.org/doc/ja/sample.cgi?query=key', '?param=test'));
			eq("http://www.rhaco.org/doc/index.html",File::absolute('http://www.rhaco.org/doc/ja/action.html', '../../index.html'));
			eq("http://www.rhaco.org/?param=test",File::absolute('http://www.rhaco.org/doc/ja/sample.cgi?query=key', '../../../?param=test'));
			eq("/doc/ja/index.html",File::absolute('/',"/doc/ja/index.html"));
			eq("/index.html",File::absolute('/',"index.html"));
			eq("/abc/index.html",File::absolute('abc',"index.html"));
			eq("http://www.rhaco.org/login",File::absolute("http://www.rhaco.org","/login"));
			eq("http://www.rhaco.org/login",File::absolute("http://www.rhaco.org/login",""));
			eq("http://www.rhaco.org/login.cgi",File::absolute("http://www.rhaco.org/logout.cgi","login.cgi"));
			eq("http://www.rhaco.org/hoge/login.cgi",File::absolute("http://www.rhaco.org/hoge/logout.cgi","login.cgi"));
			eq("http://www.rhaco.org/hoge/login.cgi",File::absolute("http://www.rhaco.org/hoge/#abc/aa","login.cgi"));
			eq("http://www.rhaco.org/hoge/abc.html#login",File::absolute("http://www.rhaco.org/hoge/abc.html","#login"));
			eq("http://www.rhaco.org/hoge/abc.html#login",File::absolute("http://www.rhaco.org/hoge/abc.html#logout","#login"));
			eq("http://www.rhaco.org/hoge/abc.html?abc=aa#login",File::absolute("http://www.rhaco.org/hoge/abc.html?abc=aa#logout","#login"));
			eq("http://www.rhaco.org/hoge/abc.html",File::absolute("http://www.rhaco.org/hoge/abc.html","javascript::alert('')"));
			eq("http://www.rhaco.org/hoge/abc.html",File::absolute("http://www.rhaco.org/hoge/abc.html","mailto::hoge@rhaco.org"));
			eq("http://www.rhaco.org/hoge/login.cgi",File::absolute("http://www.rhaco.org/hoge/?aa=bb/","login.cgi"));
			eq("http://www.rhaco.org/login",File::absolute("http://rhaco.org/hoge/hoge","http://www.rhaco.org/login"));
			eq("http://localhost:8888/spec/css/style.css",File::absolute("http://localhost:8888/spec/","./css/style.css"));			
		 */
		$tu = str_replace("\\",'/',$tu);
		if(empty($tu)) return $bu;
		$bu = str_replace("\\",'/',$bu);
		if(preg_match("/^[\w]+\:\/\/[^\/]+/",$tu)) return $tu;
		$isnet = preg_match("/^[\w]+\:\/\/[^\/]+/",$bu,$basehost);
		$isroot = (substr($tu,0,1) == '/');
		if($isnet){
			if(strpos($tu,'javascript:') === 0 || strpos($tu,'mailto:') === 0) return $bu;
			$preg_cond = ($tu[0] === '#') ? '#' : "#\?";
			$bu = preg_replace("/^(.+?)[".$preg_cond."].*$/","\\1",$bu);
			if($tu[0] === '#' || $tu[0] === "?") return $bu.$tu;
			if(substr($bu,-1) !== '/'){
				if(substr($tu,0,2) === "./"){
					$tu = '.'.$tu;
				}else if($tu[0] !== '.' && $tu[0] !== '/'){
					$tu = "../".$tu;
				}
			}
		}
		if(empty($bu) || preg_match("/^[a-zA-Z]\:/",$tu) || (!$isnet && $isroot) || preg_match("/^[\w]+\:\/\/[^\/]+/",$tu)) return $tu;
		if($isnet && $isroot && isset($basehost[0])) return $basehost[0].$tu;

		$rlist = array(array('://','/./','//'),array('#REMOTEPATH#','/','/')
					,array("/^\/(.+)$/","/^(\w):\/(.+)$/"),array("#ROOT#\\1","\\1#WINPATH#\\2",'')
					,array('#REMOTEPATH#','#ROOT#','#WINPATH#'),array('://','/',':/'));
		$bu = preg_replace($rlist[2],$rlist[3],str_replace($rlist[0],$rlist[1],$bu));
		$tu = preg_replace($rlist[2],$rlist[3],str_replace($rlist[0],$rlist[1],$tu));
		$basedir = $targetdir = $rootpath = '';

		if(strpos($bu,'#REMOTEPATH#')){
			list($rootpath)	= explode('/',$bu);
			$bu = substr($bu,strlen($rootpath));
			$tu = str_replace('#ROOT#','',$tu);
		}
		$baseList = preg_split("/\//",$bu,-1,PREG_SPLIT_NO_EMPTY);
		$targetList = preg_split("/\//",$tu,-1,PREG_SPLIT_NO_EMPTY);

		for($i=0;$i<sizeof($baseList)-substr_count($tu,"../");$i++){
			if($baseList[$i] != '.' && $baseList[$i] != '..') $basedir .= $baseList[$i].'/';
		}
		for($i=0;$i<sizeof($targetList);$i++){
			if($targetList[$i] != '.' && $targetList[$i] != '..') $targetdir .= '/'.$targetList[$i];
		}
		$targetdir = (!empty($basedir)) ? substr($targetdir,1) : $targetdir;
		$basedir = (!empty($basedir) && substr($basedir,0,1) != '/' && substr($basedir,0,6) != '#ROOT#' && !strpos($basedir,'#WINPATH#')) ? '/'.$basedir : $basedir;
		return str_replace($rlist[4],$rlist[5],$rootpath.$basedir.$targetdir);
	}
	/**
	 * 相対パスを取得
	 * @param string $baseUrl ベースのファイルパス
	 * @param string $targetUrl ファイルパス
	 * @return string
	 */
	static public function relative($baseUrl,$targetUrl){
		/***
			eq("./overview.html",File::relative("http://www.rhaco.org/doc/ja/","http://www.rhaco.org/doc/ja/overview.html"));
			eq("../overview.html",File::relative("http://www.rhaco.org/doc/ja/","http://www.rhaco.org/doc/overview.html"));
			eq("../../overview.html",File::relative("http://www.rhaco.org/doc/ja/","http://www.rhaco.org/overview.html"));
			eq("../en/overview.html",File::relative("http://www.rhaco.org/doc/ja/","http://www.rhaco.org/doc/en/overview.html"));
			eq("./doc/ja/overview.html",File::relative("http://www.rhaco.org/","http://www.rhaco.org/doc/ja/overview.html"));
			eq("./ja/overview.html",File::relative("http://www.rhaco.org/doc/","http://www.rhaco.org/doc/ja/overview.html"));
			eq("http://www.goesby.com/user.php/rhaco",File::relative("http://www.rhaco.org/doc/ja/","http://www.goesby.com/user.php/rhaco"));
			eq("./doc/ja/overview.html",File::relative("/www.rhaco.org/","/www.rhaco.org/doc/ja/overview.html"));
			eq("./ja/overview.html",File::relative("/www.rhaco.org/doc/","/www.rhaco.org/doc/ja/overview.html"));
			eq("/www.goesby.com/user.php/rhaco",File::relative("/www.rhaco.org/doc/ja/","/www.goesby.com/user.php/rhaco"));
			eq("./ja/overview.html",File::relative("c:/www.rhaco.org/doc/","c:/www.rhaco.org/doc/ja/overview.html"));
			eq("c:/www.goesby.com/user.php/rhaco",File::relative("c:/www.rhaco.org/doc/ja/","c:/www.goesby.com/user.php/rhaco"));
			eq("./Documents/workspace/prhagger/__settings__.php",File::relative("/Users/kaz/","/Users/kaz/Documents/workspace/prhagger/__settings__.php"));
			eq("./",File::relative("C:/xampp/htdocs/rhaco/test/template/sub","C:/xampp/htdocs/rhaco/test/template/sub"));
			eq("./",File::relative('C:\xampp\htdocs\rhaco\test\template\sub','C:\xampp\htdocs\rhaco\test\template\sub'));
		 */
		$rlist = array(array('://','/./','//'),array('#REMOTEPATH#','/','/')
					,array("/^\/(.+)$/","/^(\w):\/(.+)$/"),array("#ROOT#\\1","\\1#WINPATH#\\2",'')
					,array('#REMOTEPATH#','#ROOT#','#WINPATH#'),array('://','/',':/'));
		$baseUrl = preg_replace($rlist[2],$rlist[3],str_replace($rlist[0],$rlist[1],str_replace("\\",'/',$baseUrl)));
		$targetUrl = preg_replace($rlist[2],$rlist[3],str_replace($rlist[0],$rlist[1],str_replace("\\",'/',$targetUrl)));
		$filename = $url = '';
		$counter = 0;

		if(preg_match("/^(.+\/)[^\/]+\.[^\/]+$/",$baseUrl,$null)) $baseUrl = $null[1];
		if(preg_match("/^(.+\/)([^\/]+\.[^\/]+)$/",$targetUrl,$null)) list($tmp,$targetUrl,$filename) = $null;
		if(substr($baseUrl,-1) == '/') $baseUrl = substr($baseUrl,0,-1);
		if(substr($targetUrl,-1) == '/') $targetUrl = substr($targetUrl,0,-1);
		$baseList = explode('/',$baseUrl);
		$targetList = explode('/',$targetUrl);
		$baseSize = sizeof($baseList);

		if($baseList[0] != $targetList[0]) return str_replace($rlist[4],$rlist[5],$targetUrl);
		foreach($baseList as $key => $value){
			if(!isset($targetList[$key]) || $targetList[$key] != $value) break;
			$counter++;
		}
		for($i=sizeof($targetList)-1;$i>=$counter;$i--) $filename = $targetList[$i].'/'.$filename;
		if($counter == $baseSize) return sprintf('./%s',$filename);
		return sprintf('%s%s',str_repeat('../',$baseSize - $counter),$filename);
	}
	/**
	 * フォルダ名の配列を取得
	 * @param string $directory  検索対象のファイルパス
	 * @param boolean $recursive 階層を潜って取得するか
	 * @param boolean $a 隠しファイルも参照するか
	 * @return string[]
	 */
	static public function dir($directory,$recursive=false,$a=false){
		$directory = self::parse_filename($directory);
		if(is_file($directory)) $directory = dirname($directory);
		if(is_readable($directory) && is_dir($directory)) return new FileIterator($directory,0,$recursive,$a);
		throw new InvalidArgumentException(sprintf('permission denied `%s`',$directory));
	}
	/**
	 * 指定された$directory内のファイル情報をFileとして配列で取得
	 * @param string $directory  検索対象のファイルパス 
	 * @param boolean $recursive 階層を潜って取得するか
	 * @param boolean $a 隠しファイルも参照するか
	 * @return File[]
	 */
	static public function ls($directory,$recursive=false,$a=false){
		$directory = self::parse_filename($directory);
		if(is_file($directory)) $directory = dirname($directory);
		if(is_readable($directory) && is_dir($directory)){
			return new FileIterator($directory,1,$recursive,$a);
		}
		throw new InvalidArgumentException(sprintf('permission denied `%s`',$directory));
	}
	/**
	 * ファイルパスからディレクトリ名部分を取得
	 * @param string $path ファイルパス
	 * @return string
	 */
	static public function dirname($path){
		$dir_name = dirname(str_replace("\\",'/',$path));
		$len = strlen($dir_name);
		return ($len === 1 || ($len === 2 && $dir_name[1] === ':')) ? null : $dir_name;
	}
	/**
	 * フルパスからファイル名部分を取得
	 * @param string $path ファイルパス
	 * @return string
	 */
	static public function basename($path){
		$basename = basename($path);
		$len = strlen($basename);
		return ($len === 1 || ($len === 2 && $basename[1] === ':')) ? null : $basename;
	}
	/**
	 * ディレクトリでユニークなファイル名を返す
	 * @param $dir
	 * @param $prefix
	 * @return string
	 */
	static public function temp_path($dir,$prefix=null){
		if(is_dir($dir)){
			if(substr(str_replace("\\",'/',$dir),-1) != '/') $dir .= '/';
			while(is_file($dir.($path = uniqid($prefix,true))));
			return $path;
		}
		return uniqid($prefix,true);
	}
	/**
	 * パスの前後にスラッシュを追加／削除を行う
	 * @param string $path ファイルパス
	 * @param boolean $prefix 先頭にスラッシュを存在させるか
	 * @param boolean $postfix 末尾にスラッシュを存在させるか
	 * @return string
	 */	
	static public function path_slash($path,$prefix,$postfix){
		if(!empty($path)){
			if($prefix === true){
				if($path[0] != '/') $path = '/'.$path;
			}else if($prefix === false){
				if($path[0] == '/') $path = substr($path,1);
			}
			if($postfix === true){
				if(substr($path,-1) != '/') $path = $path.'/';
			}else if($postfix === false){
				if(substr($path,-1) == '/') $path = substr($path,0,-1);
			}
		}
		return $path;
		/***
			eq("/abc/",self::path_slash("/abc/",null,null));
			eq("/abc/",self::path_slash("abc",true,true));
			eq("/abc/",self::path_slash("/abc/",true,true));
			eq("abc/",self::path_slash("/abc/",false,true));			
			eq("/abc",self::path_slash("/abc/",true,false));
			eq("abc",self::path_slash("/abc/",false,false));
		 */
	}
}