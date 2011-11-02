<?php
/**
 * 基底クラス
 * @author tokushima
 */
class Object{
	static private $_sm = array(array(),array(),array()); // anon,class anon,module
	private $_m = array(array(),array(),array(),array(),true); 	// objects,modules,props,params,static
	protected $_ = array(null,null); // last access prop (object,prop)

	/**
	 * モジュールがあるか
	 * @param string $method
	 * @return boolean
	 */
	final public function has_module($method){
		foreach((($this->_m[4]) ? (isset(self::$_sm[2][get_class($this)]) ? self::$_sm[2][get_class($this)] : array()) : $this->_m[1]) as $obj){
			if(method_exists($obj,$method)) return true;
		}
		return false;
	}
	/**
	 * モジュールの実行
	 * @param string $method
	 * @param mixed $p 0..9
	 * @return mixed
	 */
	final public function call_module($method,&$p0=null,&$p1=null,&$p2=null,&$p3=null,&$p4=null,&$p5=null,&$p6=null,&$p7=null,&$p8=null,&$p9=null){
		if($this->has_module($method)){
			$result = null;
			foreach((($this->_m[4]) ? self::$_sm[2][get_class($this)] : $this->_m[1]) as $obj){
				if(method_exists($obj,$method)) $result = call_user_func_array(array($obj,$method),array(&$p0,&$p1,&$p2,&$p3,&$p4,&$p5,&$p6,&$p7,&$p8,&$p9));
			}
			return $result;
		}
		return $p0;
	}
	/**
	 * モジュールを追加する
	 * @param object $obj モジュールに追加するインスタンス
	 */
	final public function add_module($obj){
		if(!is_object($obj)) throw new InvalidArgumentException('invalid argument');
		if($this->_m[4]){
			self::$_sm[2][get_class($this)][] = $obj;
		}else{
			if(get_class($this) === get_class($obj)) return;
			$this->_m[1][] = $obj;
			foreach($this->_m[0] as $mixin_obj){
				if($mixin_obj instanceof self) $mixin_obj->add_module($obj);
			}
		}
		return $this;
	}
	/**
	 * モジュールをコピーする
	 * @param object $obj モジュールを有するオブジェクト
	 */
	final public function copy_module($obj){
		foreach($obj->_m[1] as $m) $this->add_module($m);	
		return $this;	
	}
	/**
	 * ハッシュとしての値を返す
	 * @return array
	 */
	final public function hash(){
		$args = func_get_args();
		if(method_exists($this,'__hash__')) return call_user_func_array(array($this,'__hash__'),$args);
		$result = array();
		foreach($this->props() as $name){
			if($this->a($name,'get') !== false && $this->a($name,'hash') !== false){
				switch($this->a($name,'type')){
					case 'boolean': $result[$name] = $this->{$name}(); break;
					default: $result[$name] = $this->{'fm_'.$name}();
				}
			}
		}
		return $result;
		/***
			# hash_fm
			$name1 = create_class('
				protected $aaa = "hoge";
				protected $bbb = 1;
				protected $ccc = 123;
			');
			$obj1 = new $name1();
			eq(array("aaa"=>"hoge","bbb"=>"1","ccc"=>"123"),$obj1->hash());

			$name2 = create_class('
				protected function __fm_ccc__(){
					return "[".$this->ccc."]";
				}
			',$name1
			,'
				@var serial $aaa @{"hash":false}
				@var number $bbb
			'
			);
			$obj2 = new $name2();
			eq(array("bbb"=>1,"ccc"=>"[123]"),$obj2->hash());
		*/
		/***
			# hash_type
			$name = create_class('
				protected $aaa=1;
				protected $bbb=2;
				protected $ccc=false;
				protected $ddd="ABC";
				protected $eee=20100420;
			',null,'
				@var serial $aaa
				@var number $bbb
				@var boolean $ccc
				@var string $ddd
				@var intdate $eee
			');
			$obj = new $name();
			eq(array("aaa"=>1,"bbb"=>2,"ccc"=>false,"ddd"=>"ABC","eee"=>"2010/04/20"),$obj->hash());
		 */
	}
	/**
	 * 値をコピーする
	 * @param Object $arg コピーする値
	 * @return $this
	 */
	final public function cp($arg){
		$args = func_get_args();
		if(method_exists($this,'__cp__')){
			call_user_func_array(array($this,'__cp__'),$args);
		}else if(isset($args[0])){
			$vars = $this->prop_values();
			if($args[0] instanceof self){
				foreach($args[0]->prop_values() as $name => $value){
					if(array_key_exists($name,$vars) && $args[0]->a($name,'cp') !== false) $this->{$name}($value);
				}
			}else if(is_array($args[0])){
				foreach($args[0] as $name => $value){
					if(array_key_exists($name,$vars)) $this->{$name}($value);
				}
			}else{
				throw new InvalidArgumentException('cp');
			}
		}
		return $this;
		/***
			# cp1
			$name1 = create_class('public $aaa;');
			$name2 = create_class('public $aaa;');
			$name3 = create_class('public $ccc;');

			$obj1 = new $name1();
			$obj2 = new $name2("aaa=hoge");
			$obj3 = new $name3("ccc=fuga");

			eq("hoge",$obj1->cp($obj2)->aaa());
			eq("hoge",$obj1->cp($obj3)->aaa());

			$obj1 = new $name1();
			eq("hoge",$obj1->cp(array("aaa"=>"hoge"))->aaa());
		*/
		/***
			# cp2
			$name1 = create_class('
				public $aaa;
				public $bbb;
			',null,'
				@var mixed $aaa @{"cp":false}
			');
			$name2 = create_class('
				public $aaa;
				public $bbb;
			');
			$obj1 = new $name1("aaa=hogefuga,bbb=123456");
			eq("hogefuga",$obj1->aaa());
			eq("123456",$obj1->bbb());
			$obj2 = new $name2();
			eq(null,$obj2->aaa());
			eq(null,$obj2->bbb());
			$obj2->cp($obj1);
			eq(null,$obj2->aaa());
			eq("123456",$obj2->bbb());
		*/
	}
	/**
	 * objectをmixinさせる
	 * @param object $object mixinさせるインスタンス
	 * @return $this
	 */
	final public function add_object($object){
		if(!is_object($object) || !($object instanceof self) || get_class($object) === get_class($this)) throw new InvalidArgumentException('invalid argument');
		$this->_m[0] = array_reverse(array_merge(array_reverse($this->_m[0],true),array(get_class($object)=>$object)),true);
		return $this;
		/***
			$name1 = create_class('
				public $aaa = "AAA";
				public function xxx(){
					return "xxx";
				}
			');
			$name2 = create_class('
				public $bbb = "BBB";
				protected $ccc = "CCC";
				public function zzz(){
					return "zzz";
				}
			');
			$aa = new $name1();
			eq("xxx",$aa->xxx());
			try{
				$aa->zzz();
				fail();
			}catch(Exception $e){
				success();
			}
			$aa->add_object(new $name2());
			eq("zzz",$aa->zzz());
			eq(array("aaa","bbb","ccc"),$aa->props());

			$name3 = create_class('',$name2);
			$obj3 = new $name3();
			$obj3->add_object(new $name2());
			eq("BBB",$obj3->bbb());
			eq("CCC",$obj3->ccc());
			eq("zzz",$obj3->zzz());
			eq(true,($obj3 instanceof $name3));
			eq(array("bbb","ccc"),$obj3->props());			

			$name4 = create_class('
				public $eee = "EEE";
			',"stdClass");
			$obj4 = new $name1;
			try{
				$obj4->add_object(new $name4);
				fail();
			}catch(InvalidArgumentException $e){
				success();
			}
			$obj2 = new $name2();
			try{
				$obj2->add_object($obj2);
				fail();
			}catch(LogicException $e){
				success();
			}
		 */
		/***
			# duplicate_mixin
			$name1 = create_class('
				protected $aaa = "AAA";
				public function xxx(){ return "xxx"; }
			');
			$name2 = create_class('
				protected $aaa = "A+A+A";
				protected $bbb = "BBB";
				protected $ccc = "CCC";
				public function zzz(){ return "zzz"; }
			');
			$aa = new $name1();
			eq("xxx",$aa->xxx());
			try{
				$aa->zzz();
				fail();
			}catch(Exception $e){
				success();
			}
			$aa->add_object(new $name2());
			eq("zzz",$aa->zzz());
			eq(array("aaa","bbb","ccc"),$aa->props());
			eq("A+A+A",$aa->aaa());
		 */
		/***
			# duplicate_mixin_set
			$name1 = create_class('
				protected $aaa = "AAA";
			');
			$name2 = create_class('
				protected $aaa = "A+A+A";
				protected $bbb = "BBB";
				protected $ccc = "CCC";
			',null,'
				@var string $aaa
			');
			$aa = new $name1();
			$aa->add_object(new $name2());
			eq(array("aaa","bbb","ccc"),$aa->props());
			eq("A+A+A",$aa->aaa());
			eq("BBB",$aa->bbb());
			$aa->bbb("GGG");
			eq("GGG",$aa->bbb());
			eq(array("aaa","bbb","ccc"),$aa->props());

			$bb = clone($aa);
			eq(array("aaa","bbb","ccc"),$bb->props());

			eq("GGG",$aa->bbb());

			$bb->bbb("AAA");
			eq("AAA",$bb->bbb());
			
			eq("GGG",$aa->bbb());
			$aa->bbb("FFF");
			eq("FFF",$aa->bbb());
			
			eq("AAA",$bb->bbb());
			eq(array("aaa","bbb","ccc"),$bb->props());
			
			$cc = clone($bb);
			eq("string",$cc->a("aaa","type"));
			eq(null,$cc->a("bbb","type"));
			eq(null,$cc->a("ccc","type"));
			eq(array("aaa","bbb","ccc"),$cc->props());
		 */
		/***
			#mixin_ext
			$name1 = create_class('
				protected $aaa = "AAA";
				protected function ___hoge___(){
					return "hoge_".call_user_func($this->_);
				}
			');
			$name2 = create_class('
				protected $bbb = "BBB";
			');
			$name3 = create_class('
				protected $ccc = "CCC";
			',$name2);
			
			$obj = new $name3();
			$obj->add_object(new $name1());
			
			eq("AAA",$obj->aaa());
			eq("BBB",$obj->bbb());
			eq("CCC",$obj->ccc());

			eq("hoge_AAA",$obj->hoge_aaa());
			eq("hoge_BBB",$obj->hoge_bbb());
			eq("hoge_CCC",$obj->hoge_ccc());
		 */
	}
	final public function __set($n,$v){
		if(in_array($n,$this->_m[2])){
			$this->_ = array($this,$n);
			call_user_func_array(array($this,'___set___'),array($v));
			$this->_ = array(null,null);
		}else if($n[0] == '_'){
			$this->{$n} = $v;
		}else{
			$this->{$n} = $v;
			$this->_m[2][] = $n;
		}
	}
	final public function __get($n){
		if(!in_array($n,$this->_m[2])) throw new InvalidArgumentException('Processing not permitted [get]');
		$this->_ = array($this,$n);
		$res = $this->___get___();
		$this->_ = array(null,null);
		return $res;
	}
	final public function __call($n,$args){
		foreach($this->_m[0] as $o){
			try{ return call_user_func_array(array($o,$n),$args);
			}catch(ErrorException $e){}
		}
		list($call,$prop) = (in_array($n,$this->_m[2])) ? array((empty($args) ? 'get' : 'set'),$n) : (preg_match("/^([a-z]+)_([a-zA-Z].*)$/",$n,$n) ? array($n[1],$n[2]) : array(null,null));
		if(empty($call)) throw new ErrorException(get_class($this).'::'.$n.' method not found');
		foreach(array_merge(array($this),$this->_m[0]) as $o){
			if(method_exists($o,'___'.$call.'___')){
				$o->_ = array($this,$prop);
				$result = call_user_func_array(array($o,(method_exists($o,'__'.$call.'_'.$prop.'__') ? '__'.$call.'_'.$prop.'__' : '___'.$call.'___')),$args);
				$o->_ = array(null,null);
				return $result;
			}
		}		
		/***
			$class1 = create_class('
				public $aaa;
				public $bbb;
				public $ccc;
				public $ddd;
				public $eee;
				public $fff;
				protected $ggg = "hoge";
				public $hhh;

				protected function __set_ddd__($a,$b){
					$this->ddd = $a.$b;
				}
				public function nextDay(){
					return date("Y/m/d H:i:s",$this->eee + 86400);
				}
				protected function ___cn___(){
					if($this->a($this->_[1],"column") === null || $this->a($this->_[1],"table") === null) throw new Exception();
					return array($this->a($this->_[1],"table"),$this->a($this->_[1],"column"));
				}
			',null,'
				@var number $aaa
				@var number[] $bbb
				@var string{} $ccc
				@var timestamp $eee
				@var string $fff @{"column":"Acol","table":"BTbl"}
				@var string $ggg @{"set":false}
				@var boolean $hhh
			');
			$hoge = new $class1();
			eq(null,$hoge->aaa());
			eq(false,$hoge->is_aaa());
			$hoge->aaa("123");
			eq(123,$hoge->aaa());
			eq(true,$hoge->is_aaa());
			eq(array(123),$hoge->ar_aaa());
			eq(123,$hoge->rm_aaa());
			eq(false,$hoge->is_aaa());
			eq(null,$hoge->aaa());

			eq(array(),$hoge->bbb());
			$hoge->bbb("123");
			eq(array(123),$hoge->bbb());
			$hoge->bbb(456);
			eq(array(123,456),$hoge->bbb());
			eq(456,$hoge->in_bbb(1));
			eq("hoge",$hoge->in_bbb(5,"hoge"));
			$hoge->bbb(789);
			$hoge->bbb(10);
			eq(array(123,456,789,10),$hoge->bbb());
			eq(array(1=>456,2=>789),$hoge->ar_bbb(1,2));
			eq(array(1=>456,2=>789,3=>10),$hoge->ar_bbb(1));
			eq(array(123,456,789,10),$hoge->rm_bbb());
			eq(array(),$hoge->bbb());

			eq(array(),$hoge->ccc());
			eq(false,$hoge->is_ccc());
			$hoge->ccc("AaA");
			eq(array("AaA"=>"AaA"),$hoge->ccc());
			eq(true,$hoge->is_ccc());
			eq(true,$hoge->is_ccc("AaA"));
			eq(false,$hoge->is_ccc("bbb"));
			$hoge->ccc("bbb");
			eq(array("AaA"=>"AaA","bbb"=>"bbb"),$hoge->ccc());
			$hoge->ccc(123);
			eq(array("AaA"=>"AaA","bbb"=>"bbb","123"=>"123"),$hoge->ccc());
			eq("bbb",$hoge->rm_ccc("bbb"));
			eq(array("AaA"=>"AaA","123"=>"123"),$hoge->ccc());
			$hoge->ccc("ddd");
			eq(array("AaA"=>"AaA","123"=>"123","ddd"=>"ddd"),$hoge->ccc());
			eq(array("123"=>"123"),$hoge->ar_ccc(1,1));
			eq(array("AaA"=>"AaA","ddd"=>"ddd"),$hoge->rm_ccc("AaA","ddd"));
			eq(array("123"=>"123"),$hoge->ccc());
			eq(array("123"=>"123"),$hoge->rm_ccc());
			eq(array(),$hoge->ccc());
			$hoge->ccc("abc","def");
			eq(array("abc"=>"def"),$hoge->ccc());

			eq(null,$hoge->ddd());
			$hoge->ddd("hoge","fuga");
			eq("hogefuga",$hoge->ddd());

			$hoge->eee("1976/10/04");
			eq("1976/10/04",date("Y/m/d",$hoge->eee()));
			eq("1976/10/05 00:00:00",$hoge->nextDay());

			try{
				$hoge->eee("ABC");
				eq(false,$hoge->eee());
			}catch(InvalidArgumentException $e){
				success();
			}
			try{
				$hoge->eee("000/00/00 00:00:00");
				eq(null,$hoge->eee());
			}catch(InvalidArgumentException $e){
				fail();
			}
			try{
				$hoge->eee(null);
				success();
			}catch(InvalidArgumentException $e){
				fail();
			}
			eq(array("BTbl","Acol"),$hoge->cn_fff());

			eq("hoge",$hoge->ggg());
			try{
				$hoge->ggg("fuga");
				fail();
			}catch(Exception $e){
				success();
			}
			$hoge->hhh(true);
			eq(true,$hoge->hhh());
			$hoge->hhh(false);
			eq(false,$hoge->hhh());
			try{
				$hoge->hhh("hoge");
				fail();
			}catch(Exception $e){
				success();
			}
		*/
		/***
			# types
			$name1 = create_class('
				protected $aa;
				protected $aaa;
				protected $bb;
				protected $cc;
				protected $dd;
				protected $ee;
				protected $ff;
				protected $gg;
				protected $hh;
				protected $ii;
				protected $jj;
				protected $kk;
				protected $ll;
				protected $mm;
				protected $nn;
				protected $oo;
				protected $pp;
				protected $qq;
				
				protected function __set_aaa__($value){
					$this->aaa = (($value === null) ? "" : "ABC").$value;
				}
				protected function __get_aaa__(){
					return empty($this->aaa) ? null : "[".$this->aaa."]";
				}
			',null,'
				@var mixed $aa
				@var mixed $aaa
				@var string $bb
				@var serial $cc
				@var number $dd
				@var boolean $ee
				@var timestamp $ff
				@var time $gg
				@var choice $hh @{"choices":["abc","def"]}
				@var string{} $ii
				@var string[] $jj
				@var email $kk
				@var date $ll
				@var alnum $mm
				@var intdate $nn
				@var integer $oo
				@var text $pp
				@var number $qq @{"decimal_places":2}
			');
			$obj = new $name1();
			eq(false,$obj->is_aa());
			$obj->aa("hoge");
			eq(true,$obj->is_aa());
			$obj->aa("");
			eq(null,$obj->aa());

			eq(false,$obj->is_aaa());
			$obj->aaa("hoge");
			eq(true,$obj->is_aaa());
			eq("[ABChoge]",$obj->aaa());
			$obj->aaa(null);
			eq(false,$obj->is_aaa());

			eq(null,$obj->a("bb","attr"));
			eq(false,$obj->is_bb());
			$obj->bb("hoge");
			eq("hoge",$obj->bb());
			eq(true,$obj->is_bb());
			$obj->bb("");
			eq(false,$obj->is_bb());			
			$obj->bb("");
			eq("",$obj->bb());
			$obj->bb(null);
			eq(null,$obj->bb());
			$obj->bb("aaa\nbbb\nccc\n");
			eq("aaabbbccc",$obj->bb());

			eq(false,$obj->is_pp());
			$obj->pp("hoge");
			eq("hoge",$obj->pp());
			eq(true,$obj->is_pp());
			$obj->pp("");
			eq(false,$obj->is_pp());			
			$obj->pp("");
			eq("",$obj->pp());
			$obj->pp(null);
			eq(null,$obj->pp());

			eq(false,$obj->is_cc());
			$obj->cc(1);
			eq(true,$obj->is_cc());
			$obj->cc(0);
			eq(true,$obj->is_cc());
			$obj->cc("");
			eq(null,$obj->cc());

			eq(false,$obj->is_dd());
			$obj->dd(1);
			eq(true,$obj->is_dd());
			$obj->dd(0);
			eq(true,$obj->is_dd());
			$obj->dd(-1.2);
			eq(-1.2,$obj->dd());

			eq(false,$obj->is_ee());
			$obj->ee(true);
			eq(true,$obj->is_ee());
			$obj->ee(false);
			eq(false,$obj->is_ee());

			eq(false,$obj->is_ff());
			$obj->ff("2009/04/27 12:00:00");
			eq(true,$obj->is_ff());

			eq(false,$obj->is_ll());
			$obj->ll("2009/04/27");
			eq(true,$obj->is_ll());
			
			eq(false,$obj->is_gg());
			$obj->gg("12:00:00");
			eq(true,$obj->is_gg());
			eq(43200,$obj->gg());
			$obj->gg("12:00");
			eq(720,$obj->gg());
			eq("12:00",$obj->fm_gg());

			$obj->gg("12:00.345");
			eq(720.345,$obj->gg());
			eq("12:00.345",$obj->fm_gg());
			try{
				$obj->gg("1:2:3:4");
				fail();
			}catch(Exception $e){
				success();
			}
			$obj->gg("20時40分50秒");
			eq("20:40:50",$obj->fm_gg());

			eq(false,$obj->is_hh());
			$obj->hh("abc");
			eq(true,$obj->is_hh());

			eq(false,$obj->is_ii());
			eq(false,$obj->is_ii("hoge"));
			$obj->ii("hoge","abc");
			eq(true,$obj->is_ii());
			eq(true,$obj->is_ii("hoge"));
			$obj->ii(array("A"=>"def","B"=>"ghi"));
			eq(true,$obj->is_ii("A"));
			eq(true,$obj->is_ii("B"));
			eq("ghi",$obj->in_ii("B"));
			eq(array("A"=>"def","B"=>"ghi"),$obj->rm_ii("A","B"));
			eq(null,$obj->in_ii("A"));
			eq(null,$obj->in_ii("C"));
			eq(null,$obj->rm_ii("C"));
			eq(true,$obj->is_ii());
			eq(array("hoge"=>"abc"),$obj->rm_ii());
			eq(false,$obj->is_ii());
			eq(array(),$obj->rm_ii("A","B"));

			eq(false,$obj->is_jj());
			eq(false,$obj->is_jj(0));
			$obj->jj("abc");
			eq(true,$obj->is_jj(0));
			$obj->jj(array("def","ghi"));
			eq("def",$obj->in_jj(1));
			eq(true,$obj->is_jj(1));
			eq(true,$obj->is_jj(2));

			try{
				$obj->kk("Abc@example.com");
				success();
			}catch(Exception $e){
				fail();
			}
			try{
				$obj->kk("123@example.com");
				success();
			}catch(Exception $e){
				fail();
			}
			try{
				$obj->kk("user+mailbox/department=shipping@example.com");
				success();
			}catch(Exception $e){
				fail();
			}
			try{
				$obj->kk("!#$%&'*+-/=?^_`.{|}~@example.com");
				success();
			}catch(Exception $e){
				fail();
			}
			try{
				$obj->kk("Abc.@example.com");
				fail();
			}catch(Exception $e){
				success();
			}
			try{
				$obj->kk("Abc..123@example.com");
				fail();
			}catch(Exception $e){
				success();
			}
			try{
				$obj->kk(".Abc@example.com");
				fail();
			}catch(Exception $e){
				success();
			}
			try{
				$obj->kk("Abc@.example.com");
				fail();
			}catch(Exception $e){
				success();
			}
			try{
				$obj->kk("Abc@example.com.");
				fail();
			}catch(Exception $e){
				success();
			}
			eq(null,$obj->nn());
			try{
				$obj->nn("1004");
				fail();
			}catch(Exception $e){
				success();
			}
			eq(123451004,$obj->nn("123451004"));
			eq("12345",$obj->fm_nn("Y"));
			eq(91004,$obj->nn("91004"));
			eq(20091004,$obj->nn("20091004"));
			eq(20091004,$obj->nn("2009/10/04"));
			eq(20091004,$obj->nn("2009/10/4"));
			eq(20090104,$obj->nn("2009/1/4"));
			eq(19000104,$obj->nn("1900/1/4"));
			eq(6450104,$obj->nn("645 1 4"));
			eq(6450104,$obj->nn("645年1月4日"));
			eq("645/01/04",$obj->fm_nn());
			eq("645",$obj->fm_nn("Y"));
			eq("6450104",$obj->fm_nn("Ymd"));
			eq("645年01月04日",$obj->fm_nn("Y年m月d日"));
			eq(19810204,$obj->nn("1981-02-04"));

			eq(false,$obj->is_mm());
			$obj->mm("abc123_");
			eq(true,$obj->is_mm());
			try{
				$obj->mm("/abc");
				fail();
			}catch(Exception $e){
				success();
			}
			eq(false,$obj->is_oo());
			$obj->oo(123);			
			eq(123,$obj->oo());
			$obj->oo("456");
			eq(456,$obj->oo());
			$obj->oo(-123);
			eq(-123,$obj->oo());			
			
			try{
				$obj->oo("123F");
				fail();
			}catch(Exception $e){
				success();
			}
			try{
				$obj->oo(123.45);
				fail();
			}catch(Exception $e){
				success();
			}
			try{
				$obj->oo("123.0");
				success();
			}catch(Exception $e){
				fail();
			}
			
			try{
				$obj->oo("123.000000001");
				fail();
			}catch(Exception $e){
				success();
			}
			try{
				$obj->oo(123.000000001);
				fail();
			}catch(Exception $e){
				success();
			}
			try{
				$obj->oo("123.0000000001");
				fail();
			}catch(Exception $e){
				success();
			}
			try{
				$obj->oo(123.0000000001);
				fail();
			}catch(Exception $e){
				success();
			}
			try{
				$obj->oo(123.0);
				success();
			}catch(Exception $e){
				fail();
			}
			$obj->qq(2);
			eq(2,$obj->qq());
			$obj->qq(3.123);
			eq(3.12,$obj->qq());
			$obj->qq(123.554);
			eq(123.55,$obj->qq());
			$obj->qq(123.555);
			eq(123.55,$obj->qq());
			$obj->qq(123.556);
			eq(123.55,$obj->qq());
			$obj->qq(0);
			eq(0,$obj->qq());
			$obj->qq(123456789.01);
			eq(123456789.01,$obj->qq());
			$obj->qq(123456789.1);
			eq(123456789.1,$obj->qq());			
		*/
	}
	final public function __construct(){
		$c = get_class($this);
		foreach(array_keys(get_object_vars($this)) as $name){
			if($name[0] != '_'){
				$ref = new ReflectionProperty($c,$name);
				if(!$ref->isPrivate()) $this->_m[2][] = $name;
			}
		}
		$a = (func_num_args() === 1) ? func_get_arg(0) : null;
		if(!is_string($a) || strpos($a,'_static_=true') === false){
			$this->_m[4] = false;
			$init = true;

			if(!isset(self::$_sm[0][$c])){
				self::$_sm[0][$c] = array();
				$d = null;
				$r = new ReflectionClass($this);
				while($r->getName() != __CLASS__){
					$d = $r->getDocComment().$d;
					$r = $r->getParentClass();
				}
				$d = preg_replace("/^[\s]*\*[\s]{0,1}/m",'',str_replace(array('/'.'**','*'.'/'),'',$d));
				if(preg_match_all("/@var\s([\w_]+[\[\]\{\}]*)\s\\\$([\w_]+)(.*)/",$d,$m)){
					foreach($m[2] as $k => $n){
						$p = (false !== ($s = strpos($m[3][$k],'@{'))) ? json_decode(substr($m[3][$k],$s+1,strrpos($m[3][$k],'}')-$s),true) : array();
						if(!is_array($p)) throw new LogicException('JSON error `'.$n.'`');
						self::$_sm[0][$c][$n] = (isset(self::$_sm[0][$c][$n])) ? array_merge(self::$_sm[0][$c][$n],$p) : $p;
						if(false != ($h = strpos($m[1][$k],'{}')) || false !== ($l = strpos($m[1][$k],'[]'))){
							self::$_sm[0][$c][$n]['type'] = substr($m[1][$k],0,-2);
							self::$_sm[0][$c][$n]['attr'] = (isset($h) && $h !== false) ? 'h' : 'a';
						}else{
							self::$_sm[0][$c][$n]['type'] = $m[1][$k];
						}
						foreach(array_keys(self::$_sm[0][$c]) as $n){
							if(self::$_sm[0][$c][$n]['type'] == 'serial'){
								self::$_sm[0][$c][$n]['primary'] = true;
							}else if(self::$_sm[0][$c][$n]['type'] == 'choice' && method_exists($this,'__choices_'.$n.'__')){
								self::$_sm[0][$c][$n]['choices'] = $this->{'__choices_'.$n.'__'}();
							}
						}
					}
				}
				if(preg_match_all("/@class\s.*@(\{.*\})/",$d,$m)){
					foreach($m[1] as $j){
						$p = json_decode($j,true);
						if(!is_array($p)) throw new LogicException('JSON error @class');
						self::$_sm[1][$c] = array_merge((isset(self::$_sm[1][$c]) ? self::$_sm[1][$c] : array()),$p);
					}
				}
				if(method_exists($this,'__anon__')) $this->__anon__($d);
			}
			if(method_exists($this,'__new__')){
				$args = func_get_args();
				call_user_func_array(array($this,'__new__'),$args);
			}else if(!empty($a) && is_string($a) && preg_match_all("/.+?[^\\\],|.+?$/",$a,$m)){
				$init = (strpos($a,'_init_=false') === false);
				foreach($m[0] as $g){
					if(strpos($g,'=') !== false){
						list($n,$v) = explode('=',$g,2);
						if($n[0] != '_'){
							if(!in_array($n,$this->_m[2])) throw new ErrorException(get_class($this).'::'.$n.' property not found');
							if(substr($v,-1) == ',') $v = substr($v,0,-1);
							$this->{$n}(($v === '') ? null : str_replace("\\,",',',preg_replace("/^([\"\'])(.*)\\1$/","\\2",$v)));
						}
					}
				}
			}
			if($init && method_exists($this,'__init__')) $this->__init__();
		}
		/***
			$name1 = create_class('protected $aaa="A1";');
			$name2 = create_class('
						protected $bbb="B";
					');
			$obj2 = new $name2;
			$obj2->add_object(new $name1());
			eq("A1",$obj2->aaa());
			eq("B",$obj2->bbb());
			
			$name1 = create_class('protected $aaa="A1";');
			$name2 = create_class('
						protected $bbb="B";
						protected $aaa="A2";

						public function aaa2(){
							return $this->aaa;
						}
						public function aaa3(){
							return $this->aaa();
						}
					');
			$obj2 = new $name2;
			$obj2->add_object(new $name1());

			eq("A1",$obj2->aaa());
			eq("B",$obj2->bbb());
			eq("A2",$obj2->aaa2());
			eq("A1",$obj2->aaa3());

			$obj2->aaa("Z");
			eq("Z",$obj2->aaa());
			eq("B",$obj2->bbb());
			eq("A2",$obj2->aaa2());
			eq("Z",$obj2->aaa3());
			
			$name = create_class('
					public $aaa;
					public $bbb;
					public $ccc;
					public $ddd;
				',null,'
					@var boolean $ccc
					@var number $ddd
				');
			$hoge = new $name("aaa=hoge");
			eq("hoge",$hoge->aaa());
			eq(null,$hoge->bbb());
			$hoge = new $name("ccc=true");
			eq(true,$hoge->ccc());
			$hoge = new $name("ddd=123");
			eq(123,$hoge->ddd());
			$hoge = new $name("ddd=123.45");
			eq(123.45,$hoge->ddd());

			$hoge = new $name("bbb=fuga,aaa=hoge");
			eq("hoge",$hoge->aaa());
			eq("fuga",$hoge->bbb());
		*/
	}
	final public function __destruct(){
		if(method_exists($this,'__del__')) $this->__del__();
	}
	final public function __toString(){
		return (string)$this->__str__();
	}
	final public function __clone(){
		if(method_exists($this,'__clone__')){
			$this->__clone__();
		}else{
			$this->_m[2] = unserialize(serialize($this->_m[2]));
			$this->_m[0] = unserialize(serialize($this->_m[0]));
			$this->_m[1] = unserialize(serialize($this->_m[1]));
			$this->_m[3] = array();
		}
	}
	/**
	 * プロパティ名を返す
	 * @return string{}
	 */
	final public function props(){
		$r = $this->_m[2];
		foreach($this->_m[0] as $o) $r = array_merge($r,$o->props());
		return array_keys(array_flip($r));
		/***
			$name1 = create_class('
				public $public;
				protected $protected;
				private $private;
				
				protected function __init__(){
					$this->public = "public";
					$this->protected = "protected";
					$this->private = "private";
				}
			');
			
			$obj = new $name1();
			eq("public",$obj->public());
			eq("protected",$obj->protected());
			try{
				$obj->private();
				fail();
			}catch(ErrorException $e){
				success();
			}
		 */
	}
	/**
	 * get可能なオブジェクトのプロパティを返す
	 * @return mixed{} (name => value)
	 */
	final public function prop_values(){
		$result = array();
		foreach($this->props() as $name){
			if($this->a($name,'get') !== false) $result[$name] = $this->{$name}();
		}
		return $result;
		/***
			$name1 = create_class('
						public $public_var = 1;
						protected $protected_var = 2;
						private $private_var = 3;

						public function vars(){
							$result = array();
							foreach($this->prop_values() as $k => $v) $result[$k] = $v;
							return $result;
						}
					');
			$obj = new $name1();
			eq(array("public_var"=>1,"protected_var"=>2),$obj->vars());
			$obj->add_var = 4;
			eq(array("public_var"=>1,"protected_var"=>2,"add_var"=>4),$obj->vars());

			$name2 = create_class('
						public $e_public_var = 1;
						protected $e_protected_var = 2;
						private $e_private_var = 3;
					',$name1);
			$obj2 = new $name2();
			eq(array("e_public_var"=>1,"e_protected_var"=>2,"public_var"=>1,"protected_var"=>2),$obj2->vars());
			$obj2->add_var = 4;
			eq(array("e_public_var"=>1,"e_protected_var"=>2,"public_var"=>1,"protected_var"=>2,"add_var"=>4),$obj2->vars());
		*/
	}
	/**
	 * 文字列表現を返す
	 * @return string
	 */
	final public function str(){
		return (string)$this->__str__();
	}
	final protected function prop_anon($name){
		if(isset($this->_m[3][$name])) return $this->_m[3][$name];
		$c = get_class($this);
		if(isset(self::$_sm[0][$c][$name])){
			return self::$_sm[0][$c][$name];
		}else{
			foreach($this->_m[0] as $o){
				if(null !== ($a = $o->prop_anon($name))) return $a;
			}
		}
		return array();
	}
	/**
	 * クラスのアノテーションを取得
	 * @param string $a アノテーション名
	 * @param mixed $d デフォルト値
	 * @return mixed
	 */
	final public function anon($a,$d=null){
		return isset(self::$_sm[1][$this->get_called_class()][$a]) ? self::$_sm[1][$this->get_called_class()][$a] : $d;
	}
	/**
	 * アノテーションの値を取得/設定
	 * @param string $v 変数名
	 * @param string $a アノテーション名
	 * @param mixed $d 設定する値
	 * @@aram boolean $f
	 * @return mixed
	 */
	final public function a($v,$a,$d=null,$f=false){
		$p = $this->prop_anon($v);
		if($f) $this->_m[3][$v][$a] = $d;
		return isset($p[$a]) ? $p[$a] : $d;
		/***
			# get_a
			$class1 = create_class('
				protected $aaa;
				protected $bbb;
				protected $ccc;

				protected function __choices_ccc__(){
					return array("111","222",333);
				}
			',null,'
				@var choice $aaa @{"choices":["AA","BB","CC"]}
				@var choice $bbb @{"choices":["aaa","bbb","cc,c"]}
				@var choice $ccc
			');
			$obj = new $class1();
			$obj->aaa("BB");
			$obj->bbb("bbb");
			$obj->ccc("222");

			eq(array("AA","BB","CC"),$obj->a("aaa","choices"));
			eq(array("aaa","bbb","cc,c"),$obj->a("bbb","choices"));
			eq(array("111","222","333"),$obj->a("ccc","choices"));
		 */
		/***
			#get_a_mixin
			$name1 = create_class('
				protected $aaa = "AAA";
			',null,'
				@var string $aaa
			');
			$name2 = create_class('
				protected $bbb = "BBB";
			',null,'
				@var integer $bbb
			');
			$obj = new $name2();
			$obj->add_object(new $name1());
			eq("integer",$obj->a("bbb","type"));
			eq("string",$obj->a("aaa","type"));
		 */
	}
	/**
	 * 追加されたモジュールを参照する
	 * @param string $name
	 * @return object
	 */
	final public function o($name){
		return $this->_m[0][$name];
	}
	/**
	 * クラスアクセスとして返す
	 * @param string $class_name クラス名
	 * @return object
	 */
	final static public function c($class_name){
		if(!is_subclass_of($class_name,__CLASS__)) throw new BadMethodCallException('Processing not permitted [static]');
		$obj = new $class_name('_static_=true');
		if(!$obj->_m[4]) throw new BadMethodCallException('Processing not permitted [static]');
		return $obj;
	}
	/**
	 * クラスアクセスの場合にクラス名を返す
	 * @return string
	 */
	final public function get_called_class(){
		if(!$this->_m[4]) throw new BadMethodCallException('Processing not permitted [static]');
		return get_class($this);
	}
	protected function __str__(){
		return get_class($this);
	}
	final static private function set_assert($t,$v,$param){
		if($v === null) return null;
		switch($t){
			case null: return $v;
			case 'string': return str_replace(array("\r\n","\r","\n"),'',$v);
			case 'text': return is_bool($v) ? (($v) ? 'true' : 'false') : ((string)$v);
			default:
				if($v === '') return null;
				switch($t){
					case 'number':
						if(!is_numeric($v)) throw new InvalidArgumentException('must be an of '.$t);
						return (float)(isset($param['decimal_places']) ? (floor($v * pow(10,$param['decimal_places'])) / pow(10,$param['decimal_places'])) : $v);
					case 'serial':
					case 'integer':
						if(!is_numeric($v) || (int)$v != $v) throw new InvalidArgumentException('must be an of '.$t);
						return (int)$v;
					case 'boolean':
						if(is_string($v)){ $v = ($v === 'true' || $v === '1') ? true : (($v === 'false' || $v === '0') ? false : $v);
						}else if(is_int($v)){ $v = ($v === 1) ? true : (($v === 0) ? false : $v); }
						if(!is_bool($v)) throw new InvalidArgumentException('must be an of '.$t);
						return (boolean)$v;
					case 'timestamp':
					case 'date':
						if(ctype_digit((string)$v)) return (int)$v;
						if(preg_match('/^0+$/',preg_replace('/[^\d]/','',$v))) return null;
						$time = strtotime($v);
						if($time === false) throw new InvalidArgumentException('must be an of '.$t);
						return $time;
					case 'time':
						if(is_numeric($v)) return $v;
						$d = array_reverse(preg_split("/[^\d\.]+/",$v));
						if($d[0] === '') array_shift($d);
						list($s,$m,$h) = array((isset($d[0]) ? (float)$d[0] : 0),(isset($d[1]) ? (float)$d[1] : 0),(isset($d[2]) ? (float)$d[2] : 0));
						if(sizeof($d) > 3 || $m > 59 || $s > 59 || strpos($h,'.') !== false || strpos($m,'.') !== false) throw new InvalidArgumentException('must be an of '.$t);
						return ($h * 3600) + ($m*60) + ((int)$s) + ($s-((int)$s));
					case 'intdate':
						if(preg_match("/^\d\d\d\d\d+$/",$v)){
							$v = sprintf('%08d',$v);
							list($y,$m,$d) = array((int)substr($v,0,-4),(int)substr($v,-4,2),(int)substr($v,-2,2));
						}else{
							$x = preg_split("/[^\d]+/",$v);
							if(sizeof($x) < 3) throw new InvalidArgumentException('must be an of '.$t);
							list($y,$m,$d) = array((int)$x[0],(int)$x[1],(int)$x[2]);
						}
						if($m < 1 || $m > 12 || $d < 1 || $d > 31 || (in_array($m,array(4,6,9,11)) && $d > 30) || (in_array($m,array(1,3,5,7,8,10,12)) && $d > 31)
							|| ($m == 2 && ($d > 29 || (!(($y % 4 == 0) && (($y % 100 != 0) || ($y % 400 == 0)) ) && $d > 28)))
						) throw new InvalidArgumentException('must be an of '.$t);
						return (int)sprintf('%d%02d%02d',$y,$m,$d);
					case 'email':
						if(!preg_match('/^[\w\''.preg_quote('./!#$%&*+-=?^_`{|}~','/').']+@(?:[A-Z0-9-]+\.)+[A-Z]{2,6}$/i',$v) 
							|| strlen($v) > 255 || strpos($v,'..') !== false || strpos($v,'.@') !== false || $v[0] === '.') throw new InvalidArgumentException('must be an of '.$t);
						return $v;
					case 'alnum':
						if(!ctype_alnum(str_replace('_','',$v))) throw new InvalidArgumentException('must be an of '.$t);
						return $v;
					case 'choice':
						$v = is_bool($v) ? (($v) ? 'true' : 'false') : $v;
						if(!isset($param['choices']) || !in_array($v,$param['choices'],true)) throw new InvalidArgumentException('must be an of '.$t);
						return $v;
					case 'mixed': return $v;
					default:
						if(!($v instanceof $t)) throw new InvalidArgumentException('must be an of '.$t);
						return $v;
				}
		}
	}
	final protected function ___get___(){
		list($o,$n) = $this->_;
		if($o->a($n,'get') === false) throw new InvalidArgumentException('Processing not permitted [get()]');
		if($o->a($n,'attr') !== null) return (is_array($o->{$n})) ? $o->{$n} : (is_null($o->{$n}) ? array() : array($o->{$n}));
		return ($o instanceof $this) ? $o->{$n} : $o->{$n}();
	}
	final protected function ___set___(){
		list($o,$n) = $this->_;
		if($o->a($n,'set') === false) throw new InvalidArgumentException('Processing not permitted [set()]');
		$a = func_get_args();
		if(!($o instanceof $this)) return call_user_func_array($this->_,$a);
		$p = $o->prop_anon($n);
		if(func_num_args() == 1 && $a[0] === null){
			$o->{$n} = (($o->a($n,'attr') === null) ? null : array());
		}else{
			switch($o->a($n,'attr')){
				case 'a':
					$a = (is_array($a[0])) ? $a[0] : array($a[0]);
					foreach($a as $v) $o->{$n}[] = call_user_func_array(array(__CLASS__,'set_assert'),array($o->a($n,'type'),$v,$p));
					break;
				case 'h':
					$a = (sizeof($a) === 2) ? array($a[0]=>$a[1]) : (is_array($a[0]) ? $a[0] : array((($a[0] instanceof self) ? $a[0]->str() : $a[0])=>$a[0]));
					foreach($a as $k => $v) $o->{$n}[$k] = call_user_func_array(array(__CLASS__,'set_assert'),array($o->a($n,'type'),$v,$p));
					break;
				default:
					$o->{$n} = call_user_func_array(array(__CLASS__,'set_assert'),array($o->a($n,'type'),$a[0],$p));
			}
		}
		return $o->{$n};
	}
	final protected function ___rm___(){
		list($o,$n) = $this->_;
		if($o->a($n,'set') === false) throw new InvalidArgumentException('Processing not permitted [set()]');
		$a = func_get_args();
		if(!($o instanceof $this)) return call_user_func_array(array($this->_[0],'rm_'.$this->_[1]),$a);
		$r = call_user_func($this->_);
		$r = is_object($r) ? clone($r) : $r;
		
		if($o->a($n,'attr') === null){
			$o->{$n} = null;
		}else{
			if(empty($a) || empty($o->{$n})){
				$o->{$n} = array();
			}else{
				$v = array();
				foreach($a as $k){
					if(array_key_exists($k,$o->{$n})){
						$v[$k] = is_object($r[$k]) ? clone($r[$k]) : $r[$k];
						$o->{$n}[$k];
						unset($o->{$n}[$k]);
					}
				}
				$r = $v;
				if(sizeof($a) == 1) $r = empty($r) ? null : array_shift($r);				
			}
		}
		return $r;
	}
	final protected function ___fm___($f=null,$d=null){
		list($o,$n) = $this->_;
		$v = call_user_func($this->_);
		switch($o->a($n,'type')){
			case 'timestamp': return ($v === null) ? null : (date((empty($f) ? 'Y/m/d H:i:s' : $f),(int)$v));
			case 'date': return ($v === null) ? null : (date((empty($f) ? 'Y/m/d' : $f),(int)$v));
			case 'time':
				if($v === null) return 0;
				$h = floor($v / 3600);
				$i = floor(($v - ($h * 3600)) / 60);
				$s = floor($v - ($h * 3600) - ($i * 60));
				$m = str_replace(' ','0',rtrim(str_replace('0',' ',(substr(($v - ($h * 3600) - ($i * 60) - $s),2,12)))));
				return (($h == 0) ? '' : $h.':').(sprintf('%02d:%02d',$i,$s)).(($m == 0) ? '' : '.'.$m);
			case 'intdate': if($v === null) return null;
							return str_replace(array('Y','m','d'),array(substr($v,0,-4),substr($v,-4,2),substr($v,-2,2)),(empty($f) ? 'Y/m/d' : $f));
			case 'boolean': return ($v) ? (isset($d) ? $d : '') : (empty($f) ? 'false' : $f);
		}
		return $v;
	}
	final protected function ___label___(){
		list($o,$n) = $this->_;
		$label = $o->a($n,'label');
		return isset($label) ? $label : $n;
	}
	final protected function ___ar___($i=null,$j=null){
		list($o,$n) = $this->_;
		$v = call_user_func($this->_);
		$a = is_array($v) ? $v : (($v === null) ? array() : array($v));
		if(isset($i)){
			$c = 0;
			$l = ((isset($j) ? $j : sizeof($a)) + $i);
			$r = array();
			foreach($a as $k => $p){
				if($i <= $c && $l > $c) $r[$k] = $p;
				$c++;
			}
			return $r;
		}
		return $a;
	}
	final protected function ___in___($k=null,$d=null){
		$v = call_user_func($this->_);
		return (isset($k)) ? ((is_array($v) && isset($v[$k]) && $v[$k] !== null) ? $v[$k] : $d) : $d;
	}
	final protected function ___is___($k=null){
		list($o,$n) = $this->_;
		$v = call_user_func($this->_);
		
		if($o->a($n,'attr') !== null){
			if($k === null) return !empty($v);
			$v = isset($v[$k]) ? $v[$k] : null;
		}
		switch($o->a($n,'type')){
			case 'string':
			case 'text': return (isset($v) && $v !== '');
		}
		return (boolean)(($o->a($n,'type') == 'boolean') ? $v : isset($v));
	}
	/***
		# label
		$class1 = create_class('
			protected $aaa;
			protected $bbb;
			protected $ccc;
			protected $ddd;
			protected $eee;
		',null,'
			@var mixed $aaa @{"label":"hoge"}
			@var mixed $ccc @{"label":"abc def"}
			@var string $ddd @{"label":"abc def"}
			@var string $eee @{"label":"abc def"}
		');
		$obj = new $class1();
		eq("hoge",$obj->label_aaa());
		eq("bbb",$obj->label_bbb());
		eq("abc def",$obj->label_ccc());
		eq("abc def",$obj->label_ddd());
		eq("abc def",$obj->label_eee());
	 */
}