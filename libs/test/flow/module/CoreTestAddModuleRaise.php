<?php
class CoreTestAddModuleRaise extends Object{
	protected function __new__(){
		throw new RuntimeException();
	}
}