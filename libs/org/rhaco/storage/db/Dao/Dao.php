<?php
module('DaoStatementIterator');
import('org.rhaco.storage.db.Dbc');
import('org.rhaco.storage.db.model.Q');
import('org.rhaco.storage.db.model.Daq');
import('org.rhaco.storage.db.model.Column');
import('org.rhaco.storage.db.exception.DaoException');
import('org.rhaco.storage.db.exception.LengthDaoException');
import('org.rhaco.storage.db.exception.RequiredDaoException');
import('org.rhaco.storage.db.exception.UniqueDaoException');
import('org.rhaco.storage.db.exception.NotfoundDaoException');
import('org.rhaco.storage.db.exception.DaoBadMethodCallException');
/**
 * O/R Mapper
 * @author Kazutaka Tokushima
 * @license New BSD License
 */
abstract class Dao extends Object{
	static private $_writable_con_ = false;
	static private $_re_writable_con_ = false;
	static private $_replication_ = array();
	static private $_connection_ = array();
	static private $_dao_ = array();
	static private $_cnt_ = 0;	

	private $_has_hierarchy_ = 1;	
	private $_class_id_;
	private $_hierarchy_;
	private $_dao_exception_;

	private $_database_;
	private $_table_;
	private $_create_ = true;
	private $_update_ = true;
	private $_delete_ = true;
	
