<?php
module("RssEnclosure");
module("RssSource");
/**
 * @author Kazutaka Tokushima
 * @license New BSD License
 * @var timestamp $pubDate
 * @var RssEnclosure[] $enclosure
 * @var RssSource[] $source
 */
class RssItem extends Object{
	protected $title;
	protected $link;
	protected $description;
	protected $author;
	protected $category;
	protected $comments;
	protected $pubDate;
	protected $guid;
	protected $enclosure;
	protected $source;

	protected function __str__(){
		$result = new Tag("item");
		foreach($this->prop_values() as $name => $value){
			if(!empty($value)){
				switch($name){
					case "title":
					case "link":
					case "description":
					case "author":
					case "category":
					case "comments":
					case "guid":
						$result->add(new Tag($name,$this->{$name}()));
						break;
					case "pubDate":
						$result->add(new Tag($name,$this->format_date($value)));
						break;
					default:
						if(is_array($this->{$name})){
							foreach($this->{$name} as $o) $result->add($o);
							break;
						}else if(is_object($this->{$name})){
							$result->add($value);
							break;
						}else{
							$result->add(new Tag($name,$this->{$name}()));
							break;
						}
				}
			}
		}
		return $result->get();
	}
	private function format_date($time){
		$tzd = date("O",$time);
		$tzd = $tzd[0].substr($tzd,1,2).":".substr($tzd,3,2);
		return date("Y-m-d\TH:i:s".$tzd,$time);
	}
	static public function parse($src){
		$result = array();
		foreach(Tag::anyhow($src)->in("item") as $in){
			$o = new self();
			$o->title($in->f("title.value()"));
			$o->link($in->f("link.value()"));
			$o->description($in->f("description.value()"));
			$o->author($in->f("author.value()"));
			$o->category($in->f("category.value()"));
			$o->comments($in->f("comments.value()"));
			$o->pubDate($in->f("pubDate.value()"));
			$o->guid($in->f("guid.value()"));
	
			$value = $in->value();
			$o->enclosure = RssEnclosure::parse($value);
			$o->source = RssSource::parse($src);
			$result[] = $o;
		}
		return $result;
	}
}