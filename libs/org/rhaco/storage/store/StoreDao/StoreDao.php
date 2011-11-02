<?php
import("org.rhaco.storage.db.Dao");
/**
 * Daoを使ったStoreモジュール
 * @author tokushima
 * @var string $id @{"primary":true}
 * @var text $data
 * @var timestamp $expiry
 * @var boolean $serialize
 */
class StoreDao extends Dao{
	protected $id;
	protected $data;
	protected $expiry;
	protected $serialize = false;

	/**
	 * Storeのモジュール
	 */
	public function store_has($id,$ignore_time){
		try{
			if($ignore_time){
				C(__CLASS__)->find_get(Q::eq("id",$id));
			}else{
				C(__CLASS__)->find_get(Q::eq("id",$id),Q::gt("expiry",time()));
			}
			return true;
		}catch(NotfoundDaoException $e){}
		return false;
	}
	/**
	 * Storeのモジュール
	 */
	public function store_get($id){
		try{
			$obj = C(__CLASS__)->find_get(Q::eq("id",$id),Q::gt("expiry",time()));
			return ($obj->serialize()) ? unserialize($obj->data()) : $obj->data();
		}catch(NotfoundDaoException $e){}
		return null;
	}
	/**
	 * Storeのモジュール
	 */
	public function store_set($id,$source,$expiry_time){
		$obj = new self();
		$obj->id($id);
		$obj->expiry(time() + $expiry_time);

		if(!is_resource($source)){
			$obj->serialize(true);
			$source = serialize($source);
		}
		$obj->data($source);
		$obj->save();
		return $source;
	}
	/**
	 * Storeのモジュール
	 */
	public function store_delete($id){
		C(__CLASS__)->find_delete(Q::eq("id",$id));
		C(__CLASS__)->commit();
	}
}
