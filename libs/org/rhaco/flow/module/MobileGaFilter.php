<?php
/**
 * Mobile向けGoogleAnalyticsのフィルタ
 * @const string $account GoogleAnalyticsのアカウント
 * @const string $cookie_name visitor_idを記録するクッキーの名前
 */
class MobileGaFilter extends Object{
	public function before_flow_print_template(&$src,Flow $flow){
		$account = module_const("account");
		$cookie_name = module_const("cookie_name","__utmmobile");
		
		if(!empty($account) && preg_match("/<\/body>/i",$src,$match_end_body)){
			$agent = isset($_SERVER["HTTP_USER_AGENT"]) ? $_SERVER["HTTP_USER_AGENT"] : "";
			$guid = isset($_SERVER["HTTP_X_DCMGUID"]) ? $_SERVER["HTTP_X_DCMGUID"] : "";
			if(empty($guid)) $guid = isset($_SERVER["HTTP_X_UP_SUBNO"]) ? $_SERVER["HTTP_X_UP_SUBNO"] : "";
			if(empty($guid)) $guid = isset($_SERVER["HTTP_X_JPHONE_UID"]) ? $_SERVER["HTTP_X_JPHONE_UID"] : "";
			if(empty($guid)) $guid = isset($_SERVER["HTTP_X_EM_UID"]) ? $_SERVER["HTTP_X_EM_UID"] : "";	
			$visitor_id = "0x".substr(md5((!empty($guid)) ? ($guid.$account) : ($agent.uniqid(rand(0,0x7fffffff),true))),0,16);

			if(!$flow->is_vars($cookie_name)){
				$flow->vars($cookie_name,$visitor_id);
				$flow->write_cookie($cookie_name);
			}
			$name = isset($_SERVER["SERVER_NAME"]) ? $_SERVER["SERVER_NAME"] : "";
			$referer = (!isset($_SERVER["HTTP_REFERER"]) || (empty($_SERVER["HTTP_REFERER"]) && $_SERVER["HTTP_REFERER"] !== "0")) ? "-" : $_SERVER["HTTP_REFERER"];
			$path = (!isset($_SERVER["REQUEST_URI"]) || empty($_SERVER["REQUEST_URI"])) ? "" : $_SERVER["REQUEST_URI"];
			$addr = isset($_SERVER["REMOTE_ADDR"]) ? ((preg_match("/^([^.]+\.[^.]+\.[^.]+\.).*/",$_SERVER["REMOTE_ADDR"],$m)) ? $m[1]."0" : "") : "";
			
			$url = ($flow->is_secure_map() ? "https" : "http")."://www.google-analytics.com/__utm.gif?"
						."utmwv=4.4sh"
						."&utmn=".rand(0,0x7fffffff)
						."&utmhn=".urlencode($name)
						."&utmr=".urlencode($referer)
						."&utmp=".urlencode($path)
						."&utmac=".$account
						."&utmcc=__utma%3D999.999.999.999.999.1%3B"
						."&utmvid=".$visitor_id
						."&utmip=".$addr;
			$add_image_tag = sprintf('<img src="%s" />',$url);
			$src = str_replace($match_end_body[0],$add_image_tag.$match_end_body[0],$src);
		}
	}
}
