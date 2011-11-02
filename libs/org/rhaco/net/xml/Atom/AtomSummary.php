<?php
/**
 * @author Kazutaka Tokushima
 * @license New BSD License
 */
class AtomSummary extends Object{
	protected $type;
	protected $lang;
	protected $value;

	protected function __str__(){
		if($this->none()) return "";
		$result = new Tag("summary");
		foreach($this->prop_values() as $name => $value){
			if(!empty($value)){
				switch($name){
					case "type":
						$result->param($name,$value);
						break;
					case "lang":
						$result->param("xml:".$name,$value);
						break;
					case "value":
						$result->value(Tag::xmltext($value));
						break;
				}
			}
		}
		return $result->get();
	}
	static public function parse(&$src){
		$result = null;
		if(Tag::setof($tag,$src,"summary")){
			$result = new self();
			$result->type($tag->in_param("type","text"));
			$result->lang($tag->in_param("xml:lang"));
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