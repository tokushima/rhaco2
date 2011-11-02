<?php
import('org.rhaco.storage.db.module.DbcModule');
/**
 * Mysqlモジュール
 * @author tokushima
 */
class DbcMysql extends DbcModule{
	public function connect($name,$host,$port,$user,$password,$sock){
		if(!extension_loaded('pdo_mysql')) throw new RuntimeException('pdo_mysql not supported');
		$con = null;
		if(empty($host)) $host = 'localhost';
		if(empty($name)) throw new InvalidArgumentException('undef connection name');
		$dsn = empty($sock) ?
					sprintf('mysql:dbname=%s;host=%s;port=%d',$name,$host,((empty($port) ? 3306 : $port))) :
					sprintf('mysql:dbname=%s;unix_socket=%s',$name,$sock);
		try{
			$con = new PDO($dsn,$user,$password);
			if(!empty($this->encode)) $this->prepare_execute($con,'set names '.$this->encode);
			$this->prepare_execute($con,'set autocommit=0');
			$this->prepare_execute($con,'set session transaction isolation level read committed');
		}catch(PDOException $e){
			throw new PDOException(__CLASS__.' connect failed');
		}
		return $con;
	}
	private function prepare_execute($con,$sql){
		$st = $con->prepare($sql);
		$st->execute();
		$error = $st->errorInfo();
		if((int)$error[0] !== 0) throw new InvalidArgumentException($error[2]);
	}
	public function show_columns_sql(Dao $obj){
		return Dao::daq('show columns from '.$obj->table());
	}
	public function last_insert_id_sql(){
		return Dao::daq('select last_insert_id() as last_insert_id;');
	}
	protected function column_alias_sql(Dao $dao,Column $column,Q $q,$alias=true){
		$column_str = ($alias) ? $column->table_alias().'.'.$this->quotation($column->column()) : $this->quotation($column->column());
		if($q->ignore_case()) return 'upper('.$column_str.')';
		return $column_str;
	}
	public function parse_columns(PDOStatement $it){
		$results = array();
		foreach($it as $value){
			$type = $value['Type'];
			$name = $value['Field'];
			$size = $decimal_places = null;

			if(!ctype_alpha($name[0])) $name = 'c_'.$name;
			if($value['Extra'] == 'auto_increment'){
				$type = 'serial';
			}else if(preg_match("/^enum\((.+)\)$/",$type,$match)){
				$type = 'choice('.$match[1].')';
			}else if(preg_match("/^(.+)\(([\d,]+)\)/",$type,$match)){
				$type = $match[1];
				$size = $match[2];

				if(strpos($size,',') !== false){
					list($null,$decimal_places) = $size;
					$size = null;
				}
			}
			switch($type){
				case 'varchar':
					$type = 'string'; break;
				case 'blob':
				case 'longblob':
				case 'tinyblob':
					$type = 'text'; break;
				case 'double':
				case 'int':
				case 'bigint':
				case 'tinyint':
				case 'smallint':
				case 'decimal':
					$type = 'number'; break;
				case 'datetime':
					$type = 'timestamp'; break;
			}
			$results[$name] = array(
								'default'=>$value['Default']
								,'type'=>$type
								,'column'=>$value['Field']
								,'primary'=>($value['Key'] == 'PRI')
								,'decimal_places'=>(($decimal_places !== null) ? intval($decimal_places) : null)
							);
		}
		return $results;
	}
	public function create_table($name,array $columns){
		$sql = 'create table '.$this->quotation($name).'(';
		$columndef = $primary = array();
		foreach($columns as $column_name => $column){
			$column_str = '';
			switch($column['type']){
				case 'mixed':
				case 'string': $column_str = $this->quotation($column_name).' varchar('.(isset($column['max']) ? $column['max'] : 255).')'; break;
				case 'text': $column_str = $this->quotation($column_name).(isset($column['max']) ? ' varchar('.$column['max'].')' : ' text'); break;
				case 'number': $column_str = $this->quotation($column_name).' '.(isset($column['decimal_places']) ? sprintf('numeric(%d,%d)',26-$column['decimal_places'],$column['decimal_places']) : 'double'); break;
				case 'serial': $column_str = $this->quotation($column_name).' int auto_increment'; break;
				case 'boolean': $column_str = $this->quotation($column_name).' tinyint(1)'; break;
				case 'timestamp': $column_str = $this->quotation($column_name).' timestamp'; break;
				case 'date': $column_str = $this->quotation($column_name).' date'; break;
				case 'time': $column_str = $this->quotation($column_name).' int'; break;
				case 'intdate': 
				case 'integer': $column_str = $this->quotation($column_name).' int'; break;
				case 'email': $column_str = $this->quotation($column_name).' varchar(255)'; break;
				case 'alnum': $column_str = $this->quotation($column_name).(isset($column['max']) ? ' varchar('.$column['max'].')' : ' text'); break;
				case 'choice': $column_str = $this->quotation($column_name).' varchar(255)'; break;
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