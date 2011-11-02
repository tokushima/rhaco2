<?php
import("org.rhaco.net.xml.Atom");
module("RssItem");
/**
 * Rss2.0を扱う
 * 
 * @author Kazutaka Tokushima
 * @license New BSD License
 * @var timestamp $lastBuildDate
 * @var timestamp $pubDate
 * @var RssItem[] $item
 * @var Tag[] $extra
 * @var string{} $xmlns
 */
class Rss extends Object{
	protected $version;
	protected $title;
	protected $link;
	protected $description;
	protected $language;
	protected $copyright;
	protected $docs;
	protected $lastBuildDate;
	protected $managingEditor;
	protected $pubDate;
	protected $webMaster;
	protected $item;
	protected $extra;
	protected $xmlns;

	protected function __init__(){
		$this->pubDate = time();
		$this->lastBuildDate = time();
		$this->version = "2.0";
	}
	public function add($arg){
		if($arg instanceof RssItem){
			$this->item[] = $arg;
		}else if($arg instanceof self){
			foreach($arg->ar_item() as $item) $this->item[] = $item;
		}else if($arg instanceof Tag){
			$this->extra($arg);
		}
		return $this;
	}
	protected function __fm_lastBuildDate__(){
		return date("D, d M Y H:i:s O",$this->lastBuildDate);
	}
	protected function __fm_pubDate__(){
		return date("D, d M Y H:i:s O",$this->pubDate);
	}
	protected function __str__(){
		$result = new Tag("rss");
		foreach($this->ar_xmlns() as $ns => $url) $result->param("xmlns:".$ns,$url);
		$channel = new Tag("channel");
		foreach($this->prop_values() as $name => $value){
			if(!empty($value)){
				switch($name){
					case "version":
						$result->param("version",$value);
						break;
					case "title":
					case "link":
					case "description":
					case "language":
					case "copyright":
					case "docs":
					case "managingEditor":
					case "webMaster":
						$channel->add(new Tag($name,$this->{$name}()));
						break;
					case "lastBuildDate":
					case "pubDate":
						$channel->add(new Tag($name,$this->format_date($value)));
						break;
					default:
						if(is_array($this->{$name})){
							foreach($this->{$name} as $o) $channel->add($o);
							break;
						}else if(is_object($this->{$name})){
							$channel->add($value);
							break;
						}else{
							$channel->add(new Tag($name,$this->{$name}()));
							break;
						}
				}
			}
		}
		$result->add($channel);
		return $result->get();
		/***
			$src = text('
						<rss version="2.0">
							<channel>
								<title>rhaco</title>
								<link>http://rhaco.org</link>
								<description>php</description>
								<language>ja</language>
								<copyright>rhaco.org</copyright>
								<docs>hogehoge</docs>
								<lastBuildDate>2007-10-10T10:10:10+09:00</lastBuildDate>
								<managingEditor>tokushima</managingEditor>
								<pubDate>2007-10-10T10:10:10+09:00</pubDate>
								<webMaster>kazutaka</webMaster>
								<item><title>rhaco</title><link>http://rhaco.org</link><description>rhaco desc</description></item>
								<item><title>everes</title><link>http://www.everes.net</link><description>everes desc</description></item>
							</channel>
						</rss>
					');
					$xml = Rss::parse($src);
					eq(str_replace(array("\n","\t"),"",$src),$xml->str());
		*/
	}
	private function format_date($time){
		$tzd = date("O",$time);
		$tzd = $tzd[0].substr($tzd,1,2).":".substr($tzd,3,2);
		return date("Y-m-d\TH:i:s".$tzd,$time);
	}
	/**
	 * 出力する
	 *
	 * @param string $name 出力する際のconent-typeの名前
	 */
	public function output($name=""){
		Log::disable_display();
		header(sprintf("Content-Type: application/rss+xml; name=%s",(empty($name)) ? uniqid("") : $name));
		print($this->get(true));
		exit;
	}
	/**
	 * 文字列に変換し取得
	 * @param boolean $enc encodingヘッダを付与するか
	 */
	public function get($enc=false){
		$value = sprintf("%s",$this);
		return (($enc) ? (sprintf("<?xml version=\"1.0\" encoding=\"%s\"?>\n",mb_detect_encoding($value))) : "").$value;
	}
	/**
	 * 文字列からRssを取得する
	 *
	 * @param string $src
	 * @return Rss
	 */
	static public function parse($src){
		if(Tag::setof($rss,$src,"rss")){
			$result = new self();
			$result->version($rss->in_param("version","2.0"));
			if(Tag::setof($channel,$rss->value(),"channel")){
				$result->title($channel->f("title.value()"));
				$result->link($channel->f("link.value()"));
				$result->description($channel->f("description.value()"));
				$result->language($channel->f("language.value()"));
				$result->copyright($channel->f("copyright.value()"));
				$result->docs($channel->f("docs.value()"));
				$result->managingEditor($channel->f("managingEditor.value()"));
				$result->webMaster($channel->f("webMaster.value()"));
				$result->lastBuildDate($channel->f("lastBuildDate.value()"));
				$result->pubDate($channel->f("pubDate.value()"));

				$value = $channel->value();
				$result->item = RssItem::parse($value);
				return $result;
			}
		}
		throw new Exception("no rss");
		/***
			 $src = text('
						<rss version="2.0">
							<channel>
								<title>rhaco</title>
								<link>http://rhaco.org</link>
								<description>php</description>
								<language>ja</language>
								<copyright>rhaco.org</copyright>
								<docs>hogehoge</docs>
								<lastBuildDate>2007-10-10T10:10:10+09:00</lastBuildDate>
								<managingEditor>tokushima</managingEditor>
								<pubDate>2007-10-10T10:10:10+09:00</pubDate>
								<webMaster>kazutaka</webMaster>
								<item>
									<title>rhaco</title>
									<link>http://rhaco.org</link>
									<description>rhaco desc</description>
								</item>
								<item>
									<title>everes</title>
									<link>http://www.everes.net</link>
									<description>everes desc</description>
								</item>
							</channel>
						</rss>
					');
					$xml = Rss::parse($src);
					eq("2.0",$xml->version());
					eq("rhaco",$xml->title());
					eq("http://rhaco.org",$xml->link());
					eq("php",$xml->description());
					eq("ja",$xml->language());
					eq("rhaco.org",$xml->copyright());
					eq("hogehoge",$xml->docs());
					eq(1191978610,$xml->lastBuildDate());
					eq("Wed, 10 Oct 2007 10:10:10 +0900",$xml->fm_lastBuildDate());
					eq("tokushima",$xml->managingEditor());
					eq(1191978610,$xml->pubDate());
					eq("Wed, 10 Oct 2007 10:10:10 +0900",$xml->fm_pubDate());
					eq("kazutaka",$xml->webMaster());
					eq(2,sizeof($xml->item()));
			*/
	}
	/**
	 * ソートする
	 * @return $this
	 */
	public function sort(){
		if($this->has_module("sort")) $this->item = $this->call_module("sort",$this->item);
		return $this;
	}
	/**
	 * Atomに変換したものを返す
	 * @return Atom
	 */
	public function atom(){
		$atom = new Atom();
		$atom->title($this->title());
		$atom->subtitle($this->description());
		$atom->generator($this->webMaster());
		$atom->updated($this->lastBuildDate());

		$link = new AtomLink();
		$link->href($this->link());
		$atom->link($link);

		foreach($this->ar_item() as $item){
			$entry = new AtomEntry();
			$entry->title($item->title());
			$entry->published($item->pubDate());

			$author = new AtomAuthor();
			$author->name($item->author());
			$entry->author($author);

			$link = new AtomLink();
			$link->href($item->link());
			$entry->link($link);

			$content = new AtomContent();
			$content->value($item->description());
			$entry->content($content);

			$summary = new AtomSummary();
			$summary->value($item->comments());
			$entry->summary($summary);

			$atom->add($entry);
		}
		return $atom;
		/***
			 $src = text('
						<rss version="2.0">
							<channel>
								<title>rhaco</title>
								<link>http://rhaco.org</link>
								<description>php</description>
								<language>ja</language>
								<copyright>rhaco.org</copyright>
								<docs>hogehoge</docs>
								<lastBuildDate>2007-10-10T10:10:10+09:00</lastBuildDate>
								<managingEditor>tokushima</managingEditor>
								<pubDate>2007-10-10T10:10:10+09:00</pubDate>
								<webMaster>kazutaka</webMaster>
								<item>
									<title>rhaco</title>
									<link>http://rhaco.org</link>
									<description>rhaco desc</description>
								</item>
								<item>
									<title>everes</title>
									<link>http://www.everes.net</link>
									<description>everes desc</description>
								</item>
							</channel>
						</rss>
					');
					$xml = Rss::parse($src);
					eq("2.0",$xml->version());
					eq("rhaco",$xml->title());
					eq("http://rhaco.org",$xml->link());
					eq("php",$xml->description());
					eq("ja",$xml->language());
					eq("rhaco.org",$xml->copyright());
					eq("hogehoge",$xml->docs());
					eq(1191978610,$xml->lastBuildDate());
					eq("Wed, 10 Oct 2007 10:10:10 +0900",$xml->fm_lastBuildDate());
					eq("tokushima",$xml->managingEditor());
					eq(1191978610,$xml->pubDate());
					eq("Wed, 10 Oct 2007 10:10:10 +0900",$xml->fm_pubDate());
					eq("kazutaka",$xml->webMaster());
					eq(2,sizeof($xml->item()));

					$atom = $xml->atom();
					eq(true,$atom instanceof Atom);
					eq("rhaco",$atom->title());
					eq("php",$atom->subtitle());
					eq(1191978610,$atom->updated());
					eq("kazutaka",$atom->generator());
					eq(2,sizeof($atom->entry()));
			*/
	}
}
