<?php
import("org.rhaco.storage.db.module.DbcModule");
/**
 * Postgresqlモジュール
 * @author SHIGETA Takeshiro
 *  * @license New BSD License
 */
class DbcPgsql extends DbcModule{
	protected $quotation = '"';
	protected $order_random_str = 'random()';

	public function connect($name,$host,$port,$user,$password,$sock){
		if(!extension_loaded('pdo_pgsql')) throw new RuntimeException('pdo_sqlite not supported');
		$con = null;
		if(empty($host)) $host = 'localhost';
		if(empty($name)) throw new InvalidArgumentException('undef connection name');
		$dsn = empty($sock) ?
					sprintf("pgsql:dbname=%s host=%s port=%d",$name,$host,((empty($port) ? 5432 : $port))) :
					sprintf("pgsql:dbname=%s unix_socket=%s",$name,$sock);
		try{
			$con = new PDO($dsn,$user,$password);
			if(!empty($this->encode)) $this->prepare_execute($con,sprintf("set names '%s'",$this->encode));
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
	public function create_table($name,array $columns){
		$sql = 'create table '.$this->quotation($name).'(';
		$columndef = $primary = array();
		foreach($columns as $column_name => $column){
			$column_str = "";
			switch($column['type']){
				case 'mixed':
				case "string": $column_str = $this->quotation($column_name).(isset($column['max']) ? ' varchar('.$column['max'].')' : ' text'); break;
				case "text": $column_str = $this->quotation($column_name)." text"; break;
				case "number": $column_str = $this->quotation($column_name)." ".(isset($column['decimal_places']) ? sprintf("numeric(%d,%d)",26-$column['decimal_places'],$column['decimal_places']) : 'double'); break;
				case "serial": $column_str = $this->quotation($column_name)." serial"; break;
				case "boolean": $column_str = $this->quotation($column_name)." smallint"; break;
				case "timestamp": $column_str = $this->quotation($column_name)." timestamp"; break;
				case "date": $column_str = $this->quotation($column_name)." date"; break;
				case "time": $column_str = $this->quotation($column_name)." int"; break;
				case "intdate":
				case 'integer': $column_str = $this->quotation($column_name)." int"; break;
				case "email": $column_str = $this->quotation($column_name)." varchar(255)"; break;
				case "alnum": $column_str = $this->quotation($column_name).(isset($column['max']) ? ' varchar('.$column['max'].')' : ' text'); break;
				case "choice": $column_str = $this->quotation($column_name)." varchar(255)"; break;
				default: throw new InvalidArgumentException("undefined type `".$column['type']."`");
			}
			$column_str .= (($column['require']) ? ' not' : '').' null ';
			if($column['primary'] || $column['type'] == "serial") $primary[] = $this->quotation($column_name);
			$columndef[] = $column_str;
		}
		$sql .= implode(",",$columndef);
		if(!empty($primary)) $sql .= " ,primary key ( ".implode(",",$primary)." ) ";
		$sql .= " );";
		return Dao::daq($sql);
	}
	public function show_columns_sql(Dao $obj){
		return Dao::daq("select c.column_name, c.udt_name, c.column_default, c.table_name, k.constraint_name from information_schema.COLUMNS c left outer join (select * from information_schema.KEY_COLUMN_USAGE where table_name='".strtolower($obj->table())."') k on c.column_name = k.column_name where c.table_name = '".strtolower($obj->table())."'");
	}
	public function last_insert_id_sql(){
		return Dao::daq("select lastval() as last_insert_id;");
	}
	/**
	 * insert文を生成する
	 * @param Dao $dao
	 * @return Daq
	 */
	public function create_sql(Dao $dao){
		$insert = $vars = array();
		$autoid = null;
		foreach($dao->self_columns() as $column){
			if($column->auto()){
				$autoid = $column->name();
			}else{
				$insert[] = $this->quotation($column->column());
				$vars[] = $this->update_value($dao,$column->name());
			}
		}
		if(empty($insert)) return Dao::daq('insert into '.$this->quotation($column->table()).' default values',$vars,$autoid);
		return Dao::daq('insert into '.$this->quotation($column->table()).' ('.implode(',',$insert).') values ('.implode(',',array_fill(0,sizeof($insert),'?')).');'
					,$vars
					,$autoid
				);
	}
	public function parse_columns(PDOStatement $it){
		$results = array();
		foreach($it as $value){
			$type = $value["udt_name"];
			$name = $value["column_name"];
			$size = $max_digits = $decimal_places = null;

			if(!ctype_alpha($name[0])) $name = "c_".$name;
			if($type == "int4" && $value["column_default"]==="nextval('".$value["table_name"]."_".$value["column_name"]."_seq'::regclass)"){
				$type = "serial";
				$value["column_default"] = null;
			}else if(preg_match("/^enum\((.+)\)$/",$type,$match)){
				$type = "choice(".$match[1].")";
			}else if(preg_match("/^(.+)\(([\d,]+)\)/",$type,$match)){
				$type = $match[1];
				$size = $match[2];

				if(strpos($size,",") !== false){
					list($max_digits,$decimal_places) = $size;
					$size = null;
				}
			}
			switch($type){
				case "varchar":
					$type = "string"; break;
				case "longblob":
				case "tinyblob":
					$type = "text"; break;
				case "double":
				case "int":
				case "int4":					
				case "bigint":
				case "tinyint":
				case "smallint":
				case "decimal":
					$type = "number"; break;
				case "datetime":
					$type = "timestamp"; break;
			}
			$annotation = "type=".$type.","
							."column=".$name.","
							.(($value["constraint_name"] == $value["table_name"]."_pkey") ? "primary=true," : "")
							.(($size !== null) ? "length=".intval($size)."," : "")
							.(($max_digits !== null) ? "max_digits=".intval($max_digits)."," : "")
							.(($decimal_places !== null) ? "decimal_places=".intval($decimal_places)."," : "")
							;
			$results[] = (object)array("name"=>$name,
										"default"=>$value["column_default"],
										"annotation"=>$annotation);
		}
		return $results;
	}
	protected function column_alias_sql(Dao $dao,$column,Q $q,$alias=true){
		$column_str = ($alias) ? $column->table_alias().'.'.$this->quotation($column->column()) : $this->quotation($column->column());
		if($q->ignore_case()) return 'upper('.$column_str.'::text)';
		return $column_str;
	}
	protected function select_option_sql($paginator,$order){
		return ' '
				.(empty($order) ? "" : " order by ".implode(",",$order))
				.(($paginator instanceof Paginator) ? sprintf(" offset %d limit %d ",$paginator->offset(),$paginator->limit()) : "")
				;
	}
	protected function column_value(Dao $dao,$name,$value){
		if($value === null) return null;
		try{
			switch($dao->a($name,"type")){
				case "timestamp": return date("Y/m/d H:i:s",$value);
				case "date": return date("Y/m/d",$value);
				case "time": return date("H:i:s",strtotime(date("Y/m/d",time())) + $value);
			}
		}catch(Exception $e){}
		return $value;
	}
	protected function format_column_alias_sql(Dao $dao,Column $column,Q $q,$alias=true){
		return $this->column_alias_sql($dao,$column,$q,$alias).'::text';
	}
}
?>