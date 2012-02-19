<?php
/**
 * ページを管理するモデル
 * @author tokushima
 * @var integer $offset 開始位置
 * @var integer $limit 終了位置
 * @var integer $current 現在位置
 * @var integer $total 合計
 * @var integer $first 最初のページ番号 @{"set":false}
 * @var integer $last 最後のページ番号 @{"set":false}
 * @var string $query_name pageを表すクエリの名前
 * @var mixed{} $vars query文字列とする値
 * @var string $order 最後のソートキー
 */
class Paginator extends Object{
	protected $offset;
	protected $limit;
	protected $current;
	protected $total;
	protected $first = 1;
	protected $last;
	protected $vars = array();
	protected $query_name = 'page';
	protected $order;
	
	protected function __get_query_name__(){
		return (empty($this->query_name)) ? 'page' : $this->query_name;
	}
	/**
	 * 現在のページの最初の位置
	 * @return integer
	 */
	public function page_first(){
		return $this->offset + 1;
	}
	/**
	 * 現在のページの最後の位置
	 * @return integer
	 */
	public function page_last(){
		return (($this->offset + $this->limit) < $this->total) ? ($this->offset + $this->limit) : $this->total;
	}
	protected function __new__($paginate_by=20,$current=1,$total=0){
		$this->limit($paginate_by);
		$this->total($total);
		$this->current($current);
		/***
			$paginator = new Paginator(10);
			eq(10,$paginator->limit());
			eq(1,$paginator->first());
			$paginator->total(100);
			eq(100,$paginator->total());
			eq(10,$paginator->last());
			eq(1,$paginator->which_first(3));
			eq(3,$paginator->which_last(3));

			$paginator->current(3);
			eq(20,$paginator->offset());
			eq(true,$paginator->is_next());
			eq(true,$paginator->is_prev());
			eq(4,$paginator->next());
			eq(2,$paginator->prev());
			eq(1,$paginator->first());
			eq(10,$paginator->last());
			eq(2,$paginator->which_first(3));
			eq(4,$paginator->which_last(3));

			$paginator->current(1);
			eq(0,$paginator->offset());
			eq(true,$paginator->is_next());
			eq(false,$paginator->is_prev());

			$paginator->current(6);
			eq(5,$paginator->which_first(3));
			eq(7,$paginator->which_last(3));

			$paginator->current(10);
			eq(90,$paginator->offset());
			eq(false,$paginator->is_next());
			eq(true,$paginator->is_prev());
			eq(8,$paginator->which_first(3));
			eq(10,$paginator->which_last(3));
		 */
	}
	protected function __cp__($obj){
		if(!empty($obj)){
			if($obj instanceof Object){
				foreach($obj->prop_values() as $name => $value) $this->vars[$name] = $obj->{'fm_'.$name}();
			}else if(is_array($obj)){
				foreach($obj as $name => $value){
					if(ctype_alpha($name[0])) $this->vars[$name] = $value;
				}
			}
		}
	}
	/**
	 * 次のページ番号
	 * @return integer
	 */
	public function next(){
		return $this->current + 1;
		/***
			$paginator = new Paginator(10,1,100);
			eq(2,$paginator->next());
		*/
	}
	/**
	 * 前のページ番号
	 * @return integer
	 */
	public function prev(){
		return $this->current - 1;
		/***
			$paginator = new Paginator(10,2,100);
			eq(1,$paginator->prev());
		*/
	}
	/**
	 * 次のページがあるか
	 * @return boolean
	 */
	public function is_next(){
		return ($this->last > $this->current);
		/***
			$paginator = new Paginator(10,1,100);
			eq(true,$paginator->is_next());
			$paginator = new Paginator(10,9,100);
			eq(true,$paginator->is_next());
			$paginator = new Paginator(10,10,100);
			eq(false,$paginator->is_next());
		*/
	}
	/**
	 * 前のページがあるか
	 * @return boolean
	 */
	public function is_prev(){
		return ($this->current > 1);
		/***
			$paginator = new Paginator(10,1,100);
			eq(false,$paginator->is_prev());
			$paginator = new Paginator(10,9,100);
			eq(true,$paginator->is_prev());
			$paginator = new Paginator(10,10,100);
			eq(true,$paginator->is_prev());
		*/
	}
	/**
	 * 前のページを表すクエリ
	 * @return string
	 */
	public function query_prev(){
		return Http::query(array_merge(
							$this->ar_vars()
							,array($this->query_name()=>$this->prev())
						));
		/***
			$paginator = new Paginator(10,3,100);
			$paginator->query_name("page");
			$paginator->vars("abc","DEF");
			eq("abc=DEF&page=2",$paginator->query_prev());
		*/
	}
	/**
	 * 次のページを表すクエリ
	 * @return string
	 */
	public function query_next(){
		return Http::query(array_merge(
							$this->ar_vars()
							,array($this->query_name()=>$this->next())
						));
		/***
			$paginator = new Paginator(10,3,100);
			$paginator->query_name("page");
			$paginator->vars("abc","DEF");
			eq("abc=DEF&page=4",$paginator->query_next());
		*/
	}
	/**
	 * orderを変更するクエリ
	 * @param string $order
	 * @param string $pre_order
	 * @return string
	 */
	public function query_order($order){
		if($this->is_vars('order')) $this->order = $this->rm_vars('order');
		return Http::query(array_merge(
							$this->ar_vars()
							,array('order'=>$order,'porder'=>$this->order())
						));
		/***
			$paginator = new Paginator(10,3,100);
			$paginator->query_name("page");
			$paginator->vars("abc","DEF");		
			$paginator->order("bbb");
			eq("abc=DEF&order=aaa&porder=bbb",$paginator->query_order("aaa"));
			
			$paginator = new Paginator(10,3,100);
			$paginator->query_name("page");
			$paginator->vars("abc","DEF");
			$paginator->vars("order","bbb");
			eq("abc=DEF&order=aaa&porder=bbb",$paginator->query_order("aaa"));
		*/
	}
	/**
	 * 指定のページを表すクエリ
	 * @param integer $current 現在のページ番号
	 * @return string
	 */
	public function query($current){
		return Http::query(array_merge($this->ar_vars(),array($this->query_name()=>$current)));
		/***
			$paginator = new Paginator(10,1,100);
			eq("page=3",$paginator->query(3));
		 */
	}
	protected function __set_current__($value){
		$value = intval($value);
		$this->current = ($value === 0) ? 1 : $value;
		$this->offset = $this->limit * round(abs($this->current - 1));
	}
	protected function __set_total__($total){
		$this->total = intval($total);
		$this->last = ($this->total == 0 || $this->limit == 0) ? 0 : intval(ceil($this->total / $this->limit));
	}
	protected function ___which___($paginate){
		return null;
	}
	protected function __is_first__($paginate){
		return ($this->which_first($paginate) !== $this->first);
	}
	protected function __is_last__($paginate){
		return ($this->which_last($paginate) !== $this->last());
	}
	protected function __which_first__($paginate=null){
		if($paginate === null) return $this->first;
		$paginate = $paginate - 1;
		$first = ($this->current > ($paginate/2)) ? @ceil($this->current - ($paginate/2)) : 1;
		$last = ($this->last > ($first + $paginate)) ? ($first + $paginate) : $this->last;
		return (($last - $paginate) > 0) ? ($last - $paginate) : $first;
	}
	protected function __which_last__($paginate=null){
		if($paginate === null) return $this->last;
		$paginate = $paginate - 1;
		$first = ($this->current > ($paginate/2)) ? @ceil($this->current - ($paginate/2)) : 1;
		return ($this->last > ($first + $paginate)) ? ($first + $paginate) : $this->last;
	}
	/**
	 * ページとして有効な範囲のページ番号を有する配列を作成する
	 * @param integer $counter ページ数
	 * @return integer[]
	 */
	public function range($counter=10){
		if($this->which_last($counter) > 0) return range((int)$this->which_first($counter),(int)$this->which_last($counter));
		return array(1);
	}
	/**
	 * rangeが存在するか
	 * @return boolean
	 */
	public function has_range(){
		return ($this->last > 1);
		/***
			$paginator = new self(4,1,3);
			eq(1,$paginator->first());
			eq(1,$paginator->last());
			eq(false,$paginator->has_range());
			
			$paginator = new self(4,2,3);
			eq(1,$paginator->first());
			eq(1,$paginator->last());
			eq(false,$paginator->has_range());
			
			$paginator = new self(4,1,10);
			eq(1,$paginator->first());
			eq(3,$paginator->last());
			eq(true,$paginator->has_range());
			
			$paginator = new self(4,2,10);
			eq(1,$paginator->first());
			eq(3,$paginator->last());
			eq(true,$paginator->has_range());			
		 */
	}
}