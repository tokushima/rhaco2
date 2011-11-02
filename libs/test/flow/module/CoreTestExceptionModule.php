<?php
class CoreTestExceptionModule{
	public function init_flow_handle(Flow $flow){
		throw new LogicException('flow handle begin exception');
	}
}