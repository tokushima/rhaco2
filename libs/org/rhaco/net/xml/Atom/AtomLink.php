<?php
/**
 * @author Kazutaka Tokushima
 * @license New BSD License
 */
class AtomLink extends Object{
	protected $rel;
	protected $type;
	protected $href;

	protected function __str__(){
		if($this->none()) return "";
		$result = new Tag("link");
		foreach($this->prop_values() as $name => $value){
			if(!empty($value)){
				switch($name){
					case "href":
					case "rel":
					case "type":
						$result->param($name,$value);
						break;
				}
			}
		}
		return $result->get();
	}
	static public function parse(&$src){
		$result = array();
		foreach(Tag::anyhow($src)->in("link") as $in){
			$o = new self();
			$o->href($in->in_param("href"));
			$o->rel($in->in_param("rel"));
			$o->type($in->in_param("type"));
			$result[] = $o;
			$src = str_replace($in->plain(),"",$src);
		}
		return $result;
	}
	public function none(){
		foreach($this->prop_values() as $var => $value){
			if(!empty($value)) return false;
		}
		return true;
	}
}