	private function dao_exception(){
		if(empty($this->_dao_exception_)) $this->_dao_exception_ = new DaoException();
		return $this->_dao_exception_;
	}
	/**
	 * DBのテーブルからDaoインスタンスを返す
	 * @param string $name テーブル名
	 * @param string $db DB名
	 * @param string $dict インスタンスへセットする値
	 * @return self
	 */
	final static public function instant($name,$db,$dict=null){
		import('org.rhaco.storage.db.model.InstantDao');
		return InstantDao::instance($name,$db,$dict);
		/***
			Dbc::temp("test_1","test_active_mapper_query",array("id"=>"serial","value"=>"string"));			
			Dao::instant("test_active_mapper_query","test_1","value=abc")->save();
			Dao::instant("test_active_mapper_query","test_1","value=def")->save();
			C(Dao::instant("test_active_mapper_query","test_1"))->commit();

			$list = array("abc","def");
			$i = 0;
			foreach(C(Dao::instant("test_active_mapper_query","test_1"))->find() as $obj){
				eq($list[$i],$obj->value());
				$i++;
			}
		*/
	}
	final private function get_connection(){
		$c = get_class($this);
		$this->_database_ = C($c)->anon('database');
		$this->_table_ = C($c)->anon('table');
		$this->_update_ = (C($c)->anon('update',true) === true);
		$this->_create_ = (C($c)->anon('create',true) === true);
		$this->_delete_ = (C($c)->anon('delete',true) === true);

		if(!isset(self::$_replication_[$c]) || !isset(self::$_connection_[$c.$this->get_replication_master_name(false)])){
			if(empty($this->_database_)){
				$p = Lib::package_path($this);
				$pe = explode('.',$p);

				while(!empty($pe)){
					$this->_database_ = implode('.',$pe);
					try{
						self::$_connection_[$c.$this->get_replication_master_name(true)] = Dbc::connection($this->_database_);
						self::$_replication_[$c] = false;
						break;
					}catch(RuntimeException $e){
						try{
							self::$_connection_[$c.$this->get_replication_master_name(false)] = Dbc::connection($this->_database_.$this->get_replication_master_name(false));
							self::$_replication_[$c] = true;
							break;
						}catch(RuntimeException $e){
							array_pop($pe);
							if(empty($pe)) throw new RuntimeException(trans('connection fail `{1}`',$p));
						}
					}
				}
			}else{
				try{
					self::$_connection_[$c.$this->get_replication_master_name(true)] = Dbc::connection($this->_database_);
					self::$_replication_[$c] = false;
				}catch(RuntimeException $e){
					try{
						self::$_connection_[$c.$this->get_replication_master_name(false)] = Dbc::connection($this->_database_.$this->get_replication_master_name(false));
						self::$_replication_[$c] = true;
					}catch(RuntimeException $e){
						throw new RuntimeException(trans('connection fail {1}',$this->_database_));
					}
				}
			}
		}
		if(empty($this->_table_)){
			$table_class = $c;
			$parent_class = get_parent_class($c);
			while(__CLASS__ != $parent_class && !R(new ReflectionClass($parent_class))->isAbstract()){
				$table_class = $parent_class;
				$parent_class = get_parent_class($parent_class);
			}
			$this->_table_ = strtolower($table_class[0]);
			for($i=1;$i<strlen($table_class);$i++) $this->_table_ .= (ctype_lower($table_class[$i])) ? $table_class[$i] : '_'.strtolower($table_class[$i]);
		}
		$this->_table_ = $this->set_table_name($this->_table_);
		$module_class = import($this->connection()->type());
		$this->add_module(new $module_class($this->connection()->encode()));
	}
	final private function get_replication_master_name($master=false){
		return (($master || self::is_replication_master()) ? '#master' : '#slave');
	}
	final private function is_replication_master(){
		return (self::$_writable_con_ || (isset(self::$_replication_[get_class($this)]) && self::$_replication_[get_class($this)] === false));
	}
	final private function set_table_name($name){
		$name = $this->connection()->prefix().$name;
		if($this->connection()->is_upper()) $name = strtoupper($name);
		if($this->connection()->is_lower()) $name = strtolower($name);
		return $name;
	}
	final protected function __new__(){
		$this->get_connection();
		if(method_exists($this,'__new_instance__')){
			$this->__new_instance__();
		}
		if(func_num_args() == 1){
			$props = array_keys(get_object_vars($this));
			foreach(Text::dict(func_get_arg(0)) as $n => $v){
				switch($n){
					case '_has_hierarchy_':
					case '_class_id_':
					case '_hierarchy_':
						$this->{$n} = $v;
						break;
					default:
						if($n[0] != '_' && in_array($n,$props)) $this->{$n}($v);
				}
			}
		}
		$this->parse_column_annotation();
	}
	/***
		#con
		Dbc::temp("test_1","test_dao_init_con",array("id"=>"serial"));
		Dao::instant("test_dao_init_con","test_1")->save();
		$TestDaoInitCon = create_class('
			protected $id;
		','Dao','
			@class @{"database":"test_1","table":"test_dao_init_con"}
			@var serial $id
		');
		$result = C($TestDaoInitCon)->find_all();
		eq(1,sizeof($result));
	 */
	/***
		#has
		Dbc::temp("test_1","test_dao_init_has_parent",array("id"=>"serial","value"=>"string"));
		Dbc::temp("test_1","test_dao_init_has_child",array("id"=>"serial","parent_id"=>"number","value"=>"string"));
		Dbc::temp("test_1","test_dao_init_has_child_two",array("id"=>"serial","parent_id1"=>"number","parent_id2"=>"number","value"=>"string"));
		Dao::instant("test_dao_init_has_parent","test_1","value=parent1")->save();
		Dao::instant("test_dao_init_has_parent","test_1","value=parent2")->save();
		Dao::instant("test_dao_init_has_child","test_1","parent_id=1,value=child")->save();
		Dao::instant("test_dao_init_has_child_two","test_1","parent_id1=1,parent_id2=2,value=child_two")->save();

		$TestDaoInitHasParent = create_class('
			protected $id;
			protected $value;
		','Dao','
			@class @{"database":"test_1","table":"test_dao_init_has_parent"}
			@var serial $id
		');
		$TestDaoInitHasChild = create_class('
			protected $id;
			protected $parent_id;
			protected $value;
			protected $parent;
		','Dao','
			@class @{"database":"test_1","table":"test_dao_init_has_child"}
			@var serial $id
			@var number $parent_id
			@var '.$TestDaoInitHasParent.' $parent @{"cond":"parent_id()id"}
		');
		$result = C($TestDaoInitHasChild)->find_all(Q::order("parent_id"));
		eq(1,sizeof($result));
		foreach($result as $c){ eq(true,($c->parent() instanceof $TestDaoInitHasParent)); }
		eq("parent1",$result[0]->parent()->value());

		$TestDaoInitHasChildTwo = create_class('
			protected $id;
			protected $parent_id1;
			protected $parent_id2;
			protected $value;
			protected $parent1;
			protected $parent2;
		','Dao','
			@class @{"database":"test_1","table":"test_dao_init_has_child_two"}
			@var serial $id
			@var number $parent_id1
			@var number $parent_id2
			@var '.$TestDaoInitHasParent.' $parent1 @{"cond":"parent_id1()id"}
			@var '.$TestDaoInitHasParent.' $parent2 @{"cond":"parent_id2()id"}
		');
		$result = C($TestDaoInitHasChildTwo)->find_all();
		eq(1,sizeof($result));
		foreach($result as $c){ eq(true,($c->parent1() instanceof $TestDaoInitHasParent)); }
		eq("parent1",$result[0]->parent1()->value());
		eq("parent2",$result[0]->parent2()->value());
	 */
	/***
		# ext_has
		Dbc::temp("test_1","test_dao_init_exthas_parent",array("id"=>"serial","pvalue1"=>"string","pvalue2"=>"string"));
		Dbc::temp("test_1","test_dao_init_exthas_child",array("id"=>"serial","parent_id"=>"number","value1"=>"string","value2"=>"string"));
		
		Dao::instant("test_dao_init_exthas_parent","test_1","pvalue1=parent1,pvalue2=parent2")->save();
		Dao::instant("test_dao_init_exthas_child","test_1","parent_id=1,value1=child1,value2=child2")->save();

		$TestDaoInitExthasParent = create_class('
			protected $id;
			protected $pvalue1;
			protected $pvalue2;
		','Dao','
			@class @{"database":"test_1","table":"test_dao_init_exthas_parent"}
			@var serial $id
		');
		$TestDaoInitExthasChild = create_class('
			protected $id;
			protected $parent_id;
			protected $value1;
			protected $value2;
			protected $parent;
		','Dao','
			@class @{"database":"test_1","table":"test_dao_init_exthas_child"}
			@var serial $id
			@var integer $parent_id
			@var '.$TestDaoInitExthasParent.' $parent @{"cond":"parent_id()id"}
		');
		$TestDaoInitExthasChildExt = create_class('
			protected $parent;
		',$TestDaoInitExthasChild,'
			@var '.$TestDaoInitExthasParent.' $parent @{"cond":"parent_id()id"}
		');
		$result = C($TestDaoInitExthasParent)->find_get(Q::order("id"));
		eq("parent1",$result->pvalue1());
		
		$result = C($TestDaoInitExthasChild)->find_all(Q::order("parent_id"));
		eq(1,sizeof($result));
		foreach($result as $c){ eq(true,($c->parent() instanceof $TestDaoInitExthasParent)); }
		eq("parent1",$result[0]->parent()->pvalue1());
		eq("parent2",$result[0]->parent()->pvalue2());
		eq("child1",$result[0]->value1());
		eq("child2",$result[0]->value2());
		
		$result = C($TestDaoInitExthasChildExt)->find_all(Q::order("parent_id"));
		eq(1,sizeof($result));
		foreach($result as $c){ eq(true,($c->parent() instanceof $TestDaoInitExthasParent)); }
		eq("parent1",$result[0]->parent()->pvalue1());
		eq("parent2",$result[0]->parent()->pvalue2());
		eq("child1",$result[0]->value1());
		eq("child2",$result[0]->value2());			
	 */
	/***
		#grand
		Dbc::temp("test_1","test_dao_init_grand",array("id"=>"serial","parent_id"=>"number","value"=>"string"));
		Dao::instant("test_dao_init_grand","test_1","parent_id=0,value=1")->save();
		Dao::instant("test_dao_init_grand","test_1","parent_id=1,value=2")->save();
		Dao::instant("test_dao_init_grand","test_1","parent_id=2,value=3")->save();

		$TestDaoInitGrand = create_class('
			protected $id;
			protected $parent_id;
			protected $value;

			protected $parent_value;
			protected $parent_parent_id;
		','Dao','
			@class @{"database":"test_1","table":"test_dao_init_grand"}
			@var serial $id
			@var number $parent_id
			@var mixed $parent_value @{"column":"value","cond":"parent_id(test_dao_init_grand.id.parent_id,test_dao_init_grand.id)"}
			@var mixed $parent_parent_id @{"column":"parent_id","cond":"@parent_value"}
		');
		$result = C($TestDaoInitGrand)->find_all();
		eq(1,sizeof($result));
	 */
	/***
		#map
		Dbc::temp("test_1","test_dao_init_map_parent",array("id"=>"serial","value"=>"string"));
		Dbc::temp("test_1","test_dao_init_map_child",array("id"=>"serial","value"=>"string"));
		Dbc::temp("test_1","test_dao_init_map_map",array("id"=>"serial","parent_id"=>"number","child_id"=>"number"));
		Dao::instant("test_dao_init_map_parent","test_1","value=parent1")->save();
		Dao::instant("test_dao_init_map_parent","test_1","value=parent2")->save();
		Dao::instant("test_dao_init_map_child","test_1","value=child1")->save();
		Dao::instant("test_dao_init_map_map","test_1","parent_id=1,child_id=1")->save();

		$TestDaoInitMapParent = create_class('
			protected $id;
			protected $value;
		','Dao','
			@class @{"database":"test_1","table":"test_dao_init_map_parent"}
			@var serial $id
		');
		$TestDaoInitMapChild = create_class('
			protected $id;
			protected $value;
			protected $parent;
		','Dao','
			@class @{"database":"test_1","table":"test_dao_init_map_child"}
			@var serial $id
			@var '.$TestDaoInitMapParent.' $parent @{"cond":"id(test_dao_init_map_map.child_id.parent_id)id"}
		');
		$result = C($TestDaoInitMapChild)->find_all();
		eq(1,sizeof($result));
		foreach($result as $c){ eq(true,($c->parent() instanceof $TestDaoInitMapParent)); }
		eq("parent1",$result[0]->parent()->value());
	 */
	/***
		#prepare
		Dbc::temp("test_1","test_dao_init_prepare_1",array("id"=>"serial"));
		Dbc::temp("test_1","test_dao_init_prepare_2",array("id"=>"serial"));
		Dao::instant("test_dao_init_prepare_1","test_1")->save();
		Dao::instant("test_dao_init_prepare_1","test_1")->save();			
		Dao::instant("test_dao_init_prepare_2","test_1")->save();
		Dao::instant("test_dao_init_prepare_2","test_1")->save();

		$TestDaoInitPrepare1 = create_class('
			protected $id;
		','Dao','
			@class @{"database":"test_1","table":"test_dao_init_prepare_1"}
			@var serial $id
		');
		$TestDaoInitPrepare2 = create_class('
			protected $id;
		','Dao','
			@class @{"database":"test_1","table":"test_dao_init_prepare_2"}
			@var serial $id
		');
		$a = $b = 0;
		foreach(C($TestDaoInitPrepare1)->find() as $oa){
			$a++;
			foreach(C($TestDaoInitPrepare2)->find() as $ob) $b++;
		}
		eq(2,$a);
		eq(4,$b);
	 */
	/***
		#many
		Dbc::temp("test_1","test_dao_init_many_parent",array("id"=>"serial","value"=>"string"));
		Dbc::temp("test_1","test_dao_init_many_child",array("id"=>"serial","parent_id"=>"number","value"=>"string"));
		Dao::instant("test_dao_init_many_parent","test_1","value=parent1")->save();
		Dao::instant("test_dao_init_many_parent","test_1","value=parent2")->save();
		Dao::instant("test_dao_init_many_child","test_1","parent_id=1,value=child1-1")->save();
		Dao::instant("test_dao_init_many_child","test_1","parent_id=1,value=child1-2")->save();
		Dao::instant("test_dao_init_many_child","test_1","parent_id=1,value=child1-3")->save();
		Dao::instant("test_dao_init_many_child","test_1","parent_id=2,value=child2-1")->save();
		Dao::instant("test_dao_init_many_child","test_1","parent_id=2,value=child2-2")->save();

		$TestDaoInitManyChild = create_class('
			protected $id;
			protected $parent_id;
			protected $value;
		','Dao','
			@class @{"database":"test_1","table":"test_dao_init_many_child"}
			@var serial $id
			@var number $parent_id
		');
		$TestDaoInitManyParent = create_class('
			protected $id;
			protected $value;
			protected $children;
		','Dao','
			@class @{"database":"test_1","table":"test_dao_init_many_parent"}
			@var serial $id
			@var '.$TestDaoInitManyChild.'[] $children @{"cond":"id()parent_id"}
		');
		$size = array(3,2);
		foreach(C($TestDaoInitManyParent)->find() as $key => $r){
			eq($size[$key],sizeof($r->children()));
		}
		foreach(C($TestDaoInitManyParent)->find_all() as $key => $r){
			eq($size[$key],sizeof($r->children()));
			foreach($r->children() as $child){
				eq(true,($child instanceof $TestDaoInitManyChild));
				eq($key + 1,$child->parent_id());
			}
		}
	*/
	/***
		#extra
		Dbc::temp("test_1","test_dao_init_extra",array("id"=>"serial"));
		$TestDaoInitExtra = create_class('
			protected $id;
			protected $value;
		','Dao','
			@class @{"database":"test_1","table":"test_dao_init_extra"}
			@var serial $id
			@var mixed $value @{"extra":true}
		');
		try{
			$result = C($TestDaoInitExtra)->find_all();
			success();
		}catch(Excepton $e){
			fail();
		}
	 */
	/***
		#join
		Dbc::temp("test_1","test_dao_init_join_clip",array("id"=>"serial"));
		Dao::instant("test_dao_init_join_clip","test_1")->save();
		Dao::instant("test_dao_init_join_clip","test_1")->save();
		Dao::instant("test_dao_init_join_clip","test_1")->save();
		Dao::instant("test_dao_init_join_clip","test_1")->save();
		Dao::instant("test_dao_init_join_clip","test_1")->save();
		Dao::instant("test_dao_init_join_clip","test_1")->save();

		Dbc::temp("test_1","test_dao_init_join_tag",array("id"=>"serial","name"=>"string"));
		Dao::instant("test_dao_init_join_tag","test_1","name=aaa")->save();
		Dao::instant("test_dao_init_join_tag","test_1","name=bbb")->save();

		Dbc::temp("test_1","test_dao_init_join_cliptag",array("id"=>"serial","clip_id"=>"number","tag_id"=>"number"));
		Dao::instant("test_dao_init_join_cliptag","test_1","clip_id=1,tag_id=1")->save();
		Dao::instant("test_dao_init_join_cliptag","test_1","clip_id=2,tag_id=1")->save();
		Dao::instant("test_dao_init_join_cliptag","test_1","clip_id=3,tag_id=1")->save();
		Dao::instant("test_dao_init_join_cliptag","test_1","clip_id=4,tag_id=2")->save();
		Dao::instant("test_dao_init_join_cliptag","test_1","clip_id=4,tag_id=1")->save();
		Dao::instant("test_dao_init_join_cliptag","test_1","clip_id=5,tag_id=2")->save();
		Dao::instant("test_dao_init_join_cliptag","test_1","clip_id=5,tag_id=1")->save();

		$TestDaoInitJoinClip = create_class('
			protected $id;
			protected $tag_name;
		','Dao','
			@class @{"database":"test_1","table":"test_dao_init_join_clip"}
			@var serial $id
			@var string $tag_name @{"join":true,"column":"name","cond":"id(test_dao_init_join_cliptag.clip_id.tag_id,test_dao_init_join_tag.id)"}
		');
		$re = C($TestDaoInitJoinClip)->find_all();
		eq(6,sizeof($re));

		$re = C($TestDaoInitJoinClip)->find_all(Q::eq("tag_name","aaa"));
		eq(5,sizeof($re));

		$re = C($TestDaoInitJoinClip)->find_all(Q::eq("tag_name","bbb"));
		eq(2,sizeof($re));
	*/
	/***
		#cross_db
		Dbc::temp("test_1","test_dao_init_cross",array("id"=>"serial","value"=>"string"));
		Dao::instant("test_dao_init_cross","test_1","value=A")->save();
		Dao::instant("test_dao_init_cross","test_1","value=B")->save();

		Dbc::temp("test_2","test_dao_init_cross_child",array("id"=>"serial","parent_id"=>"number"));
		Dao::instant("test_dao_init_cross_child","test_2","parent_id=1")->save();
		Dao::instant("test_dao_init_cross_child","test_2","parent_id=2")->save();

		$TestDaoInitCross = create_class('
			protected $id;
			protected $value;
		','Dao','
			@class @{"database":"test_1","table":"test_dao_init_cross"}
			@var serial $id
		');
		$TestDaoInitCrossChild = create_class('
			protected $id;
			protected $parent_id;
			protected $parent;
		','Dao','
			@class @{"database":"test_2","table":"test_dao_init_cross_child"}
			@var serial $id
			@var number $parent_id
			@var '.$TestDaoInitCross.' $parent @{"cond":"parent_id()id"}
		');
		$result = array(1=>"A",2=>"B");
		foreach(C($TestDaoInitCrossChild)->find_all() as $o){
			eq(true,is_class($o->parent()));
			eq($result[$o->id()],$o->parent()->value());
		}
	 */
	/***
		# replication
		Dbc::temp("test_4#master","test_dao_init_replication",array("id"=>"serial","value"=>"string"));
		Dbc::temp("test_4#slave","test_dao_init_replication",array("id"=>"serial","value"=>"string"));

		$TestDaoInitReplication = create_class('
			protected $id;
			protected $value;
		','Dao','
			@class @{"database":"test_4","table":"test_dao_init_replication"}
			@var serial $id
		');
		$result = C($TestDaoInitReplication)->find_all();
		eq(0,sizeof($result));

		try{
			$obj = new $TestDaoInitReplication();
			$obj->value("hoge");
			$obj->save();
		}catch(DaoBadMethodCallException $e){
			success();
		}
		
		self::begin_write();		
		try{
			$obj = new $TestDaoInitReplication();
			$obj->value("hoge");
			$obj->save();
			success();
		}catch(DaoBadMethodCallException $e){
			fail();
		}
		self::end_write();
		
		$result = C($TestDaoInitReplication)->find_all();
		eq(0,sizeof($result));

		self::begin_write();
		$result = C($TestDaoInitReplication)->find_all();
		if(eq(1,sizeof($result))){
			eq("hoge",$result[0]->value());
	
			try{
				$result[0]->value("fuga");
				$result[0]->save();
				eq("fuga",$result[0]->value());
			}catch(DaoBadMethodCallException $e){
				fail();
			}
		}
		self::end_write();
	 */
	/***
		# save_master
		Dbc::temp("test_4#master","test_dao_m",array("id"=>"serial","value"=>"string"));
		
		self::begin_write();
		
		Dao::instant("test_dao_m","test_4","value=hoge")->save();

		$TestDaoM = create_class('
			protected $id;
			protected $value;
		','Dao','
			@class @{"database":"test_4","table":"test_dao_m"}
			@var serial $id
		');

		$result = C($TestDaoM)->find_all();
		eq(1,sizeof($result));
		try{
			$result[0]->value("abc");
			$result[0]->save();
			success();
		}catch(DaoBadMethodCallException $e){
			fail();
		}
	*/
	/***
		# save_master_many
		Dbc::temp("test_4#master","test_dao_m_many_parent",array("id"=>"serial","value"=>"string"));
		Dbc::temp("test_4#master","test_dao_m_many_child",array("id"=>"serial","parent_id"=>"number","value"=>"string"));
		Dbc::temp("test_4#slave","test_dao_m_many_parent",array("id"=>"serial","value"=>"string"));
		Dbc::temp("test_4#slave","test_dao_m_many_child",array("id"=>"serial","parent_id"=>"number","value"=>"string"));
		
		self::begin_write();
		Dao::instant("test_dao_m_many_parent","test_4","value=parent1")->save();
		Dao::instant("test_dao_m_many_parent","test_4","value=parent2")->save();
		Dao::instant("test_dao_m_many_child","test_4","parent_id=1,value=child1-1")->save();
		Dao::instant("test_dao_m_many_child","test_4","parent_id=1,value=child1-2")->save();
		Dao::instant("test_dao_m_many_child","test_4","parent_id=1,value=child1-3")->save();
		Dao::instant("test_dao_m_many_child","test_4","parent_id=2,value=child2-1")->save();
		Dao::instant("test_dao_m_many_child","test_4","parent_id=2,value=child2-2")->save();

		$TestDaoMManyChild = create_class('
			protected $id;
			protected $parent_id;
			protected $value;
		','Dao','
			@class @{"database":"test_4","table":"test_dao_m_many_child"}
			@var serial $id
			@var number $parent_id
		');
		$TestDaoMManyParent = create_class('
			protected $id;
			protected $value;
			protected $children;
		','Dao','
			@class @{"database":"test_4","table":"test_dao_m_many_parent"}
			@var serial $id
			@var '.$TestDaoMManyChild.'[] $children @{"cond":"id()parent_id"}
		');

		self::end_write();

		$result = C($TestDaoMManyParent)->find_all();
		eq(0,sizeof($result));

		self::begin_write();		
		$result = C($TestDaoMManyParent)->find_all();
		eq(2,sizeof($result));			

		$size = array(3,2);
		foreach(C($TestDaoMManyParent)->find() as $key => $r){
			eq($size[$key],sizeof($r->children()));
			try{
				$r->value("aa");
				$r->save();
				success();
			}catch(DaoBadMethodCallException $e){
				eq(null,$e->getMessage());
				fail();
			}
		}
		foreach(C($TestDaoMManyParent)->find_all() as $key => $r){
			eq($size[$key],sizeof($r->children()));
			foreach($r->children() as $child){
				eq(true,($child instanceof $TestDaoMManyChild));
				eq($key + 1,$child->parent_id());
				
				try{
					$child->value("aaa");
					$child->save();
					success();
				}catch(DaoBadMethodCallException $e){
					fail();
				}
			}
		}
		self::end_write();
	*/
	/***
		# save_slave
		Dbc::temp("test_4#master","test_dao_s_save",array("id"=>"serial","value"=>"string"));
		Dbc::temp("test_4#slave","test_dao_s_save",array("id"=>"serial","value"=>"string"));

		$TestDaoSSave = create_class('
			protected $id;
			protected $value;
		','Dao','
			@class @{"database":"test_4","table":"test_dao_s_save"}	
			@var serial $id
		');

		self::begin_write();
		try{
			$obj = new $TestDaoSSave();
			$obj->value("hoge");
			$obj->save();
			success();
		}catch(DaoBadMethodCallException $e){
			fail();
		}		
		$result = C($TestDaoSSave)->find_all();
		eq(1,sizeof($result));
		self::end_write();

		$result = C($TestDaoSSave)->find_all();
		eq(0,sizeof($result));

		self::begin_write();
		C($TestDaoSSave)->commit();
		self::end_write();

		try{
			$obj = new $TestDaoSSave();
			$obj->value("hoge");
			$obj->save();
			fail();
		}catch(DaoBadMethodCallException $e){
			success();			
		}
	*/
	/***
		# time
		Dbc::temp("test_1","test_dao_time",array("id"=>"serial","ts"=>"timestamp","date"=>"date","idate"=>"integer"));
		$TestDaoTime = create_class('
			protected $id;
			protected $ts;
			protected $date;
			protected $idate;
		','Dao','
			@class @{"database":"test_1","table":"test_dao_time"}
			@var serial $id
			@var timestamp $ts
			@var date $date
			@var intdate $idate
		');
		$obj = new $TestDaoTime();
		eq(null,$obj->ts());
		eq(null,$obj->date());
		eq(null,$obj->idate());
		$obj->save();

		foreach(C($TestDaoTime)->find() as $o){
			eq(null,$o->ts());
			eq(null,$o->date());
			eq(null,$o->idate());
		}	 
	 */
	/***
		# auto_now_add_time
		Dbc::temp("test_1","test_dao_require_time",array("id"=>"serial","ts"=>"timestamp","date"=>"timestamp","idate"=>"number"));
		$TestDaoRequireTime = create_class('
			protected $id;
			protected $ts;
			protected $date;
			protected $idate;
		','Dao','
			@class @{"database":"test_1","table":"test_dao_require_time"}
			@var serial $id
			@var timestamp $ts @{"auto_now_add":true}
			@var date $date @{"auto_now_add":true}
			@var intdate $idate @{"auto_now_add":true}
		');
		$obj = new $TestDaoRequireTime();
		eq(null,$obj->ts());
		eq(null,$obj->date());
		eq(null,$obj->idate());
		$obj->save();

		foreach(C($TestDaoRequireTime)->find() as $o){
			neq(null,$o->ts());
			neq(null,$o->date());
			neq(null,$o->idate());
		}
	 */
	/***
		# auto_now_time
		Dbc::temp("test_1","test_dao_auto_now_time",array("id"=>"serial","ts"=>"timestamp","date"=>"timestamp","idate"=>"number"));
		$TestDaoAutonowTime = create_class('
			protected $id;
			protected $ts;
			protected $date;
			protected $idate;
		','Dao','
			@class @{"database":"test_1","table":"test_dao_auto_now_time"}
			@var serial $id
			@var timestamp $ts @{"auto_now":true}
			@var date $date @{"auto_now":true}
			@var intdate $idate @{"auto_now":true}
		');
		$obj = new $TestDaoAutonowTime();
		$obj->save();

		foreach(C($TestDaoAutonowTime)->find() as $o){
			neq(null,$o->ts());
			neq(null,$o->date());
			neq(null,$o->idate());
		}
	 */
	/***
		# auto_code_add_string
		Dbc::temp("test_1","test_dao_auto_code_add",array("id"=>"serial","code1"=>"string","code2"=>"string","code3"=>"string"));
		$TestDaoAutoCode = create_class('
			protected $id;
			protected $code1;
			protected $code2;
			protected $code3;
		','Dao','
			@class @{"database":"test_1","table":"test_dao_auto_code_add"}
			@var serial $id
			@var string $code1 @{"auto_code_add":true}
			@var string $code2 @{"auto_code_add":true,"max":10}
			@var string $code3 @{"auto_code_add":true,"max":40}
		');
		C($TestDaoAutoCode)->find_delete();
		
		$obj = new $TestDaoAutoCode();
		eq(null,$obj->code1());
		eq(null,$obj->code2());
		eq(null,$obj->code3());
		$obj->save();

		foreach(C($TestDaoAutoCode)->find() as $o){
			neq(null,$o->code1());
			neq(null,$o->code2());
			neq(null,$o->code3());
			eq(32,strlen($o->code1()));
			eq(10,strlen($o->code2()));
			eq(40,strlen($o->code3()));
		}
		
		$TestDaoAutoCodeDigit = create_class('
		',$TestDaoAutoCode,'
			@var string $code1 @{"auto_code_add":true,"ctype":"digit"}
			@var string $code2 @{"auto_code_add":true,"max":10,"ctype":"digit"}
			@var string $code3 @{"auto_code_add":true,"max":40,"ctype":"digit"}
		');
		C($TestDaoAutoCodeDigit)->find_delete();
		
		$obj = new $TestDaoAutoCodeDigit();
		eq(null,$obj->code1());
		eq(null,$obj->code2());
		eq(null,$obj->code3());
		$obj->save();

		foreach(C($TestDaoAutoCodeDigit)->find() as $o){
			neq(null,$o->code1());
			neq(null,$o->code2());
			neq(null,$o->code3());
			eq(32,strlen($o->code1()));
			eq(10,strlen($o->code2()));
			eq(40,strlen($o->code3()));
			eq(true,ctype_digit($o->code1()));
			eq(true,ctype_digit($o->code2()));
			eq(true,ctype_digit($o->code3()));
		}
		$TestDaoAutoCodeAlpha = create_class('
		',$TestDaoAutoCode,'
			@var string $code1 @{"auto_code_add":true,"ctype":"alpha"}
			@var string $code2 @{"auto_code_add":true,"max":10,"ctype":"alpha"}
			@var string $code3 @{"auto_code_add":true,"max":40,"ctype":"alpha"}
		');
		C($TestDaoAutoCodeAlpha)->find_delete();
		
		$obj = new $TestDaoAutoCodeAlpha();
		eq(null,$obj->code1());
		eq(null,$obj->code2());
		eq(null,$obj->code3());
		$obj->save();

		foreach(C($TestDaoAutoCodeAlpha)->find() as $o){
			neq(null,$o->code1());
			neq(null,$o->code2());
			neq(null,$o->code3());
			eq(32,strlen($o->code1()));
			eq(10,strlen($o->code2()));
			eq(40,strlen($o->code3()));
			eq(true,ctype_alpha($o->code1()));
			eq(true,ctype_alpha($o->code2()));
			eq(true,ctype_alpha($o->code3()));
		}
	 */
	/***
		# __setup__
		self::end_write();
	 */
	/***
		# __teardown__
		self::end_write();
	 */	
	/**
	 * 書き込み可能ブロック開始
	 * @param boolean $force 
	 */
	final static public function begin_write($force=true){
		if(!$force) self::$_re_writable_con_ = self::$_writable_con_;
		self::$_writable_con_ = true;
		Log::debug(($force ? 'force ' : null).'begin write');
	}
	/**
	 * 書き込み可能ブロック終了
	 * @param boolean $force 
	 */
	final static public function end_write($force=true){
		self::$_writable_con_ = ($force) ? false : self::$_re_writable_con_;
		Log::debug(($force ? 'force ' : null).'end write');
	}
	final private function parse_column_annotation(){
		$class = get_class($this);
		if(!isset($this->_class_id_)) $this->_class_id_ = $class;
		if(isset(self::$_dao_[$this->_class_id_])){
			foreach(self::$_dao_[$this->_class_id_]->_has_dao_ as $name => $dao) $this->{$name}($dao);
			return;
		}
		$has_hierarchy = (isset($this->_hierarchy_)) ? $this->_hierarchy_ - 1 : $this->_has_hierarchy_;
		$root_table_alias = 't'.self::$_cnt_++;
		$_columns_ = $_self_columns_ = $_where_columns_ = $_conds_ = $_join_conds_ = $_alias_ = $_has_many_conds_ = $_has_dao_ = array();
	
		foreach($this->props() as $name){
			if($this->a($name,'extra') !== true){
				$anon_cond = $this->a($name,'cond');
				$column_type = $this->a($name,'type');

				$column = new Column();
				$column->name($name);
				$column->column($this->a($name,'column',$name));
				$column->column_alias('c'.self::$_cnt_++);
				if($anon_cond === null){
					if(class_exists($column_type) && is_subclass_of($column_type,__CLASS__)) throw new DaoException(trans("undef {1} annotation 'cond'",$name));
					$column->table($this->_table_);
					$column->table_alias($root_table_alias);
					$column->primary($this->a($name,'primary',false));
					$column->auto($column_type === 'serial');
					$_columns_[] = $column;
					$_self_columns_[$name] = $column;
					$_alias_[$column->column_alias()] = $name;
				}else if(false !== strpos($anon_cond,'(')){
					$is_has = (class_exists($column_type) && is_subclass_of($column_type,__CLASS__));
					$is_has_many = ($is_has && $this->a($name,'attr') === 'a');
					if((!$is_has || $has_hierarchy > 0) && preg_match("/^(.+)\((.*)\)(.*)$/",$anon_cond,$match)){
						list(,$self_var,$conds_string,$has_var) = $match;
						list($self_var,$conds_string,$has_var) = Text::trim($self_var,$conds_string,$has_var);
						$conds = array();
						$ref_table = $ref_table_alias = null;

						if(!empty($conds_string)){
							foreach(explode(',',$conds_string) as $key => $cond){
								$tcc = explode('.',$cond,3);
								if(sizeof($tcc) === 3){
									list($t,$c1,$c2) = $tcc;
									$ref_table = $this->set_table_name($t);
									$ref_table_alias = 't'.self::$_cnt_++;
									$conds[] = Column::cond_instance($c1,'c'.self::$_cnt_++,$ref_table,$ref_table_alias);
									$conds[] = Column::cond_instance($c2,'c'.self::$_cnt_++,$ref_table,$ref_table_alias);
								}else{
									list($t,$c1) = $tcc;
									$ref_table = $this->set_table_name($t);
									$ref_table_alias = 't'.self::$_cnt_++;
									$conds[] = Column::cond_instance($c1,'c'.self::$_cnt_++,$ref_table,$ref_table_alias);
								}
							}
						}
						if($is_has_many){
							$dao = new $column_type(('_class_id_='.$class.'___'.self::$_cnt_++));
							$_has_many_conds_[$name] = array($dao,$has_var,$self_var);
						}else{
							$self_db = true;
							if($is_has){
								$dao = new $column_type(('_class_id_='.$class.'___'.self::$_cnt_++).',_hierarchy_='.$has_hierarchy);
								$this->{$name}($dao);
								if($dao->database() == $this->database()){
									$_has_dao_[$name] = $dao;
									$_columns_ = array_merge($_columns_,$dao->columns());
									$_conds_ = array_merge($_conds_,$dao->conds());
									$this->a($name,'has',true,true);
									foreach($dao->columns() as $column) $_alias_[$column->column_alias()] = $name;
									$has_column = $dao->base_column($dao->columns(),$has_var);
									$conds[] = Column::cond_instance($has_column->column(),'c'.self::$_cnt_++,$has_column->table(),$has_column->table_alias());
								}else{
									$_has_many_conds_[$name] = array($dao,$has_var,$self_var);
									$self_db = false;
								}
							}else{
								$column->table($ref_table);
								$column->table_alias($ref_table_alias);
								if(!$this->a($name,'join',false)) $_columns_[] = $column;
								$_where_columns_[$name] = $column;
								$_alias_[$column->column_alias()] = $name;
							}
							if($self_db){
								array_unshift($conds,Column::cond_instance($self_var,'c'.self::$_cnt_++,$this->_table_,$root_table_alias));
								if(sizeof($conds) % 2 != 0) throw new DaoException(trans('{1}[{2}] is illegal condition',$name,$column_type));
								if($this->a($name,'join',false)){
									$this->a($name,'hash',false,true);
									$this->a($name,'get',false,true);
									$this->a($name,'set',false,true);
									for($i=0;$i<sizeof($conds);$i+=2) $_join_conds_[$name][] = array($conds[$i],$conds[$i+1]);
								}else{
									for($i=0;$i<sizeof($conds);$i+=2) $_conds_[] = array($conds[$i],$conds[$i+1]);
								}
							}
						}
					}
				}else if($anon_cond[0] === '@'){
					$c = $this->base_column($_columns_,substr($anon_cond,1));
					$column->table($c->table());
					$column->table_alias($c->table_alias());
					$_columns_[] = $column;
					$_where_columns_[$name] = $column;
					$_alias_[$column->column_alias()] = $name;
				}
			}
		}
		self::$_dao_[$this->_class_id_] = (object)array(
														'_columns_'=>$_columns_,
														'_self_columns_'=>$_self_columns_,
														'_where_columns_'=>$_where_columns_,
														'_conds_'=>$_conds_,
														'_join_conds_'=>$_join_conds_,
														'_alias_'=>$_alias_,
														'_has_dao_'=>$_has_dao_,
														'_has_many_conds_'=>$_has_many_conds_
														);
	}
	final private function base_column($_columns_,$name){
		foreach($_columns_ as $c){
			if($c->is_base() && $c->name() === $name) return $c;
		}
		throw new DaoException(trans('undef var {1}',$name));
	}
	/**
	 * 全てのColumnの一覧を取得する
	 * @return Column[]
	 */
	final public function columns(){
		return self::$_dao_[$this->_class_id_]->_columns_;
		/***
			Dbc::temp("test_1","test_dao_columns",array("id"=>"serial","value"=>"string"));
			$TestDaoColumns = create_class('
				protected $id;
				protected $value;
			','Dao','
				@class @{"database":"test_1","table":"test_dao_columns"}
				@var serial $id
			');
			$obj = new $TestDaoColumns();
			$columns = $obj->columns();
			eq(2,sizeof($columns));
			foreach($columns as $column){
				eq(true,($column instanceof Column));
			}
		 */
	}
	/**
	 * 主のColumnの一覧を取得する
	 * @return Column[]
	 */
	final public function self_columns($all=false){
		if($all) return array_merge(self::$_dao_[$this->_class_id_]->_where_columns_,self::$_dao_[$this->_class_id_]->_self_columns_);
		return self::$_dao_[$this->_class_id_]->_self_columns_;
	}
	/**
	 * primaryのColumnの一覧を取得する
	 * @return Column[]
	 */
	final public function primary_columns(){
		$result = array();
		foreach(self::$_dao_[$this->_class_id_]->_self_columns_ as $column){
			if($column->primary()) $result[$column->name()] = $column;
		}
		return $result;
	}
	/**
	 * 必須の条件を取得する
	 * @return array array(Column,Column)
	 */
	final public function conds(){
		return self::$_dao_[$this->_class_id_]->_conds_;
	}
	/**
	 * join時の条件を取得する
	 * @return array array(Column,Column)
	 */
	final public function join_conds($name){
		return (isset(self::$_dao_[$this->_class_id_]->_join_conds_[$name])) ? self::$_dao_[$this->_class_id_]->_join_conds_[$name] : array();
	}
	/**
	 * 結果配列から値を自身にセットする
	 * @param $resultset array
	 * @return integer
	 */
	final public function parse_resultset($resultset){
		foreach($resultset as $alias => $value){
			if(isset(self::$_dao_[$this->_class_id_]->_alias_[$alias])){
				if(self::$_dao_[$this->_class_id_]->_alias_[$alias] == 'ref1') $this->a(self::$_dao_[$this->_class_id_]->_alias_[$alias],'has',true);

				if($this->a(self::$_dao_[$this->_class_id_]->_alias_[$alias],'has') === true){
					$this->{self::$_dao_[$this->_class_id_]->_alias_[$alias]}()->parse_resultset(array($alias=>$value));
				}else{
					$this->{self::$_dao_[$this->_class_id_]->_alias_[$alias]}($value);
				}
			}
		}
		if(!empty(self::$_dao_[$this->_class_id_]->_has_many_conds_)){
			foreach(self::$_dao_[$this->_class_id_]->_has_many_conds_ as $name => $conds){
				foreach(C($conds[0])->find(Q::eq($conds[1],$this->{$conds[2]}())) as $dao) $this->{$name}($dao);
			}
		}
		return $this->__resultset_key__();
	}
	protected function __resultset_key__(){
		return null;
	}
	/**
	 * テーブル名を取得
	 * @return string
	 */
	final public function table(){
		return $this->_table_;
	}
	/**
	 * 接続するDB名を取得
	 */
	final public function database(){
		return $this->_database_;
	}
	/**
	 * 接続情報を返す
	 *
	 * @return Db
	 */
	final public function connection(){
		return self::$_connection_[get_class($this).$this->get_replication_master_name(false)];
	}
	protected function __find_conds__(){
		return Q::b();
	}
	protected function __save_verify__(){}
	protected function __create_verify__(){}
	protected function __update_verify__(){}
	protected function __delete_verify__(){}
	protected function __before_save__(){}
	protected function __after_save__(){}
	protected function __before_create__(){}
	protected function __after_create__(){}
	protected function __before_update__(){}
	protected function __after_update__(){}
	protected function __after_delete__(){}
	protected function __before_delete__(){}

