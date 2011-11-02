<?php
class SampleExceptionFlow extends Flow{
	public function throw_method(){
		throw new LogicException('error');
	}
}