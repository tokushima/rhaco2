<?php
/**
 * ファイルによりセッションを扱うRequestモジュール
 * @const save_path セッションファイルを保存するディレクトリ
 * @author tokushima
 */
class SessionFile{
	static private $session_save_path;

	public function __construct(){
		self::$session_save_path = module_const("save_path",App::work());
		if(substr(self::$session_save_path,-1) === "/") self::$session_save_path = substr(self::$session_save_path,0,-1);
	}
	/**
	 * Requestのモジュール
	 * @param string $session_name
	 * @param string $id
	 * @param string $save_path
	 * @return boolean
	 */
	public function session_verify($session_name,$id,$save_path){
		return is_file(self::$session_save_path."/sess_".$id);
	}
	/**
	 * Requestのモジュール
	 * @param string $id
	 * @return string
	 */
	public function session_read($id){
		$path = self::$session_save_path."/sess_".$id;
		return File::exist($path) ? File::read(self::$session_save_path."/sess_".$id) : "";
	}
	/**
	 * Requestのモジュール
	 * @param string $id
	 * @param string $sess_data
	 * @return boolean
	 */
	public function session_write($id,$sess_data){
		return File::write(self::$session_save_path."/sess_".$id,$sess_data);
	}
	/**
	 * Requestのモジュール
	 * @param string $id
	 * @return boolean
	 */
	public function session_destroy($id){
		if(File::exist(self::$session_save_path."/sess_".$id)){
			return File::rm(self::$session_save_path."/sess_".$id);
		}
		return true;
	}
	/**
	 * Requestのモジュール
	 * @param int $maxlifetime
	 * @return boolean
	 */
	public function session_gc($maxlifetime){
		foreach(glob(self::$session_save_path."/sess_*") as $filename){
			if(filemtime($filename) + $maxlifetime < time()) unlink($filename);
		}
		return true;
	}
}
