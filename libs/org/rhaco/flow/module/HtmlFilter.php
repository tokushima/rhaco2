<?php
/**
 * htmlのフィルタ
 *  - 自動エスケープ処理
 * @author tokushima
 */
class HtmlFilter{
	/**
	 * @module
	 * @param string $src
	 * @param Template $template
	 */
	public function before_exec_template(&$src,Template $template){
		if(preg_match_all("/<\?php @print\((.+?)\); \?>/",$src,$match)){
			$src = str_replace($match[0],array_map(array($this,"add_escape"),$match[1]),$src);
		}
	}
	private function add_escape($value){
		if(!(
				strpos($value,'$_t_->htmlencode(') === 0
				||strpos($value,'$_t_->html(') === 0
				|| strpos($value, '$_t_->text(') === 0
				|| strpos($value, '$_t_->noop(') === 0
				|| strpos($value,'$t->htmlencode(') === 0				
				|| strpos($value, '$t->html(') === 0
				|| strpos($value, '$t->text(') === 0
				|| strpos($value, '$t->noop(') === 0
		)){
			$value = '$_t_->htmlencode('.$value.')';
		}
		return '<?php @print('.$value.'); ?>';
	}
}