	/**
	 * SQL文を実行する準備を行う
	 * @param Daq $daq
	 * @throws DaoException
	 */
	final protected function prepare($daq){
		Log::debug($daq->sql(),$daq->ar_vars());
		$statement = $this->connection()->prepare($daq->sql());
		if($statement === false) throw new DaoException(trans('prepare fail: {1}',$daq->sql()));
		return $statement;
	}
	final private function query(Daq $daq){
		$statement = $this->prepare($daq);
		$statement->execute($daq->ar_vars());
		return $statement;
	}
	final private function update_query(Daq $daq){
		$statement = $this->query($daq);
		$errors = $statement->errorInfo();
		if(isset($errors[1])){
			C($this)->rollback();
			throw new DaoException('['.$errors[1].'] '.(isset($errors[2]) ? $errors[2] : ''));
		}
		return $statement->rowCount();
	}
	final private function func_query(Daq $daq,$is_list=false){
		$statement = $this->query($daq);
		$errors = $statement->errorInfo();
		if(isset($errors[1])){
			C($this)->rollback();
			throw new DaoException('['.$errors[1].'] '.(isset($errors[2]) ? $errors[2] : '').PHP_EOL.'( '.$daq->sql().' )');
		}
		if($statement->columnCount() == 0) return ($is_list) ? array() : null;
		return ($is_list) ? $statement->fetchAll(PDO::FETCH_ASSOC) : $statement->fetchAll(PDO::FETCH_COLUMN,0);
	}
	final private function save_verify_primary_unique(){
		$q = new Q();
		$primary = false;
		foreach($this->primary_columns() as $column){
			$value = $this->{$column->name()}();
			if($this->a($column->name(),'type') === 'serial'){
				$primary = false;
				break;
			}
			$q->add(Q::eq($column->name(),$value));
			$primary = true;
		}
		if($primary && C($this)->find_count($q) > 0) $this->dao_exception()->add(new UniqueDaoException(trans('primary unique')));
		/***
		 	# primary
			Dbc::temp("test_1","test_dao_save_verify_primary",array("id1"=>"number","id2"=>"number","value"=>"string"));

			$TestDaoSaveVerifyPrimary = create_class('
				protected $id1;
				protected $id2;
				protected $value;
			','Dao','
				@class @{"database":"test_1","table":"test_dao_save_verify_primary"}
				@var number $id1 @{"primary":true}
				@var number $id2 @{"primary":true}
			');
			$obj = new $TestDaoSaveVerifyPrimary();
			$obj->id1(1);
			$obj->id2(1);
			try{
				$obj->save();
				success();
			}catch(Exceptions $e){
				fail();
			}
			$obj = new $TestDaoSaveVerifyPrimary();
			$obj->id1(1);
			$obj->id2(1);

			eq(1,C($obj)->find_count());
		 */
	}
	/**
	 * 値の妥当性チェックを行う
	 */
	final public function validate(){
		foreach($this->self_columns() as $name => $column){
			$value = $this->{$name}();
			$label = $this->{'label_'.$name}();
			$e_require = false;

			if($this->a($name,'require') === true && ($value === '' || $value === null)){
				$this->dao_exception()->add(new RequiredDaoException(trans('{1} required',$label)),$name);
				$e_require = true;
			}
			$unique_together = $this->a($name,'unique_together');
			if($value !== '' && $value !== null && ($this->a($name,'unique') === true || !empty($unique_together))){
				$unique = $this->a($name,'unique');
				$uvalue = $value;
				$q = array(Q::eq($name,$uvalue));
				if(!empty($unique_together)){
					foreach((is_array($unique_together) ? $unique_together : array($unique_together)) as $c){
						$q[] = Q::eq($c,$this->{$c}());
					}
				}
				foreach($this->primary_columns() as $column){
					if(null !== ($pv = $this->{$column->name()})) $q[] = Q::neq($column->name(),$this->{$column->name()});
				}
				if(0 < call_user_func_array(array(C($this),'find_count'),$q)) $this->dao_exception()->add(new UniqueDaoException(trans('{1} unique',$label)),$name);
			}
			$master = $this->a($name,'master');
			if(!empty($master)){
				$primarys = R($master)->primary_columns();
				if(empty($primarys) || 0 === C($master)->find_count(Q::eq(key($primarys),$this->{$name}))) $this->dao_exception()->add(new NotfoundDaoException(trans('{1} master not found',$label)),$name);
			}
			if(!$e_require && $value !== null){
				switch($this->a($name,'type')){
					case 'number':
					case 'integer':
						if($this->a($name,'min') !== null && (float)$this->a($name,'min') > $value) $this->dao_exception()->add(new LengthDaoException(trans('{1} less than minimum',$label)),$name);
						if($this->a($name,'max') !== null && (float)$this->a($name,'max') < $value) $this->dao_exception()->add(new LengthDaoException(trans('{1} exceeds maximum',$label)),$name);
						break;
					case 'text':
					case 'string':
					case 'alnum':
						if($this->a($name,'min') !== null && (int)$this->a($name,'min') > mb_strlen($value)) $this->dao_exception()->add(new LengthDaoException(trans('{1} less than minimum',$label)),$name);
						if($this->a($name,'max') !== null && (int)$this->a($name,'max') < mb_strlen($value)) $this->dao_exception()->add(new LengthDaoException(trans('{1} exceeds maximum',$label)),$name);
						break;
				}
			}
			if($this->{'verify_'.$column->name()}() === false){
				$this->dao_exception()->add(new DaoException(trans('{1} verify fail',$this->a($column->name(),'label'))),$column->name());
			}
		}
		$this->dao_exception()->throw_over();
		/***
		 	# minmax
			Dbc::temp("test_1","test_dao_save_verify",array("id"=>"serial","value1"=>"string","value2"=>"number"));

			$TestDaoSaveVerify = create_class('
				protected $id;
				protected $value1;
				protected $value2;
			','Dao','
				@class @{"database":"test_1","table":"test_dao_save_verify"}
				@var serial $id
				@var string $value1 @{"max":3,"min":2}
				@var number $value2 @{"max":3,"min":2}
			');
			$obj = new $TestDaoSaveVerify();
			$obj->value1("123");
			$obj->value2(3);
			try{
				$obj->save();
				success();
			}catch(DaoExceptions $e){
				fail();
			}
			
			$obj = new $TestDaoSaveVerify();
			$obj->value1("1234");
			$obj->value2(4);
			try{
				$obj->save();
				fail();
			}catch(DaoExceptions $e){
				success();
			}
			
			$obj = new $TestDaoSaveVerify();
			$obj->value1("1");
			$obj->value2(1);
			try{
				$obj->save();
				fail();
			}catch(DaoExceptions $e){
				success();
			}
			
			$obj = new $TestDaoSaveVerify();
			try{
				eq(null,$obj->value1());
				eq(null,$obj->value2());
				$obj->save();
				success();
			}catch(DaoExceptions $e){
				fail();
			}
		 */
		/***
		 	# unique
			Dbc::temp("test_1","test_dao_validate_unique",array("id1"=>"serial","u1"=>"number","u2"=>"number"));
			$TestDaoValidateUnique = create_class('
				protected $id1;
				protected $u1;
				protected $u2;
			','Dao','
				@class @{"database":"test_1","table":"test_dao_validate_unique"}
				@var serial $id1
				@var integer $u1 @{"unique_together":"u2"}
				@var integer $u2
			');
			$obj = new $TestDaoValidateUnique();
			$obj->u1(2);
			$obj->u2(3);
			try{
				$obj->save();
				success();
			}catch(DaoExceptions $e){
				fail();
			}
			
			$obj = new $TestDaoValidateUnique();
			$obj->u1(2);
			$obj->u2(3);
			try{
				$obj->save();
				fail();
			}catch(DaoExceptions $e){
				success();
			}
			$obj = new $TestDaoValidateUnique();
			$obj->u1(2);
			$obj->u2(4);
			try{
				$obj->save();
				success();
			}catch(DaoExceptions $e){
				fail();
			}
		 */
		/***
		 	# unique_tri
			Dbc::temp("test_1","test_dao_validate_unique_tri",array("id1"=>"serial","u1"=>"number","u2"=>"number","u3"=>"number"));
			$TestDaoValidateUniqueTri = create_class('
				protected $id1;
				protected $u1;
				protected $u2;
				protected $u3;
			','Dao','
				@class @{"database":"test_1","table":"test_dao_validate_unique_tri"}
				@var serial $id1
				@var integer $u1 @{"unique_together":["u2","u3"]}
				@var integer $u2
				@var integer $u3
			');
			$obj = new $TestDaoValidateUniqueTri();
			$obj->u1(2);
			$obj->u2(3);
			$obj->u3(4);
			try{
				$obj->save();
				success();
			}catch(DaoExceptions $e){
				fail();
			}
			
			$obj = new $TestDaoValidateUniqueTri();
			$obj->u1(2);
			$obj->u2(3);
			$obj->u3(4);
			try{
				$obj->save();
				fail();
			}catch(DaoExceptions $e){
				success();
			}
			$obj = new $TestDaoValidateUniqueTri();
			$obj->u1(2);
			$obj->u2(4);
			$obj->u3(4);
			try{
				$obj->save();
				success();
			}catch(DaoExceptions $e){
				fail();
			}
		 */
	}
	final private function which_aggregator($exe,array $args,$is_list=false){
		$target_name = $gorup_name = array();
		if(isset($args[0]) && is_string($args[0])){
			$target_name = array_shift($args);
			if(isset($args[0]) && is_string($args[0])) $gorup_name = array_shift($args);
		}
		$query = new Q();
		if(!empty($args)) call_user_func_array(array($query,'add'),$args);
		$daq = $this->call_module($exe.'_sql',$this,$target_name,$gorup_name,$query);
		return $this->func_query($daq,$is_list);
	}
	final private function exec_aggregator($exec,$target_name,$args,$format=true){
		$dao = R($this->get_called_class());
		$args[] = $dao->__find_conds__();
		$result = $dao->which_aggregator($exec,$args);
		$current = current($result);
		if($format){
			$dao->{$target_name}($current);
			$current = $dao->{$target_name}();
		}
		return $current;
	}
	final private function exec_aggregator_by($exec,$target_name,$gorup_name,$args){
		if(empty($target_name) || !is_string($target_name)) throw new DaoException(trans('undef target_name'));
		if(empty($gorup_name) || !is_string($gorup_name)) throw new DaoException(trans('undef group_name'));
		$dao = R($this->get_called_class());
		$args[] = $dao->__find_conds__();
		$results = array();
		foreach($dao->which_aggregator($exec,$args,true) as $key => $value){
			$dao->{$target_name}($value['target_column']);
			$dao->{$gorup_name}($value['key_column']);
			$results[$dao->{$gorup_name}()] = $dao->{$target_name}();
		}
		return $results;
	}
	/**
	 * カウントを取得する
	 * @paaram string $target_name 対象となるプロパティ
	 * @return integer
	 */
	final public function find_count($target_name=null){
		$args = func_get_args();
		return (int)$this->exec_aggregator('count',$target_name,$args,false);
		/***
			Dbc::temp("test_1","test_dao_size",array("id"=>"serial","type"=>"number","value"=>"string"));
			$TestDaoSize = create_class('
				protected $id;
				protected $type;
				protected $value;
			','Dao','
				@class @{"database":"test_1","table":"test_dao_size"}
				@var serial $id
				@var number $type
			');
			R(new $TestDaoSize("value=abc,type=1"))->save();
			R(new $TestDaoSize("value=def,type=2"))->save();
			R(new $TestDaoSize("value=ghi,type=2"))->save();

			eq(3,C($TestDaoSize)->find_count("id"));
			eq(3,C($TestDaoSize)->find_count("value"));
			eq(1,C($TestDaoSize)->find_count(Q::eq("value","abc")));
			eq(2,C($TestDaoSize)->find_count(
				Q::ob(
					Q::b(Q::eq("value","abc")),
					Q::b(Q::eq("value","def"))
				)
			));
		 */
	}
	/**
	 * グルーピングしてカウントを取得する
	 *
	 * @param string $target_name 対象となるプロパティ
	 * @param string $gorup_name グルーピングするプロパティ名
	 * @return integer{}
	 */
	final public function find_count_by($target_name,$gorup_name){
		$args = func_get_args();
		return $this->exec_aggregator_by('count',$target_name,$gorup_name,$args);
		/***
			Dbc::temp("test_1","test_dao_count_by",array("id"=>"serial","type"=>"number","value"=>"string"));
			$TestDaoCountBy = create_class('
				protected $id;
				protected $type;
				protected $value;
			','Dao','
				@class @{"database":"test_1","table":"test_dao_count_by"}
				@var serial $id
				@var number $type
			');
			R(new $TestDaoCountBy("value=abc,type=1"))->save();
			R(new $TestDaoCountBy("value=def,type=2"))->save();
			R(new $TestDaoCountBy("value=ghi,type=2"))->save();
			R(new $TestDaoCountBy("value=abc,type=1"))->save();
			R(new $TestDaoCountBy("value=abc,type=1"))->save();
			eq(array(1=>3,2=>2),C($TestDaoCountBy)->find_count_by("id","type"));

			$result = C($TestDaoCountBy)->find_count_by("type","value");
			if(isset($result["abc"]) && $result["abc"] == 3){
				success();
			}else{
				fail();
			}
			if(isset($result["def"]) && $result["def"] == 1){
				success();
			}else{
				fail();
			}
			if(isset($result["ghi"]) && $result["ghi"] == 1){
				success();
			}else{
				fail();
			}
		 */
	}
	/**
	 * 合計を取得する
	 *
	 * @param string $target_name 対象となるプロパティ
	 * @return number
	 */
	final public function find_sum($target_name){
		$args = func_get_args();
		return $this->exec_aggregator('sum',$target_name,$args);
		/***
			Dbc::temp("test_1","test_dao_sum",array("id"=>"serial","price"=>"number","type"=>"number"));
			Dao::instant("test_dao_sum","test_1","price=20,type=2")->save();
			Dao::instant("test_dao_sum","test_1","price=20,type=2")->save();
			Dao::instant("test_dao_sum","test_1","price=10,type=1")->save();
			Dao::instant("test_dao_sum","test_1","price=10,type=1")->save();
			$TestDaoSum = create_class('
				protected $id;
				protected $price;
				protected $type;
			','Dao','
				@class @{"database":"test_1","table":"test_dao_sum"}
				@var serial $id
				@var number $price
				@var number $type
			');
			eq(60,C($TestDaoSum)->find_sum("price"));
			eq(20,C($TestDaoSum)->find_sum("price",Q::eq("type",1)));
		 */
	}
	/**
	 * グルーピングした合計を取得する
	 *
	 * @param string $target_name 対象となるプロパティ
	 * @param string $gorup_name グルーピングするプロパティ名
	 * @return integer{}
	 */
	final public function find_sum_by($target_name,$gorup_name){
		$args = func_get_args();
		return $this->exec_aggregator_by('sum',$target_name,$gorup_name,$args);
		/***
			Dbc::temp("test_1","test_dao_sum_by",array("id"=>"serial","price"=>"number","type"=>"number"));
			Dao::instant("test_dao_sum_by","test_1","price=20,type=2")->save();
			Dao::instant("test_dao_sum_by","test_1","price=20,type=2")->save();
			Dao::instant("test_dao_sum_by","test_1","price=10,type=1")->save();
			Dao::instant("test_dao_sum_by","test_1","price=10,type=1")->save();

			$TestDaoSumBy = create_class('
				protected $id;
				protected $price;
				protected $type;
			','Dao','
				@class @{"database":"test_1","table":"test_dao_sum_by"}
				@var serial $id
				@var number $price
				@var number $type
			');
			eq(array(1=>20,2=>40),C($TestDaoSumBy)->find_sum_by("price","type"));
			eq(array(1=>20),C($TestDaoSumBy)->find_sum_by("price","type",Q::eq("type",1)));
		 */
	}
	/**
	 * 最大値を取得する
	 *
	 * @param string $target_name 対象となるプロパティ
	 * @return number
	 */
	final public function find_max($target_name){
		$args = func_get_args();
		return $this->exec_aggregator('max',$target_name,$args);
		/***
			Dbc::temp("test_1","test_dao_max",array("id"=>"serial","price"=>"number","type"=>"number","name"=>"string"));
			Dao::instant("test_dao_max","test_1","price=30,type=2,name=aaa")->save();
			Dao::instant("test_dao_max","test_1","price=20,type=2,name=ccc")->save();
			Dao::instant("test_dao_max","test_1","price=20,type=1,name=AAA")->save();
			Dao::instant("test_dao_max","test_1","price=10,type=1,name=BBB")->save();

			$TestDaoMax = create_class('
				protected $id;
				protected $price;
				protected $type;
				protected $name;
			','Dao','
				@class @{"database":"test_1","table":"test_dao_max"}
				@var serial $id
				@var number $price
				@var number $type
			');
			eq(30,C($TestDaoMax)->find_max("price"));
			eq(20,C($TestDaoMax)->find_max("price",Q::eq("type",1)));
			eq("ccc",C($TestDaoMax)->find_max("name"));
			eq("BBB",C($TestDaoMax)->find_max("name",Q::eq("type",1)));
		 */
	}
	/**
	 * グルーピングして最大値を取得する
	 *
	 * @param string $target_name 対象となるプロパティ
	 * @param string $gorup_name グルーピングするプロパティ名
	 * @return number
	 */
	final public function find_max_by($target_name,$gorup_name){
		$args = func_get_args();
		return $this->exec_aggregator_by('max',$target_name,$gorup_name,$args);
		/***
			Dbc::temp("test_1","test_dao_max_by",array("id"=>"serial","price"=>"number","type"=>"number","name"=>"string"));
			Dao::instant("test_dao_max_by","test_1","price=30,type=2,name=aaa")->save();
			Dao::instant("test_dao_max_by","test_1","price=20,type=2,name=ccc")->save();
			Dao::instant("test_dao_max_by","test_1","price=20,type=1,name=AAA")->save();
			Dao::instant("test_dao_max_by","test_1","price=10,type=1,name=BBB")->save();
			$TestDaoMaxBy = create_class('
				protected $id;
				protected $price;
				protected $type;
				protected $name;
			','Dao','
				@class @{"database":"test_1","table":"test_dao_max_by"}
				@var serial $id
				@var number $price
				@var number $type
			');
			eq(array(1=>20,2=>30),C($TestDaoMaxBy)->find_max_by("price","type"));
			eq(array(1=>20),C($TestDaoMaxBy)->find_max_by("price","type",Q::eq("type",1)));
			eq(array(1=>"BBB",2=>"ccc"),C($TestDaoMaxBy)->find_max_by("name","type"));
			eq(array(1=>"BBB"),C($TestDaoMaxBy)->find_max_by("name","type",Q::eq("type",1)));
		 */
	}
	/**
	 * 最小値を取得する
	 *
	 * @param string $target_name 対象となるプロパティ
	 * @param string $gorup_name グルーピングするプロパティ名
	 * @return number
	 */
	final public function find_min($target_name){
		$args = func_get_args();
		return $this->exec_aggregator('min',$target_name,$args);
		/***
			Dbc::temp("test_1","test_dao_min",array("id"=>"serial","price"=>"number","type"=>"number","name"=>"string"));
			Dao::instant("test_dao_min","test_1","price=30,type=2,name=aaa")->save();
			Dao::instant("test_dao_min","test_1","price=5,type=2,name=ccc")->save();
			Dao::instant("test_dao_min","test_1","price=20,type=1,name=AAA")->save();
			Dao::instant("test_dao_min","test_1","price=10,type=1,name=BBB")->save();
			$TestDaoMin = create_class('
				protected $id;
				protected $price;
				protected $type;
				protected $name;
			','Dao','				
				@class @{"database":"test_1","table":"test_dao_min"}
				@var serial $id
				@var number $price
				@var number $type
			');
			eq(5,C($TestDaoMin)->find_min("price"));
			eq(10,C($TestDaoMin)->find_min("price",Q::eq("type",1)));
		 */
	}
	/**
	 * グルーピングして最小値を取得する
	 * @param string $target_name 対象となるプロパティ
	 * @param string $gorup_name グルーピングするプロパティ名
	 * return integer{}
	 */
	final public function find_min_by($target_name,$gorup_name){
		$args = func_get_args();
		return $this->exec_aggregator_by('min',$target_name,$gorup_name,$args);
		/***
			Dbc::temp("test_1","test_dao_min_by",array("id"=>"serial","price"=>"number","type"=>"number","name"=>"string"));
			Dao::instant("test_dao_min_by","test_1","price=30,type=2,name=aaa")->save();
			Dao::instant("test_dao_min_by","test_1","price=5,type=2,name=ccc")->save();
			Dao::instant("test_dao_min_by","test_1","price=20,type=1,name=AAA")->save();
			Dao::instant("test_dao_min_by","test_1","price=10,type=1,name=BBB")->save();
			$TestDaoMinBy = create_class('
				protected $id;
				protected $price;
				protected $type;
				protected $name;
			','Dao','
				@class @{"database":"test_1","table":"test_dao_min_by"}
				@var serial $id
				@var number $price
				@var number $type
			');			
			$result = C($TestDaoMinBy)->find_min_by("price","type");
			eq(10,$result[1]);
			eq(5,$result[2]);
			eq(array(1=>10),C($TestDaoMinBy)->find_min_by("price","type",Q::eq("type",1)));
		 */
	}
	/**
	 * 平均を取得する
	 *
	 * @param string $target_name 対象となるプロパティ
	 * @return number
	 */
	final public function find_avg($target_name){
		$args = func_get_args();
		return $this->exec_aggregator('avg',$target_name,$args);
		/***
			Dbc::temp("test_1","test_dao_avg",array("id"=>"serial","price"=>"number","type"=>"number","name"=>"string"));
			Dao::instant("test_dao_avg","test_1","price=20,type=2,name=aaa")->save();
			Dao::instant("test_dao_avg","test_1","price=30,type=2,name=ccc")->save();
			Dao::instant("test_dao_avg","test_1","price=25,type=1,name=AAA")->save();
			Dao::instant("test_dao_avg","test_1","price=5,type=1,name=BBB")->save();
			$TestDaoAvg = create_class('
				protected $id;
				protected $price;
				protected $type;
				protected $name;
			','Dao','
				@class @{"database":"test_1","table":"test_dao_avg"}
				@var serial $id
				@var number $price
				@var number $type
			');
			eq(20,C($TestDaoAvg)->find_avg("price"));
			eq(15,C($TestDaoAvg)->find_avg("price",Q::eq("type",1)));
		 */
	}
	/**
	 * グルーピングして平均を取得する
	 * @param string $target_name 対象となるプロパティ
	 * @param string $gorup_name グルーピングするプロパティ名
	 * @return number{}
	 */
	final public function find_avg_by($target_name,$gorup_name){
		$args = func_get_args();
		return $this->exec_aggregator_by('avg',$target_name,$gorup_name,$args);
		/***
			Dbc::temp("test_1","test_dao_avg_by",array("id"=>"serial","price"=>"number","type"=>"number","name"=>"string"));
			Dao::instant("test_dao_avg_by","test_1","price=20,type=2,name=aaa")->save();
			Dao::instant("test_dao_avg_by","test_1","price=30,type=2,name=ccc")->save();
			Dao::instant("test_dao_avg_by","test_1","price=25,type=1,name=AAA")->save();
			Dao::instant("test_dao_avg_by","test_1","price=5,type=1,name=BBB")->save();
			$TestDaoAvgBy = create_class('
				protected $id;
				protected $price;
				protected $type;
				protected $name;
			','Dao','
				@class @{"database":"test_1","table":"test_dao_avg_by"}
				@var serial $id
				@var number $price
				@var number $type
			');
			eq(array(1=>15,2=>25),C($TestDaoAvgBy)->find_avg_by("price","type"));
			eq(array(1=>15),C($TestDaoAvgBy)->find_avg_by("price","type",Q::eq("type",1)));
		 */
	}
	/**
	 * distinctした一覧を取得する
	 *
	 * @param string $target_name 対象となるプロパティ
	 * @return mixed[]
	 */
	final public function find_distinct($target_name){
		$args = func_get_args();
		$dao = R($this->get_called_class());
		$args[] = $dao->__find_conds__();
		$results = $dao->which_aggregator('distinct',$args);
		return $results;
		/***
			Dbc::temp("test_1","test_dao_distinct",array("id"=>"serial","name"=>"string","type"=>"number"));
			Dao::instant("test_dao_distinct","test_1","name=AAA,type=1")->save();
			Dao::instant("test_dao_distinct","test_1","name=BBB,type=2")->save();
			Dao::instant("test_dao_distinct","test_1","name=AAA,type=1")->save();
			Dao::instant("test_dao_distinct","test_1","name=AAA,type=1")->save();
			Dao::instant("test_dao_distinct","test_1","name=CCC,type=1")->save();

			$TestDaoDistinct = create_class('
				protected $id;
				protected $name;
				protected $type;
			','Dao','
				@class @{"database":"test_1","table":"test_dao_distinct"}
				@var serial $id
				@var number $type
			');
			eq(array("AAA","BBB","CCC"),C($TestDaoDistinct)->find_distinct("name"));
			$result = C($TestDaoDistinct)->find_distinct("name",Q::eq("type",1));
			if(in_array("AAA",$result)){
				success();
			}else{
				fail();
			}
			if(in_array("CCC",$result)){
				success();
			}else{
				fail();
			}
		 */
	}
	/**
	 * 検索結果をすべて取得する
	 *
	 * @return $this[]
	 */
	final public function find_all(){
		$args = func_get_args();
		$result = array();
		foreach(call_user_func_array(array(C($this->get_called_class()),'find'),$args) as $p) $result[] = $p;
		return $result;
		/***
			# test
			Dbc::temp("test_1","test_dao_find",array("id"=>"serial","value"=>"string","value2"=>"string","updated"=>"timestamp","order"=>"number"));
			$TestDaoFind = create_class('
				protected $id;
				protected $order;
				protected $value;
				protected $value2;
				protected $updated;
			','Dao','
				@class @{"database":"test_1","table":"test_dao_find"}
				@var serial $id
				@var number $order
				@var timestamp $updated
				@var string $value
				@var string $value2
			');
			R(new $TestDaoFind("value=abc,updated=2008/12/24 10:00:00,order=4"))->save();
			R(new $TestDaoFind("value=def,updated=2008/12/24 10:00:00,order=3"))->save();
			R(new $TestDaoFind("value=ghi,updated=2008/12/24 10:00:00,order=1"))->save();
			R(new $TestDaoFind("value=jkl,updated=2008/12/24 10:00:00,order=2"))->save();
			R(new $TestDaoFind("value=aaa,updated=2008/12/24 10:00:00,order=2"))->save();

			eq(5,sizeof(C($TestDaoFind)->find_all()));
			foreach(C($TestDaoFind)->find(Q::eq("value","abc")) as $obj){
				eq("abc",$obj->value());
			}
			$paginator = new Paginator(1,2);
			eq(1,sizeof($result = C($TestDaoFind)->find_all(Q::neq("value","abc"),$paginator)));
			eq("ghi",$result[0]->value());
			eq(4,$paginator->total());

			$i = 0;
			foreach(C($TestDaoFind)->find(
					Q::neq("value","abc"),
					Q::ob(
						Q::b(Q::eq("order",2)),
						Q::b(Q::eq("order",4))
					),
					Q::neq("value","aaa")
				) as $obj){
				$i++;
			}
			eq(1,$i);

			$list = array("abc","def","ghi","jkl","aaa");
			$i = 0;
			foreach(C($TestDaoFind)->find() as $obj){
				eq($list[$i],$obj->value());
				eq("2008/12/24 10:00:00",$obj->fm_updated());
				$i++;
			}
			foreach(C($TestDaoFind)->find(Q::eq("value","AbC",Q::IGNORE)) as $obj){
				eq("abc",$obj->value());
			}
			foreach(C($TestDaoFind)->find(Q::neq("value","abc")) as $obj){
				neq("abc",$obj->value());
			}
			try{
				C($TestDaoFind)->find(Q::eq("value_error","abc"));
				eq(false,true);
			}catch(Exception $e){
				eq(true,true);
			}
			try{
				$dao = new $TestDaoFind();
				$dao->find(Q::eq("value_error","abc"));
				eq(false,true);
			}catch(Exception $e){
				eq(true,true);
			}

			$i = 0;
			foreach(C($TestDaoFind)->find(Q::startswith("value,value2",array("aa"))) as $obj){
				$i++;
				eq("aaa",$obj->value());
			}
			eq(1,$i);

			$i = 0;
			foreach(C($TestDaoFind)->find(Q::endswith("value,value2",array("c"))) as $obj){
				eq("abc",$obj->value());
				$i++;
			}
			eq(1,$i);

			$i = 0;
			foreach(C($TestDaoFind)->find(Q::contains("value,value2",array("b"))) as $obj){
				eq("abc",$obj->value());
				$i++;
			}
			eq(1,$i);

			$i = 0;
			foreach(C($TestDaoFind)->find(Q::endswith("value,value2",array("C"),Q::NOT|Q::IGNORE)) as $obj){
				neq("abc",$obj->value());
				$i++;
			}
			eq(4,$i);


			$i = 0;
			foreach(C($TestDaoFind)->find(Q::in("value",array("abc"))) as $obj){
				eq("abc",$obj->value());
				$i++;
			}
			eq(1,$i);

			foreach(C($TestDaoFind)->find(Q::match("value=abc")) as $obj){
				eq("abc",$obj->value());
			}
			foreach(C($TestDaoFind)->find(Q::match("value=!abc")) as $obj){
				neq("abc",$obj->value());
			}
			foreach(C($TestDaoFind)->find(Q::match("abc")) as $obj){
				eq("abc",$obj->value());
			}
			foreach(C($TestDaoFind)->find(Q::neq("value","abc"),new Paginator(1,3),Q::order("-id")) as $obj){
				eq("ghi",$obj->value());
			}
			foreach(C($TestDaoFind)->find(Q::neq("value","abc"),new Paginator(1,3),Q::order("id")) as $obj){
				eq("jkl",$obj->value());
			}
			foreach(C($TestDaoFind)->find(Q::neq("value","abc"),new Paginator(1,2),Q::order("order,-id")) as $obj){
				eq("aaa",$obj->value());
			}
		 */
		/***
			# ref_test
			Dbc::temp("test_1","test_dao_find_ref_1",array("id1"=>"serial","value1"=>"string"));
			Dbc::temp("test_1","test_dao_find_ref_2",array("id2"=>"serial","id1"=>"number","value2"=>"string"));
			Dao::instant("test_dao_find_ref_1","test_1","value1=aaa")->save();
			Dao::instant("test_dao_find_ref_2","test_1","id1=1,value2=bbb")->save();

			$TestDaoFindRef2 = create_class('
				protected $id2;
				protected $id1;
				protected $value1;
			','Dao','
				@class @{"database":"test_1","table":"test_dao_find_ref_2"}
				@var serial $id2
				@var number $id1
				@var mixed $value1 @{"cond":"id1(test_dao_find_ref_1.id1)"}
			');
			$result = C($TestDaoFindRef2)->find_all();
			eq(1,sizeof($result));
			eq("aaa",$result[0]->value1());

			Dbc::temp("test_1","test_dao_find_ref_3",array("id3"=>"serial","id2"=>"number"));
			Dao::instant("test_dao_find_ref_3","test_1","id2=1")->save();

			$TestDaoFindRef3 = create_class('
				protected $id3;
				protected $id2;
				protected $value1;
				protected $value2;
			','Dao','
				@class @{"database":"test_1","table":"test_dao_find_ref_3"}
				@var serial $id3
				@var number $id2
				@var mixed $value1 @{"cond":"id2(test_dao_find_ref_2.id2, test_dao_find_ref_2.id1, test_dao_find_ref_1.id1)"}
				@var mixed $value2 @{"cond":"id2(test_dao_find_ref_2.id2)"}
			');
			$result = C($TestDaoFindRef3)->find_all();
			eq(1,sizeof($result));
			eq("aaa",$result[0]->value1());
			eq("bbb",$result[0]->value2());
		*/
		/***
			# has_test
			Dbc::temp("test_1","test_dao_find_has_1",array("id1"=>"serial","value1"=>"string"));
			Dbc::temp("test_1","test_dao_find_has_2",array("id2"=>"serial","id1"=>"number"));

			Dao::instant("test_dao_find_has_1","test_1","value1=aaa")->save();
			Dao::instant("test_dao_find_has_2","test_1","id1=1")->save();

			Dao::instant("test_dao_find_has_1","test_1","value1=bbb")->save();
			Dao::instant("test_dao_find_has_2","test_1","id1=2")->save();

			$TestDaoFindHas1 = create_class('
				protected $id1;
				protected $value1;
			','Dao','
				@class @{"database":"test_1","table":"test_dao_find_has_1"}
				@var serial $id1
			');
			$TestDaoFindHas2 = create_class('
				protected $id2;
				protected $ref1;
			','Dao','
				@class @{"database":"test_1","table":"test_dao_find_has_2"}
				@var serial $id2
				@var '.$TestDaoFindHas1.' $ref1 @{"cond":"id1()id1"}
			');
			$result = C($TestDaoFindHas2)->find_all(Q::order("id2"));
			eq(2,sizeof($result));
			
			eq("aaa",$result[0]->ref1()->value1());
			eq(1,$result[0]->ref1()->id1());
			eq(1,$result[0]->id2());

			eq("bbb",$result[1]->ref1()->value1());
			eq(2,$result[1]->ref1()->id1());
			eq(2,$result[1]->id2());
		 */
		/***
			# match
			Dbc::temp("test_1","test_dao_find_match",array("id1"=>"serial","value1"=>"string"));
			Dao::instant("test_dao_find_match","test_1","value1=aaa")->save();
			Dao::instant("test_dao_find_match","test_1","value1=AaA")->save();
			Dao::instant("test_dao_find_match","test_1","value1=Bbb")->save();
			Dao::instant("test_dao_find_match","test_1","value1=BAA")->save();

			$TestDaoFindMatch = create_class('
				protected $id1;
				protected $value1;
			','Dao','
				@class @{"database":"test_1","table":"test_dao_find_match"}
				@var serial $id1
				@var string $value1
			');
			$result = C($TestDaoFindMatch)->find_all();
			eq(4,sizeof($result));

			$result = C($TestDaoFindMatch)->find_all(Q::match("AAA",Q::IGNORE));
			eq(2,sizeof($result));

			$result = C($TestDaoFindMatch)->find_all(Q::match("AA",Q::IGNORE));
			eq(3,sizeof($result));
		*/
		/***
			# null
			Dbc::temp("test_1","test_dao_find_null",array("id1"=>"serial","value1"=>"string"));
			Dao::instant("test_dao_find_null","test_1","value1=aaa")->save();
			Dao::instant("test_dao_find_null","test_1")->save();
			Dao::instant("test_dao_find_null","test_1")->save();
			Dao::instant("test_dao_find_null","test_1","value1=BAA")->save();
			Dao::instant("test_dao_find_null","test_1","value1=BAA")->save();

			$TestDaoFindNull = create_class('
				protected $id1;
				protected $value1;
			','Dao','
				@class @{"database":"test_1","table":"test_dao_find_null"}
				@var serial $id1
			');
			$result = C($TestDaoFindNull)->find_all(Q::eq("value1",null));
			eq(2,sizeof($result));
			$result = C($TestDaoFindNull)->find_all(Q::neq("value1",null));
			eq(3,sizeof($result));
		*/
		/***
			# date
			Dbc::temp("test_1","test_dao_find_date",array("id1"=>"serial","date_value"=>"date"));
			$TestDaoFindDate = create_class('
				protected $id1;
				protected $date_value;
			','Dao','
				@class @{"database":"test_1","table":"test_dao_find_date"}
				@var serial $id1
				@var date $date_value
			');
			$now = time();

			$dao = new $TestDaoFindDate();
			$dao->date_value($now);
			$dao->save();

			$dao = new $TestDaoFindDate();
			$dao->save();
			$dao = new $TestDaoFindDate();
			$dao->save();

			$result = C($TestDaoFindDate)->find_all(Q::eq("date_value",null));
			eq(2,sizeof($result));
			$result = C($TestDaoFindDate)->find_all(Q::neq("date_value",null));
			eq(1,sizeof($result));
			eq(date("Y/m/d",$now),$result[0]->fm_date_value());
		*/
		/***
			# random_order
			Dbc::temp("test_1","test_dao_find_random_order",array("id"=>"serial","value"=>"text"));
			$TestDaoFindRandomOrder = create_class('
				protected $id;
				protected $value;
			','Dao','
				@class @{"database":"test_1","table":"test_dao_find_random_order"}
				@var serial $id
				@var string $value
			');
			
			Dao::instant("test_dao_find_random_order","test_1","value=AAA")->save();
			Dao::instant("test_dao_find_random_order","test_1","value=BBB")->save();
			Dao::instant("test_dao_find_random_order","test_1","value=CCC")->save();

			$result = C($TestDaoFindRandomOrder)->find_all(Q::order("id"));
			$array = array("AAA","BBB","CCC");
			$i = 0;
			foreach($result as $obj){
				eq($array[$i],$obj->value());
				$i++;
			}
			
			$result = C($TestDaoFindRandomOrder)->find_all(Q::order("-id"));
			$array = array("CCC","BBB","AAA");
			$i = 0;
			foreach($result as $obj){
				eq($array[$i],$obj->value());
				$i++;
			}
			
			$pre_array = array();
			$result = C($TestDaoFindRandomOrder)->find_all(Q::random_order());
			$array = array("CCC","BBB","AAA");
			foreach($result as $obj){
				$i = array_search($obj->value(),$array);				
				if(neq(false,$i)){
					$pre_array[] = $obj->value();
					unset($array[$i]);
				}
			}
			$count = 0;
			for($i=0;$i<10;$i++){
				$rearray = array();
				$result = C($TestDaoFindRandomOrder)->find_all(Q::random_order());
				$array = array("CCC","BBB","AAA");
				foreach($result as $obj){
					$i = array_search($obj->value(),$array);
					if($i === false){
						fail();
						break;
					}
					$rearray[] = $obj->value();
					unset($array[$i]);
				}
				$count++;
				if($pre_array === $rearray) break;
			}
			neq(10,$count);
		*/
	}
	/**
	 * 検索結果をひとつ取得する
	 *
	 * @return $this
	 */
	final public function find_get(){
		$dao = R($this->get_called_class());
		$args = func_get_args();
		$args[] = new Paginator(1,1);
		$result = null;

		$it = call_user_func_array(array(C($dao),'find'),$args);
		foreach($it as $p){
			$result = $p;
			break;
		}
		if($result === null) throw new NotfoundDaoException(trans('{1} not found',get_class($dao)));
		return $result;
		/***
			Dbc::temp("test_1","test_dao_find_get",array("id"=>"serial","value"=>"string"));
			Dao::instant("test_dao_find_get","test_1","value=aaa")->save();
			Dao::instant("test_dao_find_get","test_1","value=bbb")->save();
			Dao::instant("test_dao_find_get","test_1","value=ccc")->save();
			$TestDaoFindGet = create_class('
				protected $id;
				protected $value;
			','Dao','
				@class @{"database":"test_1","table":"test_dao_find_get"}
				@var serial $id
			');
			eq("aaa",C($TestDaoFindGet)->find_get()->value());
			eq("aaa",C($TestDaoFindGet)->find_get()->value());
		 */
	}
	/**
	 * サブクエリを取得する
	 * 
	 * @param $name 対象のプロパティ
	 * @return Daq
	 */
	final public function find_sub($name){
		$args = func_get_args();
		array_shift($args);
		$query = new Q();
		$query->add($this->__find_conds__());

		if(!empty($args)) call_user_func_array(array($query,'add'),$args);
		if(!$query->is_order_by()) $query->order($name);
		$dao = R($this->get_called_class());
		$paginator = $query->paginator();
		if($paginator instanceof Paginator){
			$paginator->total(call_user_func_array(array(get_called_class(),'find_count'),$args));
			if($paginator->total() == 0) return array();
		}
		return $dao->call_module('select_sql',$dao,$query,$paginator,$name);
		/***
			Dbc::temp("test_1","test_dao_find_sub_user",array("id"=>"serial","name"=>"string"));
			Dao::instant("test_dao_find_sub_user","test_1","name=aaa")->save();
			Dao::instant("test_dao_find_sub_user","test_1","name=bbb")->save();
			Dao::instant("test_dao_find_sub_user","test_1","name=ccc")->save();

			Dbc::temp("test_1","test_dao_find_sub_comment",array("id"=>"serial","user_id"=>"number","value"=>"string"));
			Dao::instant("test_dao_find_sub_comment","test_1","user_id=1,value=aaa")->save();
			Dao::instant("test_dao_find_sub_comment","test_1","user_id=2,value=bbb")->save();
			Dao::instant("test_dao_find_sub_comment","test_1","user_id=2,value=ccc")->save();
			Dao::instant("test_dao_find_sub_comment","test_1","user_id=3,value=ddd")->save();
			Dao::instant("test_dao_find_sub_comment","test_1","user_id=3,value=eee")->save();

			Dbc::temp("test_1","test_dao_find_sub_friend",array("id"=>"serial","user_id"=>"number","friend_id"=>"number"));
			Dao::instant("test_dao_find_sub_friend","test_1","user_id=1,friend_id=2")->save();
			Dao::instant("test_dao_find_sub_friend","test_1","user_id=2,friend_id=3")->save();
			Dao::instant("test_dao_find_sub_friend","test_1","user_id=3,friend_id=1")->save();
			Dao::instant("test_dao_find_sub_friend","test_1","user_id=3,friend_id=2")->save();

			$TestDaoFindSubFriend = create_class('
				protected $id;
				protected $user_id;
				protected $friend_id;
			','Dao','
				@class @{"database":"test_1","table":"test_dao_find_sub_friend"}
				@var serial $id
				@var number $user_id
				@var number $friend_id
			');
			$TestDaoFindSubComment = create_class('
				protected $id;
				protected $user_id;
				protected $value;
			','Dao','
				@class @{"database":"test_1","table":"test_dao_find_sub_comment"}
				@var serial $id
				@var number $user_id
			');

			$user_id = 2;
			$re = C($TestDaoFindSubComment)->find_all(
											Q::ob(
												Q::b(Q::eq("user_id",$user_id)),
												Q::b(
													Q::in("user_id",
														C($TestDaoFindSubFriend)->find_sub("friend_id",Q::eq("user_id",$user_id))
													)
												)
											));
			eq(4,sizeof($re));
			$r = array();
			foreach($re as $obj){
				if($obj->user_id() == 2 || $obj->user_id() == 3) $r[] = $obj;
			}
			eq(4,sizeof($r));
		 */
	}
	/**
	 * 検索を実行する
	 *
	 * @return DaoStatementIterator
	 */
	final public function find(){
		$args = func_get_args();
		$dao = R($this->get_called_class());
		$query = new Q();
		$query->add($dao->__find_conds__());

		if(!empty($args)) call_user_func_array(array($query,'add'),$args);
		if(!$query->is_order_by()){
			foreach($dao->primary_columns() as $column) $query->order($column->name());
		}
		$paginator = $query->paginator();
		if($paginator instanceof Paginator){
			$paginator->total(call_user_func_array(array(C($this->get_called_class()),'find_count'),$args));
			if($paginator->total() == 0) return array();
		}
		$daq = $dao->call_module('select_sql',$dao,$query,$paginator);
		$statement = $dao->query($daq);
		$errors = $statement->errorInfo();
		if(isset($errors[1])){
			C($this->get_called_class())->rollback();
			throw new DaoException('['.$errors[1].'] '.(isset($errors[2]) ? $errors[2] : ''));
		}
		return new DaoStatementIterator($dao,$statement);
		/***
			Dbc::temp("test_1","test_dao_find_or",array("id"=>"serial","value"=>"string","no"=>"number"));
			Dao::instant("test_dao_find_or","test_1","value=A,no=1")->save();
			Dao::instant("test_dao_find_or","test_1","value=B,no=1")->save();
			Dao::instant("test_dao_find_or","test_1","value=C,no=2")->save();
			Dao::instant("test_dao_find_or","test_1","value=D,no=3")->save();
			Dao::instant("test_dao_find_or","test_1","value=E,no=1")->save();
			Dao::instant("test_dao_find_or","test_1","value=A,no=3")->save();
			Dao::instant("test_dao_find_or","test_1","value=B,no=3")->save();
			
			$TestDaoFindOr = create_class('
				protected $id;
				protected $value;
				protected $no;
			','Dao','
				@class @{"database":"test_1","table":"test_dao_find_or"}
				@var serial $id
				@var integer $no
			');
			$result = array();
			$paginator = new Paginator();
			foreach(C($TestDaoFindOr)->find(
				$paginator
				,Q::ob(
					Q::b(Q::eq("no",1),Q::eq("value","B"))
					,Q::b(Q::eq("no",1),Q::eq("value","A"))
				)
			) as $o){
				$result[] = $o;
			}
			eq(2,sizeof($result));
			eq(2,$paginator->total());
		*/
	}
	/**
	 * コミットする
	 */
	final public function commit(){
		R($this->get_called_class())->connection()->commit();
		/***
			Dbc::temp("test_1","test_dao_commit",array("id"=>"serial"));
			$TestDaoCommit = create_class('
				protected $id;
			','Dao','
				@class @{"database":"test_1","table":"test_dao_commit"}
				@var serial $id
			');
			$obj = new $TestDaoCommit();
			$obj->save();
			try{
				$obj->commit();
				fail();
			}catch(BadMethodCallException $e){
				success();
			}
		 */
	}
	/**
	 * ロールバックする
	 */
	final public function rollback(){
		R($this->get_called_class())->connection()->rollback();
		/***
			Dbc::temp("test_1","test_dao_rollback",array("id"=>"serial"));
			$TestDaoRollback = create_class('
				protected $id;
			','Dao','
				@class @{"database":"test_1","table":"test_dao_rollback"}
				@var serial $id
			');

			$obj = new $TestDaoRollback();
			$obj->save();
			try{
				$obj->rollback();
				fail();
			}catch(BadMethodCallException $e){
				success();
			}
		 */
	}
	/**
	 * 条件により削除する
	 * before/after/verifyは実行されない
	 * @return integer 実行した件数
	 */
	final public function find_delete(){
		$args = func_get_args();
		$dao = R($this->get_called_class());
		if(!$dao->_delete_ || !$dao->is_replication_master()) throw new DaoBadMethodCallException(trans('delete is not permitted'));
		$query = new Q();
		$query->add($dao->__find_conds__());

		if(!empty($args)) call_user_func_array(array($query,'add'),$args);
		/**
		 * delete文の生成
		 * @param self $this
		 */
		$daq = $dao->call_module('find_delete_sql',$dao,$query);
		return $dao->update_query($daq);
		/***
			Dbc::temp("test_1","test_dao_find_delete",array("id"=>"serial","value"=>"string"));
			$TestDaoFindDelete = create_class('
				protected $id;
				protected $value;
			','Dao','
				@class @{"database":"test_1","table":"test_dao_find_delete"}
				@var serial $id
			');
			eq(0,C($TestDaoFindDelete)->find_count());
			R(new $TestDaoFindDelete("value=abc"))->save();
			R(new $TestDaoFindDelete("value=def"))->save();
			R(new $TestDaoFindDelete("value=def"))->save();
			R(new $TestDaoFindDelete("value=def"))->save();
			R(new $TestDaoFindDelete("value=ghi"))->save();

			eq(5,C($TestDaoFindDelete)->find_count());
			C($TestDaoFindDelete)->find_delete(Q::eq("value","def"));
			eq(2,C($TestDaoFindDelete)->find_count());
		 */
	}
	/**
	 * _delete_がfalseでもDBから削除する
	 */
	final public function force_delete(){
		$pre = $this->_delete_;
		$this->_delete_ = true;
		$this->delete();
		$this->_delete_ = $pre;
	}
	/**
	 * DBから削除する
	 */
	final public function delete(){
		if(!$this->_delete_ || !$this->is_replication_master()) throw new DaoBadMethodCallException(trans('delete is not permitted'));
		$this->__before_delete__();
		$this->__delete_verify__();
		/**
		 * delete文の生成
		 * @param self $this
		 */
		$daq = $this->call_module('delete_sql',$this);
		if($this->update_query($daq) == 0) throw new DaoBadMethodCallException('delete failed');
		$this->__after_delete__();
		/***
			Dbc::temp("test_1","test_dao_delete",array("id"=>"serial","value"=>"string"));
			$TestDaoDelete = create_class('
				protected $id;
				protected $value;
			','Dao','
				@class @{"database":"test_1","table":"test_dao_delete"}
				@var serial $id
			');
			eq(0,C($TestDaoDelete)->find_count());
			R(new $TestDaoDelete("value=abc"))->save();
			R(new $TestDaoDelete("value=def"))->save();
			R(new $TestDaoDelete("value=ghi"))->save();

			eq(3,C($TestDaoDelete)->find_count());
			$obj = new $TestDaoDelete("id=1");
			$obj->delete();
			eq(2,C($TestDaoDelete)->find_count());
			$obj = new $TestDaoDelete("id=3");
			$obj->delete();
			eq(1,C($TestDaoDelete)->find_count());
			eq("def",C($TestDaoDelete)->find_get()->value());
		 */
	}
	/**
	 * DBへ保存する
	 */
	final public function save(){
		if(!$this->is_replication_master()) throw new DaoBadMethodCallException(trans('save is not permitted'));
		$q = new Q();
		$new = false;
		foreach($this->primary_columns() as $column){
			$value = $this->{$column->name()}();
			if($this->a($column->name(),'type') === 'serial' && empty($value)){
				$new = true;
				break;
			}
			$q->add(Q::eq($column->name(),$value));
		}
		if(!$new && C($this)->find_count($q) === 0) $new = true;
			foreach($this->self_columns() as $column){
			if($this->a($column->name(),'auto_now') === true){
				switch($this->a($column->name(),'type')){
					case 'timestamp':
					case 'date': $this->{$column->name()}(time()); break;
					case 'intdate': $this->{$column->name()}(date('Ymd')); break;
				}
			}else if($new && ($this->{$column->name()}() === null || $this->{$column->name()}() === '')){
				if($this->a($column->name(),'type') == 'string' && $this->a($column->name(),'auto_code_add') === true){
					$this->set_unique_code($column->name());
				}else if($this->a($column->name(),'auto_now_add') === true){
					switch($this->a($column->name(),'type')){
						case 'timestamp':
						case 'date': $this->{$column->name()}(time()); break;
						case 'intdate': $this->{$column->name()}(date('Ymd')); break;
					}
				}
			}
		}
		if($new){
			if(!$this->_create_) throw new DaoBadMethodCallException(trans('create save is not permitted'));
			$this->__before_save__();
			$this->__before_create__();
			$this->save_verify_primary_unique();
			$this->__create_verify__();
			$this->__save_verify__();
			$this->validate();
			/**
			 * createを実行するSQL文の生成
			 * @param self $this
			 * @return Daq
			 */
			$daq = $this->call_module('create_sql',$this);
			if($this->update_query($daq) == 0) throw new DaoBadMethodCallException('failed to create');
			if($daq->is_id()){
				/**
				 * AUTOINCREMENTの値を取得するSQL文の生成
				 * @param self $this
				 * @return integer
				 */
				$result = $this->func_query($this->call_module('last_insert_id_sql',$this));
				if(empty($result)) throw new DaoBadMethodCallException('create failed');
				$this->{$daq->id()}($result[0]);
			}
			$this->__after_create__();
			$this->__after_save__();
		}else{
			if(!$this->_update_) throw new DaoBadMethodCallException(trans('update save is not permitted'));
			$this->__before_save__();
			$this->__before_update__();
			$this->__update_verify__();
			$this->__save_verify__();
			$this->validate();

			$args = func_get_args();
			$query = new Q();
			if(!empty($args)) call_user_func_array(array($query,'add'),$args);
			/**
			 * updateを実行するSQL文の生成
			 * @param self $this
			 * @return Daq
			 */
			$daq = $this->call_module('update_sql',$this,$query);
			if($this->update_query($daq) == 0) throw new DaoBadMethodCallException('did not update');
			$this->__after_update__();
			$this->__after_save__();
		}
		$this->sync();
		return $this;
		/***
			Dbc::temp("test_1","test_dao_save",array("id"=>"serial","value"=>"string"));
			$TestDaoSave = create_class('
				protected $id;
				protected $value;
			','Dao','
				@class @{"database":"test_1","table":"test_dao_save"}
				@var serial $id
			');
			eq(0,C($TestDaoSave)->find_count());
			R(new $TestDaoSave("value=abc"))->save();
			R(new $TestDaoSave("value=def"))->save();
			R(new $TestDaoSave("value=ghi"))->save();

			eq(3,C($TestDaoSave)->find_count());
			$obj = new $TestDaoSave("id=1");
			$obj->sync();
			eq("abc",$obj->value());
			$obj->value("hoge");
			$obj->save();			
			$obj = new $TestDaoSave("id=1");
			$obj->sync();
			eq("hoge",$obj->value());
			
			$obj2 = new $TestDaoSave("id=1");
			$obj2->sync();
			eq("hoge",$obj2->value());
			$obj2->value("xyz");
			$obj2->save();
			
			$obj->value("VWX");
			try{
				$obj->save(Q::eq("value","hoge"));
				fail();
			}catch(DaoBadMethodCallException $e){
				success();
				
				$obj2->value("hoge");
				$obj2->save();
				
				$obj->value("QWE");
				try{
					$obj->save(Q::eq("value","hoge"));
					success();
				}catch(DaoBadMethodCallException $e){
					fail();
				}
			}
		 */
	}
	/**
	 * 指定のプロパティにユニークコードをセットする
	 * @param string $prop_name
	 * @return string 生成されたユニークコード
	 */
	final public function set_unique_code($prop_name){
		$code = '';
		$max = $this->a($prop_name,'max',32);
		$ctype = $this->a($prop_name,'ctype','alnum');
		if($ctype != 'alnum' && $ctype != 'alpha' && $ctype != 'digit') throw new LogicException('unexpected ctype');
		$char = '';
		if($ctype == 'alnum' || $ctype == 'digit') $char .= '0123456789';
		if($ctype != 'digit'){
		 	if($this->a($prop_name,'upper',false) === true) $char .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
		 	if($this->a($prop_name,'lower',true) === true) $char .= 'abcdefghijklmnopqrstuvwxyz';
		}
		$charl = strlen($char) - 1;
		while($code == '' || C($this)->find_count(Q::eq($prop_name,$code)) > 0){
			for($code='',$i=0;$i<$max;$i++) $code .= $char[mt_rand(0,$charl)];
		}
		$this->{$prop_name}($code);
		return $code;
	}
	/**
	 * DBの値と同じにする
	 * @return $this
	 */
	final public function sync(){
		$query = new Q();
		$paginator = new Paginator(1,1);
		foreach($this->primary_columns() as $column){
			$query->add(Q::eq($column->name(),$this->{$column->name()}()));
		}
		/**
		 * selectを実行するSQL文の生成
		 * @param self $this
		 * @return Daq
		 */
		$daq = $this->call_module('select_sql',$this,$query,$paginator);
		$statement = $this->query($daq);
		$errors = $statement->errorInfo();
		if(isset($errors[1])){
			C($this)->rollback();
			throw new DaoException('['.$errors[1].'] '.(isset($errors[2]) ? $errors[2] : ''));
		}
		$it = new DaoStatementIterator($this,$statement);
		foreach($it as $dao){
			$this->cp($dao);
			return $this;
		}
		throw new NotfoundDaoException(trans('not synchronize'));
		/***
			# sync
			Dbc::temp("test_1","test_dao_sync",array("id"=>"serial","value"=>"string"));
			$TestDaoSync = create_class('
				protected $id;
				protected $value;
			','Dao','
				@class @{"database":"test_1","table":"test_dao_sync"}
				@var serial $id
			');
			eq(0,C($TestDaoSync)->find_count());
			R(new $TestDaoSync("value=abc"))->save();
			R(new $TestDaoSync("value=def"))->save();

			eq(2,C($TestDaoSync)->find_count());
			$obj = new $TestDaoSync("id=1");
			$obj->sync();
			eq("abc",$obj->value());			
			$obj = new $TestDaoSync("id=2");
			$obj->sync();
			eq("def",$obj->value());

			$obj = new $TestDaoSync("id=3");
			try{
				$obj->sync();
				fail();
			}catch(NotfoundDaoException $e){
				success();
			}
		 */
		/***
			# find_conds
			Dbc::temp("test_1","test_dao_sync_find_conds",array("id"=>"serial","value"=>"string"));
			$TestDaoSyncFindConds = create_class('
				protected $id;
				protected $value;
				protected function __find_conds__(){
					return Q::b(Q::eq("value","abc"));
				}
			','Dao','
				@class @{"database":"test_1","table":"test_dao_sync_find_conds"}
				@var serial $id
			');
			eq(0,C($TestDaoSyncFindConds)->find_count());
			R(new $TestDaoSyncFindConds("value=abc"))->save();
			R(new $TestDaoSyncFindConds("value=def"))->save();
			eq(1,C($TestDaoSyncFindConds)->find_count());
			
			$result = array();
			foreach(C($TestDaoSyncFindConds)->find() as $d){
				$result[] = $d;
			}
			if(eq(1,sizeof($result))){
				eq("abc",$result[0]->value());
			}

			$obj = new $TestDaoSyncFindConds("id=1");
			$obj->sync();
			eq("abc",$obj->value());

			$obj = new $TestDaoSyncFindConds("id=2");
			$obj->sync();
			eq("def",$obj->value());
		 */		
	}
	/**
	 * find_pageでのデフォルトのソート対象
	 *
	 * @return string
	 */
	protected function __page_order__(){
		$columns = $this->primary_columns();
		if(empty($columns)) $columns = $this->self_columns();
		$column = array_shift($columns);
		return '-'.$column->name();
	}
	/**
	 * １ページ分を取得する
	 * @param string $query
	 * @param Paginator $paginator
	 * @param string $order
	 * @param string $porder
	 * @return array Dao
	 */
	final public function find_page($query=null,Paginator $paginator=null,$order=null,$porder=null){
		if($paginator === null) $paginator = new Paginator();
		$dao = R($this->get_called_class());
		return C($dao)->find_all($paginator,Q::match($query,Q::IGNORE),Q::select_order($order,$porder),Q::order($dao->__page_order__()));
		/***
			# match
			Dbc::temp("test_1","test_dao_find_page_match",array("id1"=>"serial","value1"=>"string"));
			Dao::instant("test_dao_find_page_match","test_1","value1=aaa")->save();
			Dao::instant("test_dao_find_page_match","test_1","value1=AaA")->save();
			Dao::instant("test_dao_find_page_match","test_1","value1=Bbb")->save();
			Dao::instant("test_dao_find_page_match","test_1","value1=BAA")->save();

			$TestDaoFindPageMatch = create_class('
				protected $id1;
				protected $value1;
			','Dao','
				@class @{"database":"test_1","table":"test_dao_find_page_match"}
				@var serial $id1
				@var string $value1
			');
			$result = C($TestDaoFindPageMatch)->find_page();
			eq(4,sizeof($result));

			$result = C($TestDaoFindPageMatch)->find_page("AAA");
			eq(2,sizeof($result));

			$result = C($TestDaoFindPageMatch)->find_page("AA");
			eq(3,sizeof($result));


			eq(4,C($TestDaoFindPageMatch)->find_count());
			
			$result = C($TestDaoFindPageMatch)->find_page();
			eq(4,sizeof($result));
			
			$paginator = new Paginator(2);
			$result = C($TestDaoFindPageMatch)->find_page("",$paginator);
			eq(2,sizeof($result));
			eq(4,$paginator->total());
			
			$paginator = new Paginator(2);
			$result = C($TestDaoFindPageMatch)->find_page("AA",$paginator,"value1");
			eq(2,sizeof($result));
			eq(3,$paginator->total());
			
			
			$q = "";
			$paginator = new Paginator(20, 1);
			$object_list = C($TestDaoFindPageMatch)->find_page($q, $paginator, 'value1');
			$paginator = $paginator->cp(array('q' => $q));
			eq(4,sizeof($object_list));
			eq(4,$paginator->total());
		*/
	}
	/**
	 * columnに対応した変数名を返す
	 * @return string[]
	 */
	final public function get_columns(){
		$keys = array();
		foreach($this->props() as $k){
			if(!$this->a($k,'join')) $keys[] = $k;
		}
		return $keys;
	}
	/**
	 * Daqを返す
	 * @param string $sql
	 * @param array $vars
	 * @param string $id_name
	 * @return Daq
	 */
	final static public function daq($sql,$vars=array(),$id_name=null){
		$daq = new Daq();
		$daq->sql($sql);
		$daq->cp($vars);
		$daq->id($id_name);
		return $daq;
	}
	/**
	 * すべての接続をコミットする
	 */
	final static public function commit_all(){
		foreach(Dbc::connections() as $con) $con->commit();
	}
	/**
	 * すべての接続をロールバックする
	 */
	final static public function rollback_all(){
		foreach(Dbc::connections() as $con) $con->rollback();
	}
	/**
	 * テーブルを作成する
	 * -create_table org.rhaco.flow.module.SessionDao
	 * @request mixed $execute 生成されたSQLを実行する
	 */
	static public function __setup_create_table__(Request $req,$package){		
		$exec = $req->is_vars('execute');
		$sql = $con = null;
		$path = (empty($package)) ? path("resources/schema.xml") : File::absolute(dirname(Lib::imported_path($package)),"resources/schema.xml");
		
		if(empty($package) && is_file($path)){
			$con = Dbc::connection($package);
			foreach(Tag::anyhow(File::read($path))->in("database") as $db){
				foreach($db->in("table") as $table){
					$columns = array();

					foreach($table->in("column") as $column){
						$params = array();
						foreach($column->ar_param() as $param){
							$params[$param[0]] = Text::seem($param[1]);
						}
						$columns[$column->in_param("name")] = $params;
					}
					try{
						$sql .= " ".$con->create_table($table->in_param("name"),$columns,$exec)."\n";
					}catch(Exception $e){
						Exceptions::add($e);
					}
				}
			}
		}
		if($req->is_vars('all')){
			foreach(Lib::classes(true,true) as $path => $name) Lib::import($path);
			foreach(get_declared_classes() as $class){
				try{
					if(is_subclass_of($class,__CLASS__)){
						$ref = new ReflectionClass($class);
						if(!$ref->isAbstract() && !$ref->isInterface()){
							$obj = $ref->newInstance("_init_=false");
							if($obj instanceof Dao){
								$con = $obj->connection();
								$sql .= " ".$con->create_table($obj->table(),$obj,$exec)."\n";
							}
						}
					}
				}catch(Exception $e){
					Exceptions::add($e);
				}
			}
		}else if(!empty($package)){
			$class = Lib::import($package);
			$filepath = Lib::imported_path($package);
			$base = (basename(dirname($filepath)) == substr(basename($filepath),0,-4)) ? dirname($filepath) : $filepath;
			foreach(get_declared_classes() as $class){
				if(is_subclass_of($class,__CLASS__)){
				$ref = new ReflectionClass($class);
					if(strpos(str_replace("\\","/",$ref->getFileName()),$base) === 0){
						try{
							$obj = $ref->newInstance("_init_=false");
							if($obj instanceof Dao){
								$con = $obj->connection();
								$sql .= " ".$con->create_table($obj->table(),$obj,$exec)."\n";
							}
						}catch(Exception $e){
							Exceptions::add($e);
						}
					}
				}
			}
		}
		Exceptions::throw_over();
		if(empty($sql)) throw new RuntimeException("target not found");
		if(isset($con) && $exec) $con->commit();
		println($exec ? "-- Executed SQL:" : "-- Create Table SQL:");
		println($sql);
	}
	/**
	 * Daoの接続定義情報の一覧
	 */
	static public function __setup_dao_config__(Request $req,$value){
		$packages = array();
		foreach(Lib::classes(true,true) as $package => $class){
			Lib::import($package);
		}
		foreach(get_declared_classes() as $class){
			if(is_subclass_of($class,__CLASS__)){
				$ref = new ReflectionClass($class);
				$path = Lib::package_path($ref->getFileName());
				$packages[$path] = $path;
			}
		}
		$const = array();
		foreach(App::constants('org.rhaco.storage.db.Dbc') as $k => $v){
			list($null,$p) = explode('@',$k,2);
			$const[] = $p;
		}
		foreach($packages as $k => $v){
			$packages[$k] = false;
			foreach($const as $p){
				if(strpos($v,$k) === 0){
					$packages[$k] = true;
					break;
				}
			}
		}
		println("`org.rhaco.storage.db.Dbc` config list:",true);
		$len = Text::length(array_keys($packages));
		foreach($packages as $k => $b){
			println("    ".($b ? "[*]" : "[-]")." ".str_pad($k,$len));
		}
		foreach($packages as $k => $v){
			if($v === false){
				println("");
				println("   ex..",false);
				println(sprintf('    def("org.rhaco.storage.db.Dbc@%s"'."\n".'        ,"type=org.rhaco.storage.db.module.DbcMysql,host=localhost,dbname=DATABASE_NAME,user=USER_NAME,password=USER_PASSWORD,encode=utf8");',$k));
				break;
			}
		}
	}
	protected function ___verify___(){
		return true;
	}
	protected function ___form___(){
		throw new DaoException(trans('undef form_{1}',ucfirst($this->_[1]).'()'));
	}
	protected function ___master___(){
		list($o,$n) = $this->_;
		$results = array();
		$master = $o->a($n,'master');
		if(!empty($master)){
			$primarys = R($master)->primary_columns();
			if(!empty($primarys)){
				$primary = key($primarys);
				foreach(C($master)->find() as $dao) $results[$dao->{$primary}()] = $dao;
			}
			return $results;
		}
		throw new DaoException(trans('undef master'));
	}
	protected function __cp__($arg){
		if(isset($arg)){
			$vars = $this->prop_values();
			if($arg instanceof self){
				foreach($arg->prop_values() as $name => $value){
					if(array_key_exists($name,$vars)){
						try{
							$this->{$name}($value);
						}catch(Exception $e){
							$this->dao_exception()->add($e,$name);
						}
					}
				}
			}else if(is_array($arg)){
				foreach($arg as $name => $value){
					if(array_key_exists($name,$vars)){
						try{
							$this->{$name}($value);
						}catch(Exception $e){
							$this->dao_exception()->add($e,$name);
						}
					}
				}
			}else{
				throw new InvalidArgumentException('cp');
			}
			$this->dao_exception()->throw_over();
		}
		return $this;
	}
	/**
	 * 配列からプロパティに値をセットする
	 * @param mixed{} $arg
	 * @return $this
	 */
	public function set_props($arg){
		if(isset($arg) && (is_array($arg) || (is_object($arg) && ($arg instanceof \Traversable)))){
			$vars = get_object_vars($this);
			foreach($arg as $name => $value){
				if($name[0] != '_' && array_key_exists($name,$vars)){
					try{
						$this->{$name}($value);
					}catch(Exception $e){
						$this->dao_exception()->add($e,$name);
					}
				}
			}
		}
		return $this;
	}
}
