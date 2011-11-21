<?php
class CoreApp extends Flow{
	protected function __init__(){
		$this->vars("init_var","INIT");
	}
	/**
	 * coreをエクスポートする
	 * @param Request $req
	 * @param string $value 書き出すフォルダ
	 */
	static public function __setup_export_core__(Request $req,$value){
		$outpath = (empty($value)) ? dirname(dirname(dirname(__FILE__))).'/bin' : $value;		
		$jump_php = File::absolute($outpath,'rhaco2.php');
		$jump_php_min = File::absolute($outpath,'rhaco2_min.php');
		$jump_php_debug = File::absolute($outpath,'rhaco2_debug.tgz');
		
		$r = new ReflectionClass('Object');
		$base = dirname($r->getFilename()).'/';
		$debug_dir = $outpath.'/_debug_';
		
		$version = date('Ymd',File::last_update($base));
		File::mkdir($debug_dir);
		$include_files = array();
		
		File::write($jump_php,"<?php\n/*"."*\n * @version ".$version."\n */\n");
		File::write($jump_php_min,"<?php\n/*"."*\n * @version ".$version."\n */\n");
		
		foreach(array('App','Exceptions','FileIterator','Gettext','Lib','Object'
					,'Paginator','Request','Store','TagIterator','Tag','Template','Templf'
					,'Text','Command','File','Flow','Http','Log') as $class){
			File::append($jump_php_min,self::trim_src(file_get_contents($base.$class.'.php'),true)."\n");
			File::append($jump_php,self::trim_src(file_get_contents($base.$class.'.php'),false)."\n");
			$include_files[] = 'include(dirname(__FILE__)."/'.$class.'.php");';
			File::copy($base.$class.'.php',$debug_dir.'/'.$class.'.php');
		}
		foreach(array('Setup','Test') as $class){
			File::append($jump_php,self::trim_src(file_get_contents($base.$class.'.php'),false)."\n");
			$include_files[] = 'include(dirname(__FILE__)."/'.$class.'.php");';
			File::copy($base.$class.'.php',$debug_dir.'/'.$class.'.php');
		}
		File::append($jump_php_min,self::trim_src(file_get_contents($base.'__funcs__.php'),true)."\n");
		File::append($jump_php,self::trim_src(file_get_contents($base.'__funcs__.php'),false)."\n");
		$include_files[] = 'include(dirname(__FILE__)."/__funcs__.php");';
		File::copy($base.'__funcs__.php',$debug_dir.'/__funcs__.php');

		File::append($jump_php,self::trim_src(file_get_contents($base.'__tfuncs__.php'),false)."\n");
		$include_files[] = 'include(dirname(__FILE__)."/__tfuncs__.php");';
		File::copy($base.'__tfuncs__.php',$debug_dir.'/__tfuncs__.php');

		File::write($debug_dir.'/rhaco2.php','<?php'."\n".implode("\n",$include_files)."\n?>".file_get_contents($base.'jump.php'));
		File::tgz($jump_php_debug,$debug_dir);
		File::rm($debug_dir);

		File::append($jump_php,self::trim_src(file_get_contents($base.'jump.php'),false)."\n");
		File::append($jump_php,'if($run == 0 && !$isweb) Setup::start($exception);');

		File::append($jump_php_min,self::trim_src(file_get_contents($base.'jump.php'),true)."\n");
		$src = File::read($jump_php_min);
		$src = preg_replace("/if\(class_exists\('Test'\)\).*/","",$src);
		while(preg_match("/\n[\t\n]*\n/m",$src)) $src = preg_replace("/\n[\t\n]*\n/sm","\n",$src);
		File::write($jump_php_min,$src);
		
		print('writen:'.PHP_EOL);
		print(' '.$jump_php.PHP_EOL);
		print(' '.$jump_php_min.PHP_EOL);
		print(' '.$jump_php_debug.PHP_EOL);
	}
	static private function trim_src($src,$trim){
		$src = str_replace("\n<?php",'',"\n".$src);
		$src = trim(preg_replace("/\/\*\*\*.*?\*\//s","",$src));
		if($trim){
			if(preg_match_all("/\/\*\*.*?\*\//s",$src,$m)){
				foreach($m[0] as $d){
					if(strpos($d,'@var') === false) $src = str_replace($d,'',$src);
				}
			}
			$src = preg_replace("/\n[\t]+\n/sm","\n",$src);
			$src = preg_replace("/[\n]+/sm","\n",$src);
		}
		return trim($src);
	}
	/**
	 * coreのdoctestを実行する
	 * @param Request $req
	 * @param mixed $value
	 */
	static public function __setup_core_test__(Request $req,$value){
		$level = ($req->is_vars('fail') ? Test::FAIL : 0) | ($req->is_vars('success') ? Test::SUCCESS : 0) | ($req->is_vars('none') ? Test::NONE : 0);
		$start_time = microtime(true);

		if(empty($value)){
			if($level === 0) $level = (Test::FAIL);
			Test::exec_type($level|Test::COUNT);
			$r = new ReflectionClass('Object');

			foreach(File::ls(dirname($r->getFilename())) as $f){
				if($f->is_class()) Test::verify($f->oname());
			}
			Test::verifies();
		}else{
			if($level === 0) $level = (Test::FAIL|Test::SUCCESS|Test::NONE);
			Test::exec_type($level|Test::COUNT);
			Test::verify($value,$req->in_vars("m"),$req->in_vars("b"));
		}				
		Test::flush();
		print(sprintf("memory_get_usage: %s Mbyte ( %s sec )\n\n",number_format((memory_get_usage() / 1024 / 1024),3),number_format((microtime(true)-$start_time),3)));
		File::rm(dirname(dirname(__FILE__)).'/rhaco2.php');
	}
	/**
	 * coreをコピーする
	 * @param Request $req
	 * @param string $value コピー先のファイルパス
	 * @request void $min rhaco2_min.phpをコピーするか
	 */
	static public function __setup_copy_core__(Request $req,$value){
		$bin = App::path('bin').'/'.($req->is_vars('min') ? 'rhaco2_min.php' : 'rhaco2.php');
		if(!is_file($bin)) throw new LogicException($bin.' not found');
		if(is_file($value)){
			$fp = fopen($value,'w+');
			fwrite($fp,file_get_contents($bin));
			fclose($fp);
			println('rewrite '.$value);
		}else{
			copy($bin,$value);
			println('copy '.$value);	
		}
	}
	/***
		# __setup__
		
		eq(true,true);
	 */
	
	public function under_var(){
		$this->vars("_hoge","hogehoge");
		$this->vars("hoge","ABC");
		/***
			# test
			eq(true,true);
		 */
	}
	public function raise(){
		throw new Exception("hoge");
	}
	public function add_exceptions(){
		Exceptions::add(new Exception("hoge"));
	}
	
	/***
		# __teardown__		
		eq(true,true);
		
	 */
	public function exception(){
		/***
			# a
			try{
				Exceptions::add(new Exception());
				Exceptions::throw_over();
				fail();
			}catch(Exceptions $e){
				success();
			}
		 */
		/***
			# b
			try{
				Exceptions::throw_over();
				success();
			}catch(Exceptions $e){
				fail();
			}
		 */
	}
}