<?php
import("org.rhaco.net.xml.Atom");

/**
 * Feedを操作するフィルタ
 * @author tokushima
 */
class FeedConverter{
	/**
	 * タグを除去
	 * @param Atom $atom
	 * @return Atom
	 */
	static public function strip_tags(Atom $atom){
		foreach($atom->ar_entry() as $entry){
			if($entry->is_content()) $entry->content()->value(strip_tags(Tag::cdata($entry->content()->value())));
			if($entry->is_summary()) $entry->summary()->value(strip_tags(Tag::cdata($entry->summary()->value())));
		}
		return $atom;
	}
}
