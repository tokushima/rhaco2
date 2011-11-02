<?php
import('org.rhaco.storage.db.module.DbcModule');
/**
 * SQLiteモジュール
 *
 * @author  Keisuke SATO <riafweb@gmail.com>
 * @license New BSD License
 */
class DbcSqlite extends DbcModule
{
	protected $order_random_str = 'random()';
	/**
	 * @see org.rhaco.storage.db.module.DbcModule#connect($name, $host, $port, $user, $password, $sock)
	 */
	public function connect($name, $host, $port, $user, $password){
		if(!extension_loaded('pdo_sqlite')) throw new RuntimeException('pdo_sqlite not supported');
		$con = null;
		$host = ($host == ':memory:') ? ':memory:' : File::absolute($host,$name);
		try{
			$con = new PDO(sprintf('sqlite:%s',$host));
		}catch(PDOException $e){
			throw new PDOException(__CLASS__.' connect failed');
		}
		return $con;
	}
	/**
	 * @see org.rhaco.storage.db.module.DbcModule#create_table($name, $columns)
	 */
	public function create_table($name, array $columns){
		$sql = 'create table '.$this->quotation($name).'(';
		$columndef = $primary = array();
		foreach($columns as $column_name => $column){
			$column_str = '';
			switch($column['type']){
				case 'mixed':
				case 'string': $column_str = $this->quotation($column_name).' TEXT'; break;
				case 'text': $column_str = $this->quotation($column_name).' BLOB'; break;
				case 'number': $column_str = $this->quotation($column_name).' REAL'; break;
				case 'serial': $column_str = $this->quotation($column_name).' INTEGER PRIMARY KEY'; break;
				case 'boolean': $column_str = $this->quotation($column_name).' INTEGER'; break;
				case 'timestamp': $column_str = $this->quotation($column_name).' INTEGER'; break;
				case 'date': $column_str = $this->quotation($column_name).' BLOB'; break;
				case 'time': $column_str = $this->quotation($column_name).' INTEGER'; break;
				case 'intdate':
				case 'integer': $column_str = $this->quotation($column_name).' INTEGER'; break;
				case 'email': $column_str = $this->quotation($column_name).' TEXT'; break;
				case 'alnum': $column_str = $this->quotation($column_name).' BLOB'; break;
				case 'choice': $column_str = $this->quotation($column_name).' BLOB'; break;
				default: throw new InvalidArgumentException("undefined type `".$column['type']."`");
			}
			$column_str .= (($column['require']) ? ' not' : '').' null ';
			if($column['primary'] || $column['type'] == "serial") $primary[] = $this->quotation($column_name);
			$columndef[] = $column_str;
		}
		$sql .= implode(",",$columndef);
		$sql .= "\n)";
		return Dao::daq($sql);
	}
	/**
	 * @see org.rhaco.storage.db.module.DbcModule#show_columns_sql($dao)
	 */
	public function show_columns_sql(Dao $obj){
		return Dao::daq('PRAGMA table_info(`'. $obj->table(). '`);');
	}
	/**
	 * 日付や真偽値等ほとんどの型に対応できない
	 * @see org.rhaco.storage.db.module.DbcModule#parse_columns($it)
	 */
	public function parse_columns(PDOStatement $it){
		$results = array();
		foreach($it as $value){
			$type = $size = null;
			switch($value["type"]){
				case "INTEGER":
					if($value["pk"] == "1"){
						$type = "serial";
						break;
					}
				case "REAL":
					$type = "number"; break;
				case "TEXT":
					$type = "string"; break;
				case "BLOB":
					$type = "text"; break;
				default:
					$type = "mixied";
			}
			$annotation = "type=".$type.","
							."column=".$value["name"].","
							.(($value["pk"] == "1") ? "primary=true," : "")
							;
			$results[] = (object)array("name"=>$value["name"],
										"default"=>$value["dflt_value"],
										"annotation"=>$annotation);

		}
		return $results;
	}
	/**
	 * @see org.rhaco.storage.db.module.DbcModule#last_insert_id_sql()
	 */
	public function last_insert_id_sql(){
		return Dao::daq('select last_insert_rowid() as last_insert_id;');
	}
}
