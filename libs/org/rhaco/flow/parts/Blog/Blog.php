<?php
import("org.rhaco.lang.DateUtil");
import("org.rhaco.net.xml.Atom");
module("model.BlogEntry");
module("model.BlogTag");
/**
 * Blog
 * @author tokushima
 */
class Blog extends Flow{
	protected function __init__(){
		$names = C(BlogTag)->find_distinct("name");
		sort($names);
		$this->vars("tag_name_list",$names);
		$this->vars("query",$this->in_vars("query"));
	}
	/**
	 * 記事一覧
	 * @arg integer $paginate_by
	 * @request integer $page
	 * @rewuest string $query
	 * @context BlogEntry[] $object_list
	 * @context Paginator $paginator
	 */
	public function index(){
		$paginator = new Paginator($this->map_arg("paginate_by",1),$this->in_vars("page",1));
		$object_list = array();
		foreach(C(BlogEntry)->find(Q::match($this->in_vars("query"),Q::IGNORE),$paginator) as $obj){
			$object_list[] = $obj->add_module($this);
		}
		$this->vars("object_list",$object_list);
		$this->vars("paginator",$paginator->cp(array("query"=>$this->in_vars("query"))));
	}
	/**
	 * タグによる絞り込んだ記事一覧
	 * @param string $tag
	 * @arg integer $paginate_by
	 * @arg string $tag
	 * @request integer $page
	 * @rewuest string $query
	 * @context BlogEntry[] $object_list
	 * @context Paginator $paginator
	 */
	public function tag($tag=null){
		$paginator = new Paginator($this->map_arg("paginate_by",1),$this->in_vars("page",1));
		$tag = $this->map_arg("tag",$tag);
		$object_list = array();
		foreach(C(BlogEntry)->find(
					Q::match($this->in_vars("query"),Q::IGNORE)
					,Q::contains("tag"," ".$tag." ")
					,$paginator
					,Q::order("-id")
				) as $obj){
			$object_list[] = $obj->add_module($this);
		}
		$this->vars("object_list",$object_list);
		$this->vars("paginator",$paginator->cp(array("query",$this->in_vars("query"))));
	}
	/**
	 * Atom1.0での出力
	 * @const string[] $atom string title,string base url
	 */
	public function atom(){
		$object_list = array();
		foreach(C(BlogEntry)->find(
					new Paginator(20)
					,Q::order("-id")
					,Q::gt("create_date",DateUtil::add_day(-7))
				) as $obj){
			$object_list[] = $obj->set_formatter($this);
		}
		list($title,$url) = module_const_array("atom",2);
		Atom::convert($title,$url,$object_list)->output();
	}
	/**
	 * 指定の記事
	 * @param string $name
	 * @context BlogEntry $object
	 */
	public function detail($name){
		$this->vars("object_list",array(C(BlogEntry)->find_get(Q::eq("name",$name))->add_module($this)));
	}
	/**
	 * 整形された $srcを返す
	 * @param string $src
	 * @return string
	 */
	public function format($src){
		if($this->has_module("format")) return $this->call_module("format",$src);
		return $src;
	}
}
