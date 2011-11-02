<?php
/**
 * xhprofでプロファイラ情報を記録する
 * 
 * @see http://pecl.php.net/package/xhprof
 * @see http://www.graphviz.org/
 * @author tokushima
 * @const string $output_dir 出力ディレクトリ
 * @const string $type 出力ファイルの種類 ( ******.rhaco2 )
 */
class Xhprof{
	static public function __import__(){
		if(extension_loaded('xhprof')){
			$preg_match = module_const('preg_match');
			if(empty($preg_match) || preg_match($preg_match,self::request())) xhprof_enable();
		}
	}
	static public function __shutdown__(){
		if(extension_loaded('xhprof')){
			$preg_match = module_const('preg_match');
			if(empty($preg_match) || preg_match($preg_match,self::request())){
				$data = xhprof_disable();
				$dir = module_const('output_dir',ini_get('xhprof.output_dir'));
				if(empty($dir)) $dir = App::work('xhprof');
				$id = uniqid();
				$type = module_const('type','rhaco2');
				$view_url = module_const('view_url');
				File::write(File::absolute($dir,$id.'.'.$type),serialize($data));
	
				if(isset($view_url)){
					Log::info(sprintf('view: %s, request: %s',Text::fstring($view_url,$id,$type),self::request()));
				}else{
					Log::info(sprintf('run: %s ,source: %s, request: %s',$id,$type,self::request()));
				}
			}
		}
	}
	static private function request(){
		return isset($_SERVER['REQUEST_URI']) ? 
					preg_replace("/^(.+)\?.*$/","\\1",$_SERVER['REQUEST_URI']) : 
					(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'].(isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '') : '');		
	}
}

/*
# cp /etc/php.ini.default /etc/php.ini

cd xhprof-0.9.2/extension
phpize
./configure --with-php-config=/usr/bin/php-config
make 
make test
sudo make install

/etc/php.ini
----------
[xhprof]
extension=xhprof.so
;
; directory used by default implementation of the iXHProfRuns
; interface (namely, the XHProfRuns_Default class) for storing
; XHProf runs.
;
xhprof.output_dir=<directory_for_storing_xhprof_runs>
*/
