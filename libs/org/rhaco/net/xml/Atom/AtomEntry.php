<?php
import("org.rhaco.lang.DateUtil");
module("AtomAuthor");
module("AtomLink");
module("AtomContent");
module("AtomSummary");
/**
 * @author Kazutaka Tokushima
 * @license New BSD License
* @var timestamp $published
 * @var timestamp $updated
 * @var timestamp $issued
 * @var AtomContent $content
 * @var AtomSummary $summary
 * @var AtomLink[] $link
 * @var AtomAuthor[] $author
 */
class AtomEntry extends Object{
	protected $id;
	protected $title;
	protected $published;
	protected $updated;
	protected $issued;
	protected $xmlns;

	protected $content;
	protected $summary;
	protected $link;
	protected $author;

	protected $extra;

	protected function __init__(){
		$this->published = time();
		$this->updated = time();
		$this->issued = time();
	}
	
	public function get($enc=false){
		$value = sprintf("%s",$this);
		return (($enc) ? (sprintf("<?xml version=\"1.0\" encoding=\"%s\"?>\n",mb_detect_encoding($value))) : "").$value;
	}
	protected function __str__(){
		if($this->none()) return "";
		$result = new Tag("entry");
		foreach($this->prop_values() as $name => $value){
			if(!empty($value)){
				switch($name){
					case "xmlns":
						$result->param("xmlns",$value);
						break;
					case "id":
					case "title":
						$result->add(new Tag($name,$value));
						break;
					case "published":
					case "updated":
					case "issued":
						$result->add(new Tag($name,DateUtil::format_atom($value)));
						break;
					case "extra":
						foreach($this->{$name}->prop_values() as $value){
							if(!empty($value)){
								foreach($this->{$name}->prop_values() as $name => $value){
									$result->add(new Tag($name,$value));
								}
								break;
							}
						}
						break;
					default:
						if(is_array($this->{$name})){
							foreach($this->{$name} as $o) $result->add($o);
							break;
						}else if(is_object($this->{$name})){
							$result->add($value);
							break;
						}else{
							$result->add(new Tag($name,$value));
							break;
						}
				}
			}
		}
		return $result->get();
	}
	public function first_href(){
		return (!empty($this->link)) ? current($this->link)->href() : null;
	}
	protected function __fm_content__(){
		return (isset($this->content)) ? $this->content->value() : null;
	}
	public function parse_extra(&$src){
		$this->extra = new Object();
		$this->call_module("parse",$src,$this->extra);
	}
	static public function parse(&$src){
		$args = func_get_args();
		array_shift($args);

		$result = array();
		foreach(Tag::anyhow($src)->in("entry") as $in){
			$o = new self();
			foreach($args as $module) $o->add_module($module);
			$o->id($in->f("id.value()"));
			$o->title($in->f("title.value()"));
			$o->published($in->f("published.value()"));
			$o->updated($in->f("updated.value()"));
			$o->issued($in->f("issued.value()"));

			$value = $in->value();
			$o->content = AtomContent::parse($value);
			$o->summary = AtomSummary::parse($value);
			$o->link = AtomLink::parse($value);
			$o->author = AtomAuthor::parse($value);
			$o->parse_extra($value);

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