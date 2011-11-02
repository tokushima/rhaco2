<?php
import('org.rhaco.storage.db.model.Q');
import('org.rhaco.storage.queue.QueueModel');
module('QueueDao');
/**
 * キューのモジュール
 * @author tokushima
 *
 */
class QueueDaoModule{
	/**
	 * 挿入
	 * @param QueueModel $obj
	 */
	public function insert(QueueModel $obj){
		$dao = new QueueDao();
		$dao->set($obj);
		$dao->save();
		C(QueueDao)->commit();
	}
	/**
	 * 削除
	 * @param string $id
	 */
	public function delete($id){
		try{
			$obj = C(QueueDao)->find_get(Q::eq('id',$id));
			$obj->delete();
			C(QueueDao)->commit();
			return true;
		}catch(Exception $e){
			return false;
		}
	}
	/**
	 * 終了
	 * @param string $id
	 */
	public function finish($id){
		try{
			$obj = C(QueueDao)->find_get(Q::eq('id',$id));
			$obj->fin(time());
			$obj->save();
			C(QueueDao)->commit();
			return true;
		}catch(Exception $e){
			return false;
		}
	}
	/**
	 * 取得
	 * @param string $type
	 * @param integer $priority
	 */
	public function get($type,$priority){
		while(true){
			try{
				$object = C(QueueDao)->find_get(
							Q::gte('priority',$priority)
							,Q::eq('type',$type)
							,Q::eq('fin',null)
							,Q::eq('lock',null)
							,Q::order('priority,id')
						);
				$object->lock(microtime(true));
				$object->save(Q::eq('lock',null));
				C(QueueDao)->commit();
				return $object->get();
			}catch(BadMethodCallException $e){
			}catch(NotfoundDaoException $e){
				throw new QueueNotfoundException('node `'.$type.'` not found');
			}
		}
	}
	
	/**
	 * リセット
	 * @param string $type
	 * @param integer $lock_time
	 */
	public function reset($type,$lock_time){
		$result = array();
		foreach(C(QueueDao)->find(Q::eq('type',$type),Q::eq('fin',null),Q::neq('lock',null),Q::lte('lock',$lock_time)) as $obj){
			try{
				$obj->lock(null);
				$obj->save(Q::eq('fin',null),Q::eq('id',$obj->id()));
				C(QueueDao)->commit();
				$result[] = $obj->get();
			}catch(BadMethodCallException $e){
			}
		}
		return $result;
	}
	/**
	 * 完了していないキューの一覧
	 * @param string $type
	 * @param Paginator $paginator
	 * @param string $sorter
	 * @return QueueModel[]
	 */
	public function view($type,Paginator $paginator,$sorter){
		$q = new Q();
		$q->add(Q::eq('fin',null));
		if(!empty($type)) $q->add(Q::eq('type',$type));
		$result = array();
		foreach(C(QueueDao)->find($q,$paginator,Q::order($sorter)) as $m){
			$result[] = $m->get();
		}
		return $result;
	}
	/**
	 * 終了したものを削除する
	 * @param string $type
	 * @param timestamp $fin
	 */
	public function clean($type,$fin){
		foreach(C(QueueDao)->find(Q::eq('type',$type),Q::neq('fin',null),Q::lte('fin',$fin)) as $obj){
			try{
				$obj->delete();
			}catch(BadMethodCallException $e){}
		}
		C(QueueDao)->commit();
	}
}