<?php
import("org.rhaco.storage.memcache.MultihopMemcachedClient");
/**
 * Memcacheを使ったStoreモジュール
 * @author tokushima
 * @const string[] $servers memcacheサーバアドレス
 *
 */
class StoreMemcache extends Object{
	protected $extension = false;
	protected $memcache;

	protected function __init__(){
		$servers = module_const("servers");
		$servers = (is_array($servers)) ? $servers : array($servers);

		if(extension_loaded("memcache")){
			$this->extension = true;
			$this->memcache = new Memcache();

			foreach($servers as $server){
				$port = 11211;
				if(strpos($server,":") !== false) list($server,$port) = explode(":",$server,2);
				$this->memcache->addServer($server,$port);
			}
		}else{
			foreach($servers as $server) MultihopMemcachedClient::set_server($server);
		}
	}
	/**
	 * Storeのモジュール
	 */
	public function store_has($id,$ignore_time){
		if($this->extension) return ($this->memcache->get($id) !== false);
		try{
			MultihopMemcachedClient::read($id);
			return true;
		}catch(RuntimeException $e){}
		return false;
	}
	/**
	 * Storeのモジュール
	 */
	public function store_get($id){
		if($this->extension){
			$result = $this->memcache->get($id);
			return ($result === false) ? null : $result;
		}
		try{
			return MultihopMemcachedClient::read($id);
		}catch(RuntimeException $e){}
		return null;
	}
	/**
	 * Storeのモジュール
	 */
	public function store_set($id,$source,$expiry_time){
		if($this->extension){
			$this->memcache->set($id,$source,0,$expiry_time);
		}else{
			MultihopMemcachedClient::write($id,$source,$expiry_time);
		}
		return $source;
	}
	/**
	 * Storeのモジュール
	 */
	public function store_delete($id){
		if($this->extension){
			$this->memcache->delete($id);
		}else{
			MultihopMemcachedClient::delete($id);
		}
	}
	protected function __del__(){
		if($this->extension) $this->memcache->close();
	}
}
