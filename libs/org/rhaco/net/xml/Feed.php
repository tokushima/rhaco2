<?php
import("org.rhaco.net.xml.Atom");
import("org.rhaco.net.xml.Rss");
import("org.rhaco.net.xml.Opml");
/**
 * Rss2.0 Atom1.0 Opmlのフィードをまとめて扱う
 * @const cache boolean キャッシュをするか
 * @const time キャッシュの有効期間
 * 
 * @author Kazutaka Tokushima
 * @license New BSD License
 * @var timestamp $updated
 */
class Feed extends Http{
	static private $CACHE;
	static private $CACHE_TIME;
	protected $title;
	protected $subtitle;
	protected $id;
	protected $generator;
	protected $updated;

	static public function __import__(){
		self::$CACHE = module_const("cache",false);
		self::$CACHE_TIME = module_const("time",86400);
	}
	static public function read($url){
		$feed = new self();
		return $feed->do_read($url);
	}
	/**
	 * URLからフィードを取得
	 *
	 * @param string $url
	 * @return Atom
	 */
	public function do_read($url){
		$urls = func_get_args();
		$feed = null;

		if(!self::$CACHE || !Store::has($urls)){
			foreach($urls as $url){
				if(is_string($url) && ($url = trim($url)) && !empty($url)){
					if(!self::$CACHE || !Store::has($url)){
						$src = $this->do_get($url)->body();

						if(Tag::setof($head,$src,"head")){
							$src = Tag::xhtmlnize($head->plain(),"link");

							if(Tag::setof($tag,$src,"head")){
								foreach($tag->in("link") as $link){
									if("alternate" == strtolower($link->in_param("rel"))
										&& strpos(strtolower($link->in_param("type")),"application") === 0
										&& $url != ($link = File::absolute($url,$link->in_param("href")))
									){
										$src = $this->do_get($link)->body();
										break;
									}
								}
							}
						}
						$tmp = self::parse($src);
						if(self::$CACHE) Store::set($url,$tmp,self::$CACHE_TIME);
					}else{
						$tmp = Store::get($url);
					}
					if($feed === null){
						if($this->title !== null) $tmp->title($this->title());
						if($this->subtitle !== null) $tmp->subtitle($this->subtitle());
						if($this->id !== null) $tmp->id($this->id());
						if($this->generator !== null) $tmp->generator($this->generator());
						if($this->updated !== null) $tmp->updated($this->updated());

						$feed = $tmp;
					}else{
						$feed->add($tmp);
					}
				}
			}
			if(!($feed instanceof Atom)) $feed = new Atom();
			$feed->sort();
			if(self::$CACHE) Store::set($urls,$feed);
		}else{
			$feed = Store::get($urls);
		}
		return $feed;
	}
	/**
	 * URLからフィードを取得して出力
	 *
	 * @param $url
	 * @param $name
	 */
	public function output($url,$name=null){
		$this->do_read($url)->output($name);
	}
	/**
	 * フィードを取得
	 *
	 * @param string $src
	 * @return Atom
	 */
	static public function parse($src){
		try{
			return Atom::parse($src);
		}catch(Exception $e){
			try{
				return Rss::parse($src)->atom();
			}catch(Exception $e){
				try{
					return Opml::parse($src)->atom();
				}catch(Exception $e){
					throw new Exception("no feed");
				}
			}
		}
		/***
			$src = text('
				<feed xmlns="http://www.w3.org/2005/Atom">
				<title>atom10 feed</title>
				<subtitle>atom10 sub title</subtitle>
				<updated>2007-07-18T16:16:31+00:00</updated>
				<generator>tokushima</generator>
				<link href="http://tokushimakazutaka.com" rel="abc" type="xyz" />

				<author>
					<url>http://tokushimakazutaka.com</url>
					<name>tokushima</name>
					<email>tokushima@hoge.hoge</email>
				</author>

				<entry>
					<title>rhaco</title>
					<summary type="xml" xml:lang="ja">summary test</summary>
					<content type="text/xml" mode="abc" xml:lang="ja" xml:base="base">atom content</content>
					<link href="http://rhaco.org" rel="abc" type="xyz" />
					<link href="http://conveyor.rhaco.org" rel="abc" type="conveyor" />
					<link href="http://lib.rhaco.org" rel="abc" type="lib" />

					<updated>2007-07-18T16:16:31+00:00</updated>
				 	<issued>2007-07-18T16:16:31+00:00</issued>
				 	<published>2007-07-18T16:16:31+00:00</published>
				 	<id>rhaco</id>
				<author>
					<url>http://rhaco.org</url>
					<name>rhaco</name>
					<email>rhaco@rhaco.org</email>
				</author>
				</entry>

				<entry>
					<title>django</title>
					<summary type="xml" xml:lang="ja">summary test</summary>
					<content type="text/xml" mode="abc" xml:lang="ja" xml:base="base">atom content</content>
					<link href="http://djangoproject.jp" rel="abc" type="xyz" />

				 <updated>2007-07-18T16:16:31+00:00</updated>
				 <issued>2007-07-18T16:16:31+00:00</issued>
				 <published>2007-07-18T16:16:31+00:00</published>
				 <id>django</id>
				<author>
					<url>http://www.everes.net</url>
					<name>everes</name>
					<email>everes@hoge.hoge</email>
				</author>
				</entry>

				</feed>
			');

			$xml = Feed::parse($src);
			$result = text('
							<feed xmlns="http://www.w3.org/2005/Atom">
							<title>atom10 feed</title>
							<subtitle>atom10 sub title</subtitle>
							<id>rhaco</id>
							<generator>tokushima</generator>
							<updated>2007-07-18T16:16:31Z</updated>
							<link rel="abc" type="xyz" href="http://tokushimakazutaka.com" />
							<entry>
								<id>rhaco</id>
								<title>rhaco</title>
								<published>2007-07-18T16:16:31Z</published>
								<updated>2007-07-18T16:16:31Z</updated>
								<issued>2007-07-18T16:16:31Z</issued>
								<content type="text/xml" mode="abc" xml:lang="ja" xml:base="base">atom content</content>
								<summary type="xml" xml:lang="ja">summary test</summary>
								<link rel="abc" type="xyz" href="http://rhaco.org" />
								<link rel="abc" type="conveyor" href="http://conveyor.rhaco.org" />
								<link rel="abc" type="lib" href="http://lib.rhaco.org" />
								<author>
									<name>rhaco</name>
									<url>http://rhaco.org</url>
									<email>rhaco@rhaco.org</email>
								</author>
							</entry>
							<entry>
								<id>django</id>
								<title>django</title>
								<published>2007-07-18T16:16:31Z</published>
								<updated>2007-07-18T16:16:31Z</updated>
								<issued>2007-07-18T16:16:31Z</issued>
								<content type="text/xml" mode="abc" xml:lang="ja" xml:base="base">atom content</content>
								<summary type="xml" xml:lang="ja">summary test</summary>
								<link rel="abc" type="xyz" href="http://djangoproject.jp" />
								<author>
									<name>everes</name>
									<url>http://www.everes.net</url>
									<email>everes@hoge.hoge</email>
								</author>
							</entry>
							<author>
								<name>tokushima</name>
								<url>http://tokushimakazutaka.com</url>
								<email>tokushima@hoge.hoge</email>
							</author>
							</feed>
						');
			$result = str_replace(array("\n","\t"),"",$result);
			eq($result,$xml->str());
		*/
	}
}
