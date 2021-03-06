<?php
import('org.rhaco.storage.db.exception.DaoExceptions');
/**
 * Daoの例外
 * @author tokushima
 *
 */
class DaoException extends Exception{
	private $e = array();

	/**
	 * Exceptionを追加する
	 * @param Exception $exception 例外
	 * @param string $group グループ名
	 */
	public function add(DaoException $exception,$group=null){
		if(empty($group)) $group = 'exceptions';
		$this->e[$group][] = $exception;
	}
	/**
	 * 例外があればthrowする
	 * @throws DaoExceptions
	 */
	public function throw_over(){
		if(!empty($this->e)){
			$msg = count($this->e).' exceptions: ';
			foreach($this->e as $g => $es){
				foreach($es as $e){
					$msg .= PHP_EOL.' '.$e->getMessage();
					Exceptions::add($e,$g);
				}
			}
			throw new DaoExceptions($msg);
		}
	}
}
