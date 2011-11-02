<?php
import("org.rhaco.storage.db.module.DbcModule");
/**
 * DBコントローラ
 * @author Kazutaka Tokushima
 * @license New BSD License
 * @var string[] $resultset
 * @var number $port
 * @var boolean $upper
 * @var boolean $lower
 */
class Dbc extends Object implements Iterator{
	static private $tmp_db;
	static private $connections = array();
	protected $type;
	protected $host;
	protected $dbname;
	protected $user;
	protected $password;
	protected $port;
	protected $sock;
	protected $prefix;
	protected $lower;
	protected $upper;
	protected $con;
	protected $encode;

	private $connection;
	private $statement;
	private $resultset;
	private $resultset_counter;

	protected function __init__(){
		if(!empty($this->con)){
			$db = self::connection($this->con);
			foreach(array('type','host','dbname','user','password','port','sock','encode') as $name){
				$this->{$name} = isset($this->{$name}) ? $this->{$name} : $db->{$name};
			}
			$this->connection = $db->connection;
			$this->copy_module($db);
		}else{
			if(empty($this->type)) throw new RuntimeException("undef type");
			$module_class = Lib::import($this->type);
			$module = new $module_class($this->encode);
			if(!($module instanceof DbcModule)) throw new RuntimeException("no DbcModule ".$this->type);
			$this->add_module($module);
			$this->connection = $this->call_module("connect",$this->dbname,$this->host,$this->port,$this->user,$this->password,$this->sock);
			if(empty($this->connection)) throw new RuntimeException("connection fail ".$this->dbname);
			$this->connection->beginTransaction();
		}
	}
	static public function __shutdown__(){		
		if(!empty(self::$tmp_db)){
			foreach(self::$tmp_db as $db => $table){
				$dobj = self::connection($db);
				foreach($table as $name => $value){
					$dobj->drop_table($name);
					$dobj->commit();
				}
			}
		}
	}
	protected function __del__(){
		if($this->connection !== null){
			try{
				$this->connection->commit();
			}catch(Exception $e){}
		}
	}
	/**
	 * 接続先を取得する
	 * @param string $name
	 * @return self
	 */
	static public function connection($name){
		if(isset(self::$connections[$name])) return self::$connections[$name];
		$const = module_const($name);
		if(is_array($const)) $const = $const[rand(0,sizeof($const)-1)];
		if($const !== null) return self::$connections[$name] = new self($const);
		throw new RuntimeException("connection fail ".$name);
	}
	/**
	 * 接続先一覧
	 * @return self{}
	 */
	static public function connections(){
		return self::$connections;
	}
	/**
	 * テーブルを作成する
	 * @param string $name
	 * @param mixed $obj
	 * @param boolean $exec 実行するか
	 */
	public function create_table($table_name,$obj,$exec=true){
		$columns = array();
		$anon = array('type'=>'string','max'=>null,'decimal_places'=>null,'require'=>false,'primary'=>false);
		if($obj instanceof Object){
			foreach($obj->props() as $prop){
				if($obj->a($prop,'extra') !== true && $obj->a($prop,'cond') === null){
					$columns[$prop] = $anon;
					foreach(array_keys($anon) as $k){
						$a = $obj->a($prop,$k);
						if($a !== null) $columns[$prop][$k] = $a;
					}
				}
			}
		}else if(is_array($obj)){
			foreach($obj as $prop => $column){
				$columns[$prop] = $anon;
				foreach(array_keys($anon) as $k){
					if(isset($column[$k])) $columns[$prop][$k] = $column[$k];
				}
			}
		}
		if(!empty($columns)){
			$daq = $this->call_module("create_table",$table_name,$columns);
			if($exec) $this->query($daq->sql(),$daq->vars());
			return $daq->sql();
		}
	}
	/**
	 * テーブルを削除する
	 * @param string $name
	 */
	public function drop_table($name){
		$daq = $this->call_module("drop_table",$name);
		$this->query($daq->sql(),$daq->vars());
	}
	/**
	 * コミットする
	 */
	public function commit(){
		Log::debug("commit");
		$this->connection->commit();
		$this->connection->beginTransaction();
	}
	/**
	 * ロールバックする
	 */
	public function rollback(){
		Log::debug("rollback");
		$this->connection->rollBack();
		$this->connection->beginTransaction();
	}
	/**
	 * 文を実行する準備を行う
	 * @param string $sql
	 * @return PDOStatement
	 */
	public function prepare($sql){
		return $this->connection->prepare($sql);
	}
	/**
	 * SQL ステートメントを実行する
	 * @param string $sql 実行するSQL
	 * @param array $vars プリペアドステートメントへセットする値
	 */
	public function query($sql,array $vars=array()){
		$this->statement = $this->prepare($sql);
		if($this->statement === false) throw new LogicException($sql);
		$this->statement->execute($vars);
		$errors = $this->statement->errorInfo();
		if(isset($errors[1])){
			$this->rollback();
			throw new LogicException("[".$errors[1]."] ".(isset($errors[2]) ? $errors[2] : "")." : ".$sql);
		}
		/***
			self::temp("test_1","test_db_query",array("id"=>"serial","value"=>"string"));
			$db = self::connection("test_1");
			$db->query("insert into test_db_query(value) value(?)",array("abcedf"));
			$db->query("insert into test_db_query(value) value(?)",array("ghijklm"));

			$db->query("select value from test_db_query");
			$list = array("abcedf","ghijklm");
			$i = 0;
			while($result = $db->next_result()){
				eq($list[$i],$result["value"]);
				$i++;
			}

			$db->query("select value from test_db_query");
			$list = array("abcedf","ghijklm");
			foreach($db as $key => $result){
				eq($list[$key],$result["value"]);
			}
		 */
	}
	/**
	 * 直前に実行したSQL ステートメントに値を変更して実行する
	 * @param array $vars プリペアドステートメントへセットする値
	 */
	public function re(array $vars=array()){
		if(!isset($this->statement)) throw new LogicException();
		$this->statement->execute($vars);
		$errors = $this->statement->errorInfo();
		if(isset($errors[1])){
			$this->rollback();
			throw new LogicException("[".$errors[1]."] ".(isset($errors[2]) ? $errors[2] : "")." : ".$sql);
		}
	}
	/**
	 * 結果セットから次の行を取得する
	 * @param string $name 特定のカラム名
	 * @return string/arrray
	 */
	public function next_result($name=null){
		$this->resultset = $this->statement->fetch(PDO::FETCH_ASSOC);
		if($this->resultset !== false){
			if($name === null) return $this->resultset;
			return (isset($this->resultset[$name])) ? $this->resultset[$name] : null;
		}
		return null;
	}

	public function rewind(){
		$this->resultset_counter = 0;
		$this->resultset = $this->statement->fetch(PDO::FETCH_ASSOC);
	}
	public function current(){
		return $this->resultset;
	}
	public function key(){
		return $this->resultset_counter++;
	}
	public function valid(){
		return ($this->resultset !== false);
	}
	public function next(){
		$this->resultset = $this->statement->fetch(PDO::FETCH_ASSOC);
	}
	/**
	 * テンポラリテーブルを作成する
	 * @param string $db
	 * @param string $name
	 * @param array $columns array("カラム名"=>"型",....)
	 */
	static public function temp($db,$name,array $columns){
		if(isset(self::$tmp_db[$db][$name])) throw new RuntimeException($name." already exists");
		try{
			$con = self::connection($db);
		}catch(RuntimeException $e){
			throw new RuntimeException("connection fail ".$db);
		}
		foreach($columns as $key => $column){
			$columns[$key] = array('type'=>$column);
		}
		Log::debug($con->create_table($name,$columns,true));
		$con->commit();
		self::$tmp_db[$db][$name] = $columns;
	}
}
