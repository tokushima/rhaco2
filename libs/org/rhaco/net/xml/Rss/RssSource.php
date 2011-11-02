<?php
/**
 * @author Kazutaka Tokushima
 * @license New BSD License
 */
class RssSource extends Object{
	protected $url;
	protected $value;

	protected function __str__(){
		$result = new Tag("source");
		foreach($this->prop_values() as $name => $value){
			if(!empty($value)){
				switch($name){
					case "url":
					case "value":
						$result->param($name,$value);
						break;
				}
			}
		}
		return $result->get();
	}
	static public function parse(&$src){
		$result = array();
		foreach(Tag::anyhow($src)->in("source") as $in){
			$src = str_replace($in->plain(),"",$src);
			$o = new self();
			$o->url($in->in_param("url"));
			$o->value($in->value());
			$result[] = $o;
		}
		return $result;
	}
}