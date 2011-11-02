<?php
/**
 * @author Kazutaka Tokushima
 * @license New BSD License
 */
class AtomAuthor extends Object{
	protected $name;
	protected $url;
	protected $email;
	
	protected function __str__(){
		if($this->none()) return "";
		$result = new Tag("author");
		foreach($this->prop_values() as $name => $value){
			if(!empty($value)){
				switch($name){
					case "name":
					case "url":
					case "email":
						$result->add(new Tag($name,$value));
						break;
				}
			}
		}
		return $result->get();
	}
	static public function parse(&$src){
		$result = array();
		foreach(Tag::anyhow($src)->in("author") as $in){
			$src = str_replace($in->plain(),"",$src);
			$o = new self();
			$o->name($in->f("name.value()"));
			$o->url($in->f("url.value()"));
			$o->email($in->f("email.value()"));

			if(!$o->is_url()) $o->url($in->f("uri.value()"));
			$result[] = $o;
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