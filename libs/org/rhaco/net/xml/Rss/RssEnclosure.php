<?php
/**
 * @author Kazutaka Tokushima
 * @license New BSD License
 * @var number $length
 */
class RssEnclosure extends Object{
	protected $url;
	protected $type;
	protected $length;

	protected function __str__(){
		$result = new Tag("enclosure");
		foreach($this->prop_values() as $name => $value){
			if(!empty($value)){
				switch($name){
					case "url":
					case "type":
					case "length":
						$result->param($name,$value);
						break;
				}
			}
		}
		return $result->get();
	}

	static public function parse(&$src){
		$result = array();
		foreach(Tag::anyhow($src)->in("enclosure") as $in){
			$src = str_replace($in->plain(),"",$src);
			$o = new self();
			$o->url($in->in_param("url"));
			$o->type($in->in_param("type"));
			$o->length($in->in_param("length"));
			$result[] = $o;
		}
		return $result;
	}
}