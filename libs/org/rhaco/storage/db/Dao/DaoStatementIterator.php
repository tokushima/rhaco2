<?php
/**
 * DBデータイテレータ
 * @author Kazutaka Tokushima
 * @license New BSD License
 */
class DaoStatementIterator implements Iterator{
	private $dao;
	private $statement;
	private $resultset;
	private $key;
	private $resultset_counter;

	public function __construct(Dao $dao,PDOStatement $statement){
		$this->dao = $dao;
		$this->statement = $statement;
	}
	public function rewind(){
		$this->resultset = $this->statement->fetch(PDO::FETCH_ASSOC);
		$this->resultset_counter = 0;
	}
	public function current(){
		$obj = deepcopy($this->dao);
		$this->key = $obj->parse_resultset($this->resultset);
		return $obj;
	}
	public function key(){
		return ($this->key === null) ? $this->resultset_counter++ : $this->key;
	}
	public function valid(){
		return ($this->resultset !== false);
	}
	public function next(){
		$this->resultset = $this->statement->fetch(PDO::FETCH_ASSOC);
	}
}
