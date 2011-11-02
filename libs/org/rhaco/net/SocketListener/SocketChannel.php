<?php
module("exception.SocketErrorException");
module("exception.SocketShutdownException");

class SocketChannel extends Object{
	private $socket;

	protected function __new__($socket){
		$this->socket = $socket;
	}
	public function read(){
		while(true){
			$buffer = socket_read($this->socket,4096,PHP_BINARY_READ);
			if($buffer === false){
				Log::warn("Interrupted");
				return;
			}
			if($buffer === "\n") continue;
			return $buffer;
		}
	}
	public function write($message){
		try{
			socket_write($this->socket,$message,strlen($message));
		}catch(Exception $e){
			Log::warn("Interrupted");			
		}
	}
	public function shutdown(){
		throw new SocketShutdownException();
	}
}