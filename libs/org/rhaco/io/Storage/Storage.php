<?php
module("StorageException");
/**
 * ストレージへの操作
 * @author tokushima
 *
 */
class Storage extends Object{
	/**
	 * ランダムなノードのパスを返す
	 * ディレクトリが存在しなかった場合はディレクトリを作成する
	 * @param string $type
	 * @const string[] $nodes ノードのパス配列
	 * @return string
	 */
	public function get_save_path($type='mixed'){
		$nodes = module_const_array('save_nodes');
		if(empty($nodes)) throw new RuntimeException('ノードが設定されていません');
		$service = module_const('service_name','new_service');
		$dir = File::path_slash($nodes[rand(1,sizeof($nodes))-1],false,true).$service.'/'.$type.'/'.date('Y/md/H');
		File::mkdir($this->get_path($dir));
		return $dir;
	}
	/**
	 * 書き込みテストを行う
	 * @throws RuntimeException
	 */
	public function test(){
		$nodes = module_const_array('save_nodes');
		if(empty($nodes)) throw new RuntimeException('ノードが設定されていません');
		$service = module_const('service_name','new_service');
		foreach($nodes as $node){
			try{
				$file = $this->get_path($node.'/node_con_test');
				File::write($file,__CLASS__);
				File::read($file);
				File::rm($file);
			}catch(Exception $e){
				Exceptions::add($e,$node);
			}
		}
		Exceptions::throw_over();
	}
	/**
	 * ランダムノードのフルパスを返す
	 * @param string $type
	 */
	public function get_save_fullpath($type='mixed'){
		return $this->get_path($this->get_save_path($type));
	}
	/**
	 * 指定したノードのパスを返す
	 * @param string $node
	 * @param string $type
	 * @throws RuntimeException
	 */
	public function get_select_path($node,$type='mixed'){
		$nodes = module_const_array('save_nodes');
		if(empty($nodes)) throw new RuntimeException('ノードが設定されていません');
		if(!in_array($node,$nodes)) throw new RuntimeException('指定のノードが設定されていません `'.$node.'`');		
		$service = module_const("service_name","new_service");
		$dir = $node.'/'.$service.'/'.$type;
		File::mkdir($this->get_path($dir));
		return $dir;
	}
	/**
	 * 指定したノードのフルパスを返す
	 * @param string $node
	 * @param string $type
	 */
	public function get_select_fullpath($node,$type='mixed'){
		return $this->get_path($this->get_select_path($node,$type));
	}
	/**
	 * ファイルの実際のパスを返す
	 * @const string $base_path ストレージのベースパス
	 * @param string $path ベースパスからの相対パス
	 * @return string
	 */
	public function get_path($path){
		$base_path = module_const('base_path');
		if(empty($base_path)) throw new StorageException('ベースパスが指定されていません');
		return File::absolute(File::path_slash($base_path,null,true),$path);
	}
	
}