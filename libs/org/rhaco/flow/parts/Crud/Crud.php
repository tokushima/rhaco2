<?php
import("org.rhaco.storage.db.Dao");
module("CrudTools");
/**
 * Crud
 * @author Kazutaka Tokushima
 * @license New BSD License
 */
class Crud extends Flow{
	protected function __init__(){
		$this->vars("cf",new CrudTools());
	}
	private function get_model($name){
		$this->search_model();
		if(class_exists($name)) return new $name($this->to_dict("primary"));
		return Dao::instant($name,null,$this->to_dict("primary"));
	}
	/**
	 * 検索
	 * 
	 * @param string $name モデル名
	 * 
	 * @request string $order ソート順
	 * @request int $page ページ番号
	 * @request string $query 検索文字列
	 * @request string $porder 直前のソート順
	 * 
	 * @context array $object_list 結果配列
	 * @context Paginator $paginator ページ情報
	 * @context string $porder 直前のソート順
	 * @context Dao $model 検索対象のモデルオブジェクト
	 * @context string $model_name 検索対象のモデルの名前
	 */
	public function do_find($name){
		if($this->login()){
			$class = $this->get_model($name);
			$order = $this->in_vars("order");
			$paginator = new Paginator(10,$this->in_vars("page"));
			$this->vars("object_list",C($class)->find_page($this->in_vars("query"),$paginator,$order,$this->in_vars("porder")));
			$this->vars("paginator",$paginator->cp(array("query"=>$this->in_vars("query"),"porder"=>$order)));
			$this->vars("porder",$order);
			$this->vars("model",$class);
			$this->vars("model_name",$name);
		}
	}
	/**
	 * 詳細
	 * @param string $name モデル名
	 */
	public function do_detail($name){
		if($this->login()){
			$class = $this->get_model($name);
			$this->vars("object",$class->sync());
			$this->vars("model",$class);
			$this->vars("model_name",$name);
		}
	}
	/**
	 * 削除
	 * @param string $name モデル名
	 */
	public function do_drop($name){
		if($this->login()){
			if($this->is_post()){
				$class = $this->get_model($name);
				$class->sync()->cp($this->vars())->delete(true);
				$this->redirect_referer();
			}
		}
		return $this->do_find($name);
	}
	/**
	 * 更新
	 * @param string $name モデル名
	 */
	public function do_update($name){
		if($this->login()){
			$class = $this->get_model($name);
			$obj = $class->sync();

			if($this->is_post()){
				try{
					$obj->cp($this->vars())->save();
					if($this->is_vars("save_and_add_another")){
						$this->redirect_method("do_create",$name);
					}else{
						$this->redirect_method("do_find",$name);
					}
				}catch(Exception $e){
					Exceptions::add($e);
				}
			}else{
				$this->cp($obj);
			}
			$this->vars("model",$class);
			$this->vars("model_name",$name);
		}
	}
	/**
	 * 作成
	 * @param string $name モデル名
	 */
	public function do_create($name){
		if($this->login()){
			$class = $this->get_model($name);
			if($this->is_post()){
				try{
					$class->cp($this->vars())->save();
					if($this->is_vars("save_and_add_another")){
						$this->redirect_method("do_create",$name);
					}else{
						$this->redirect_method("do_find",$name);
					}
				}catch(Exception $e){
					Exceptions::add($e);
				}
			}else{
				$this->cp($class);
			}
			$this->vars("model",$class);
		}
	}
	/**
	 * アプリケーション内のモデルの一覧
	 */
	public function index(){
		$models = array();
		if($this->login()) $models = $this->search_model();
		$this->vars("models",$models);
	}
	private function search_model(){
		$models = array();

		foreach(get_classes(true) as $path => $class){
			if(!class_exists($class) && !interface_exists($class)) import($path);
		}
		foreach(get_declared_classes() as $class){
			if(is_class($class) && is_subclass_of($class,"Dao")) $models[] = $class;
		}
		return $models;
	}
	private function to_dict($name){
		$result = null;
		if(is_array($this->in_vars($name))){
			foreach($this->in_vars($name) as $key => $value){
				$result .= $key."=".$value.",";
			}
		}
		return $result;
	}
}
