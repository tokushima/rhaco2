<?php
/**
 * DBへのクエリモデル
 * @author tokushima
 * @license New BSD License
 * @var mixed[] $vars
 */
class Daq extends Object{
	static private $count = 0;
	protected $sql;
	protected $vars = array();
	protected $id;

	protected function __cp__($vars){
		if(is_array($vars)){
			foreach($vars as $key => $value) $this->vars[$key] = $value;
		}else{
			$this->vars($vars);
		}
	}
	protected function __is_vars__(){
		return !empty($this->vars);
	}
	public function unique_sql(){
		$rep = array();
		$sql = $this->sql();

		if(preg_match_all("/[ct][\d]+/",$this->sql,$match)){
			foreach($match[0] as $m){
				if(!isset($rep[$m])) $rep[$m] = "q".self::$count++;
			}
			foreach($rep as $key => $value){
				$sql = str_replace($key,$value,$sql);
			}
		}
		return $sql;
	}
}