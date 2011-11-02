<?php
import("org.rhaco.storage.db.Dao");
/**
 * Daoでセッションを扱うRequestモジュール
 * セッション操作時のエラーはログに反映されない為、debug_out_pathへの書き出す
 * @const string $debug_out_path デバックファイルのパス
 * @author tokushima
 * @var string $id @{"primary":true}
 * @var text $text
 * @var number $expires
 */
class SessionDao extends Dao{
	protected $id;
	protected $data;
	protected $expires;

	protected function __init__(){
		$this->expires = time();
	}
	protected function __before_update__(){
		$this->expires = time();
	}
	protected function __set_data__($value){
		$this->data = ($value === null) ? '' : $value;
	}
	/**
	 * Requestのモジュール
	 * @param string $session_name
	 * @param string $id
	 * @param string $save_path
	 * @return boolean
	 */
	public function session_verify($session_name,$id,$save_path){
		try{
			return (C(__CLASS__)->find_count(Q::eq('id',$id)) === 1);
		}catch(Exception $e){}
		return false;
	}
	/**
	 * Requestのモジュール
	 * @param string $id
	 * @return string
	 */
	public function session_read($id){
		try{
			$obj = C(__CLASS__)->find_get(Q::eq('id',$id));
			return $obj->data();
		}catch(NotfoundDaoException $e){
		}catch(Exception $e){
			$this->exception($e,'session_read');
		}
		return '';
	}
	/**
	 * Requestのモジュール
	 * @param string $id
	 * @param string $sess_data
	 * @return boolean
	 */
	public function session_write($id,$sess_data){
		try{
			$obj = new self();
			$obj->id($id);
			$obj->data($sess_data);
			try{
				$obj->save();
			}catch(DaoBadMethodCallException $e){}
			return true;
		}catch(Exception $e){
			$this->exception($e,'session_write');
		}
		return false;
	}
	/**
	 * Requestのモジュール
	 * @param string $id
	 * @return boolean
	 */
	public function session_destroy($id){
		try{
			$obj = new self();
			$obj->id($id);
			try{
				$obj->delete();
			}catch(DaoBadMethodCallException $e){}
			return true;
		}catch(Exception $e){
			$this->exception($e,'session_destroy');
		}
		return false;
	}
	/**
	 * Requestのモジュール
	 * @param int $maxlifetime
	 * @return boolean
	 */
	public function session_gc($maxlifetime){
		try{
			C(__CLASS__)->find_delete(Q::lt('expires',time() - $maxlifetime));
			C(__CLASS__)->commit();
			return true;
		}catch(Exception $e){
			$this->exception($e,'session_gc');
		}
		return false;
	}
	private function exception(Exception $e,$name){
		$path = module_const('debug_out_path');
		if(!empty($path)) File::append($path,sprintf("%s (%s): %s\n\n",$name,date('Y/m/d H:i:s'),$e->getMessage()));
	}
}
