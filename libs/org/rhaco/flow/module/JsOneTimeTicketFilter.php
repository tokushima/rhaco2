<?php
/**
 * TemplateモジュールでJavascriptによるワンタイムチケット
 * 
 * @author Kazutaka Tokushima
 * @license New BSD License
 */
class JsOneTimeTicketFilter{
	/**
	 * Flowのモジュール
	 * @param Flow $flow
	 */
	public function before_flow(Flow $flow){
		if(!$flow->is_post()){
			$flow->sessions("_onetimeticket",uniqid("").mt_rand());
			$flow->vars("_onetimeticket",$flow->in_sessions("_onetimeticket"));
		}
	}
	/**
	 * Flowのモジュール
	 * @param Flow $flow
	 */
	public function flow_verify(Flow $flow){
		if($flow->is_sessions("_onetimeticket") && $flow->in_vars("_onetimeticket") == $flow->in_sessions("_onetimeticket")){
			$flow->rm_sessions("_onetimeticket");
			return true;
		}
		return false;
	}
	/**
	 * Templateのモジュール
	 * @param string $src
	 * @param Template $template
	 */
	public function after_template(&$src,Template $template){
		if(Tag::setof($tag,$src,"body")){
			foreach($tag->in("form") as $f){
				if(strtolower($f->in_param("method")) == "post"){
					$func = uniqid("f").mt_rand();
					$f->param("onsubmit",$func."(this)");
					$f->value("<input type=\"hidden\" name=\"_onetimeticket\" />".$f->value());
					$src = str_replace($f->plain(),sprintf('
													<script type="text/javascript"><!--
														function %s(frm){
															frm._onetimeticket.value = "{$_onetimeticket}";
														}
													-->
													</script>',$func).$f->get(),$src);
				}
			}
		}
	}
}
