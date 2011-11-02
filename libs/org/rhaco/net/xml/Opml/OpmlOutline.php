<?php
/**
 * @author Kazutaka Tokushima
 * @license New BSD License
 * @var boolean $comment
 * @var boolean $breakpoint
 * @var OpmlOutline[] $outline
 */
class OpmlOutline extends Object{
	protected $text;
	protected $type;
	protected $value;
	protected $comment;
	protected $breakpoint;
	protected $htmlUrl;
	protected $xmlUrl;
	protected $title;
	protected $description;
	protected $outline;
	protected $tags;

	public function html(){
		$list = array();
		if($this->is_htmlUrl()) $list[] = $this;
		foreach($this->ar_outline() as $outline) $list = array_merge($list,$outline->html());
		return $list;
	}
	public function xml(){
		$list = array();
		if($this->is_xmlUrl()) $list[] = $this;
		foreach($this->ar_outline() as $outline) $list = array_merge($list,$outline->xml());
		return $list;
	}
	protected function __str__(){
		/***
		 * $src = '<outline title="りあふ の にっき" htmlUrl="http://riaf.g.hatena.ne.jp/riaf/" type="rss" xmlUrl="http://riaf.g.hatena.ne.jp/riaf/rss2" />';
		 * $xml = OpmlOutline::parse($src);
		 * eq($src,$xml->str());
		 */
		$outTag	= new Tag("outline");
		if($this->is_title()) $outTag->param("title",$this->title());
		if($this->is_htmlUrl()) $outTag->param("htmlUrl",$this->htmlUrl());
		if($this->is_type()) $outTag->param("type",$this->type());
		if($this->is_xmlUrl()) $outTag->param("xmlUrl",$this->xmlUrl());
		if($this->is_comment()) $outTag->param("isComment",$this->is_comment());
		if($this->is_breakpoint()) $outTag->param("isBreakpoint",$this->is_breakpoint());
		if($this->is_text()) $outTag->param("text",$this->text());
		if($this->is_description()) $outTag->param("description",$this->description());
		if($this->is_tags()) $outTag->param("tags",$this->tags());
		$outTag->add($this->value());
		foreach($this->ar_outline() as $outline) $outTag->add($outline);
		return $outTag->get();
	}
	
	static public function parse($src,$tags=""){
		$result = null;
		if(Tag::setof($tag,$src,"outline")){
			$result = new self();
			$result->text($tag->in_param("text"));
			$result->type($tag->in_param("type"));
			$result->comment($tag->in_param("isComment",false));
			$result->breakpoint($tag->in_param("isBreakpoint",false));

			$result->htmlUrl($tag->in_param("htmlUrl"));
			$result->xmlUrl($tag->in_param("xmlUrl"));
			$result->title($tag->in_param("title"));
			$result->description($tag->in_param("description"));
			$result->tags($tags);

			foreach($tag->in("outline") as $outlinetag){
				$result->outline(self::parse($outlinetag->plain(),$tags));
			}
		}
		return $result;
		/***
		 * $src = '<outline title="りあふ の にっき" htmlUrl="http://riaf.g.hatena.ne.jp/riaf/" type="rss" xmlUrl="http://riaf.g.hatena.ne.jp/riaf/rss2" />';
		 * $xml = OpmlOutline::parse($src);
		 * eq("りあふ の にっき",$xml->title());
		 * eq("http://riaf.g.hatena.ne.jp/riaf/rss2",$xml->xmlUrl());
		 * eq("http://riaf.g.hatena.ne.jp/riaf/",$xml->htmlUrl());
		 * eq("rss",$xml->type());
		 *
		 */
	}
}
