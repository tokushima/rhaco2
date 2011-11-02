<?php
module("SocketChannel");
module("exception.SocketShutdownException");
module("exception.SocketErrorException");
/**
 * ソケット上で接続待ちをする
 * @author tokushima
 */
class SocketListener extends Object{
	private $gsocket;
	private $socket;
	/**
	 * 接続待ちを開始する
	 */
	public function start($address='localhost',$port=8888,$backlog=0){
		$req = new Request();
		if($req->is_vars("address")) $address = $req->in_vars("address",$address);
		if($req->is_vars("port")) $port = $req->in_vars("port",$port);
		
		@set_time_limit(0);
		try{
			$this->gsocket = socket_create(AF_INET,SOCK_STREAM,SOL_TCP);
			if($this->gsocket === false){
				throw new SocketErrorException(socket_strerror(socket_last_error()));
			}
			if(false === socket_bind($this->gsocket,$address,$port)){
				throw new SocketErrorException(socket_strerror(socket_last_error()));
			}
			if(false === socket_listen($this->gsocket,$backlog)){
				throw new SocketErrorException(socket_strerror(socket_last_error()));			
			}
			$this->call_module("listen",$address,$port);
			
			while(true){
				$this->socket = socket_accept($this->gsocket);
				if($this->socket === false) throw new SocketErrorException(socket_strerror(socket_last_error()));
				$channel = new SocketChannel($this->socket);

				try{
					$this->call_module("instruction",$channel);
					$this->call_module("connect",$channel);
					$this->socket_close($this->socket);
				}catch(SocketShutdownException $e){
					unset($channel);
					$this->socket_close($this->socket);					
					break;
				}
				unset($channel);
			}
			$this->socket_close($this->gsocket);
		}catch(Exception $e){
			$this->socket_close($this->gsocket);
			throw new SocketErrorException($e->getMessage());
		}
	}
	private function socket_close(&$sock){
		if(is_resource($sock)){
			socket_close($sock);
			$sock = null;
		}
	}
	protected function __del__(){
		$this->socket_close($this->gsocket);
		$this->socket_close($this->socket);
	}
}
