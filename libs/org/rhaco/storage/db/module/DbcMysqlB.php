<?php
import("org.rhaco.storage.db.module.DbcMysql");
/**
 * Blobを使う場合のMysqlモジュール
 *
 * @author tokushima
 */
class DbcMysqlB extends DbcMysql{
	protected function column_alias_sql(Dao $dao,$column,Q $q,$alias=true){
		$column_str = ($alias) ? $column->table_alias().".`".$column->column()."`" : "`".$column->column()."`";

		if(!empty($this->encode)){
			switch($dao->a($column->name(),"type")){
				case "string":
				case "text":
				case "email":
				case "choice":
				case "mixed":
					$column_str = "convert(".$column_str." using ".$this->encode.") ";
			}
		}
		if($q->ignore_case()) return "upper(".$column_str.")";
		return $column_str;
	}

	public function create_table($name,array $columns){
		$sql = 'create table '.$this->quotation($name).'(';
		$columndef = $primary = array();
		foreach($columns as $column_name => $column){
			$column_str = "";
			switch($column['type']){
				case 'mixed':
				case 'string': $column_str = $this->quotation($column_name).' blob'; break;
				case 'text': $column_str = $this->quotation($column_name).' longblob'; break;
				case 'number': $column_str = $this->quotation($column_name).' '.(isset($column['decimal_places']) ? sprintf("numeric(%d,%d)",26-$column['decimal_places'],$column['decimal_places']) : 'double'); break;
				case 'serial': $column_str = $this->quotation($column_name).' int auto_increment'; break;
				case 'boolean': $column_str = $this->quotation($column_name).' tinyint(1)'; break;
				case 'timestamp': $column_str = $this->quotation($column_name).' timestamp'; break;
				case 'date': $column_str = $this->quotation($column_name).' date'; break;
				case 'time': $column_str = $this->quotation($column_name).' int'; break;
				case 'intdate': 
				case 'integer': $column_str = $this->quotation($column_name).' int'; break;
				case 'email': $column_str = $this->quotation($column_name).' varchar(255)'; break;
				case 'alnum': $column_str = $this->quotation($column_name).' longblob'; break;
				case 'choice': $column_str = $this->quotation($column_name).' longblob'; break;
				default: throw new InvalidArgumentException('undefined type `'.$column['type'].'`');
			}
			$column_str .= (($column['require']) ? ' not' : '').' null ';
			if($column['primary'] || $column['type'] == 'serial') $primary[] = $this->quotation($column_name);
			$columndef[] = $column_str;
		}
		$sql .= implode(',',$columndef);
		if(!empty($primary)) $sql .= ' ,primary key ( '.implode(',',$primary).' ) ';
		$sql .= ' ) engine = InnoDB character set utf8 collate utf8_general_ci;';
		return Dao::daq($sql);
	}
}
