<?php
/**
 * DBのテーブルからDaoインスタンスを生成する
 * @author tokushima
 */
abstract class InstantDao extends Dao{
	static private $_instant_mapper_;
	static private $_instant_table_name_;

	protected function __new_instance__(){

		if(!isset(self::$_instant_mapper_[get_class($this)])){
			/**
			 * カラム一覧を取得するSQL文を生成する
			 * @param self $this
			 * @return Daq
			 */
			$daq = $this->call_module("show_columns_sql",$this);
			$statement = $this->prepare($daq);
			$statement->execute($daq->ar_vars());
			$errors = $statement->errorInfo();
			if(isset($errors[1])){
				C($this)->rollback();
				throw new LogicException("[".$errors[1]."] ".(isset($errors[2]) ? $errors[2] : ""));
			}
			self::$_instant_mapper_[get_class($this)] = $this->call_module("parse_columns",$statement);
		}

		foreach(self::$_instant_mapper_[get_class($this)] as $name => $column){
			$this->{$name} = $column["default"];
			$this->a($name,"type",$column["type"],true);
			$this->a($name,"column",$column["column"],true);
			$this->a($name,"primary",$column["primary"],true);
			$this->a($name,"decimal_places",$column["decimal_places"],true);
		}
	}
	static public function instance($name,$db,$dict=null){
		if(!isset(self::$_instant_table_name_[$db][$name])){
			self::$_instant_table_name_[$db][$name] = create_class(''
																	,__CLASS__
																	,'@class @{"database":"'.$db.'","table":"'.$name.'"}');
		}
		$class_name = self::$_instant_table_name_[$db][$name];
		return new $class_name($dict);
	}
}
