<?php
/**
 * Templateの変数{$abc}を展開しない
 * @author tokushima
 */
class EscapeTemplateVar{
	public function before_template(&$src){
		while(Tag::setof($tag,$src,'rt:etv')){
			$src = str_replace($tag->plain(),str_replace(array('$','='),array('__RTD__','__RTE__'),$tag->value()),$src);
		}
	}
	public function after_exec_template(&$src){
		$src = str_replace(array('__RTD__','__RTE__'),array('$','='),$src);
	}
}