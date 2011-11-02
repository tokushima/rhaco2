<?php
/**
 * @author Kazutaka Tokushima
 * @license New BSD License
 */
class AtomContent extends Object{
	protected $type;
	protected $mode;
	protected $lang;
	protected $base;
	protected $value;

	protected function __str__(){
		if($this->none()) return "";
		$result = new Tag("content");
		foreach($this->prop_values() as $name => $value){
			if(!empty($value)){
				switch($name){
					case "type":
					case "mode":
						$result->param($name,$value);
						break;
					case "lang":
					case "base":
						$result->param("xml:".$name,$value);
						break;
					case "value":
						$result->value($this->{$name}());
						break;
				}
			}
		}
		return $result->get();
	}
	static public function parse(&$src){
		$result = null;
		if(Tag::setof($tag,$src,"content")){
			$result = new self();
			$result->type($tag->in_param("type"));
			$result->mode($tag->in_param("mode"));
			$result->lang($tag->in_param("xml:lang"));
			$result->base($tag->in_param("xml:base"));
			$result->value($tag->value());
			$src = str_replace($tag->plain(),"",$src);
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