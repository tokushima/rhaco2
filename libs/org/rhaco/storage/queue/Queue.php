<?php
import('org.rhaco.storage.queue.QueueModel');
import('org.rhaco.storage.queue.exception.QueueIllegalDataTypeException');
import('org.rhaco.storage.queue.exception.QueueNotfoundException');
import('org.rhaco.lang.Sorter');
/**
 * キューの制御
 * @author tokushima
 */
class Queue extends Object{
	/**
	 * 挿入
	 * @param string $type
	 * @param string $data
	 * @param integer $priority
	 */
	static public function insert($type,$data,$priority=3){
		$obj = new QueueModel();
		$obj->type($type);
		$obj->data($data);
		$obj->priority($priority);
		return Object::C(__CLASS__)->call_module('insert',$obj);
	}
	/**
	 * 取得
	 * @param string $type
	 * @param integer $priority
	 */
	static public function get($type,$priority=1){
		$obj = Object::C(__CLASS__)->call_module('get',$type,$priority);
		if(!($obj instanceof QueueModel)) throw new QueueIllegalDataTypeException('must be an of '.get_class($obj));
		return $obj;
	}
	/**
	 * 一覧で取得
	 * @param integer $limit
	 * @param string $type
	 * @param integer $priority
	 */
	static public function gets($limit,$type,$priority=1){
		$result = array();
		while(true){
			try{
				$result[] = self::get($type,$priority);
				$limit--;
				if($limit == 0) break;
			}catch(QueueNotfoundException $e){
				break;
			}
		}
		return $result;
	}
	/**
	 * 削除
	 * @param string $key
	 */	
	static public function delete($key){
		if($key instanceof QueueModel) $key = $key->id();
		return Object::C(__CLASS__)->call_module('delete',$key);
	}
	/**
	 * 終了とする
	 * @param string $key
	 */	
	static public function finish($key){
		if($key instanceof QueueModel) $key = $key->id();
		Object::C(__CLASS__)->call_module('finish',$key);
	}
	
	/**
	 * 終了していないものをリセットする
	 * @param string $type
	 * @param integer $sec
	 * @return org.rhaco.store.queue.Model[]
	 */	
	static public function reset($type,$sec=86400){
		$time = microtime(true) - (float)$sec;
		return Object::C(__CLASS__)->call_module('reset',$type,$time);
	}
	/**
	 * 一覧を取得する
	 * @param string $type
	 * @param integer $page
	 * @param integer $paginate_by
	 * @param string $order
	 * @param string $pre_order
	 * @return mixed[] ($list,$paginator,$sorter)
	 */
	static public function view($type,$page=1,$paginate_by=30,$order=null,$pre_order=null){
		$paginator = new Paginator($paginate_by,$page);
		if(empty($order)) $order = 'id';
		$sorter = Sorter::order($order,$pre_order);
		$list = array();
		if(Object::C(__CLASS__)->has_module('view')){
			$list = Object::C(__CLASS__)->call_module('view',$type,$paginator,$sorter);
		}
		$paginator->cp(array('type'=>$type,'order'=>$sorter));
		return array($list,$paginator,$sorter);
	}
	/**
	 * 終了したものを削除する
	 * @param string $type
	 * @param timstamp $fin
	 */
	static public function clean($type,$fin=null){
		Object::C(__CLASS__)->call_module('clean',$type,$fin);
	}
}
