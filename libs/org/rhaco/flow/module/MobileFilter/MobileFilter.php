<?php
import("org.rhaco.lang.text.Pictogram");
module("MobileFilterTemplf");
/**
 * モバイル用のフィルタ
 * 
 * 拡張タグ：　mf:no_save_movie
 * <mf:no_save_movie href="動画のURL" title="動画のタイトル" size="動画の容量" />
 * au,docomoで再配布不可として動画のリンクを生成する、softbankはヘッダで行う
 * 
 * @author tokushima
 *
 */
class MobileFilter{
	private $agent;
	private $cookie;

	static public function __import__(){
		ini_set('url_rewriter.tags','a=href,area=href,frame=src,input=src,form=fakeentry,fieldset=,img=src,object=data');
	}
	private function cookie_name(){
		return "mfcc".substr(md5(__CLASS__),2,10);
	}
	/**
	 * @module
	 */
	public function begin_flow_handle(Flow $flow){
		setcookie($this->cookie_name(),time(),0,"/");
		$this->agent = self::agent();
		$this->cookie = (!isset($this->agent) || isset($_COOKIE[$this->cookie_name()]) || (isset($_GET[session_name()]) && $_GET[session_name()] === session_id()));
	}
	/**
	 * @module
	 * @param Flow $flow
	 */
	public function before_flow_handle(Flow $flow){
		if($this->is_ketai()){
			parse_str(Request::request_string(),$request);
			foreach($request as $k => $v) $flow->vars($k,$this->encode($v));
		}
	}
	private function encode($value){
		if(!empty($value)){
			if(is_array($value)){
				foreach($value as $k => $v) $value[$this->encode($k)] = $this->encode($v);
			}else if(is_string($value)){
				$value = Text::encode(Pictogram::bin2code($value,$this->agent,"sjis"),"UTF-8","SJIS-win");
			}
		}
		return $value;
	}
	static private function agent(){
		$agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null;
		if(strpos($agent,'DoCoMo') !== false){
			return "docomo";
		}else if(strpos($agent,'SoftBank') !== false || strpos($agent,'Vodafone') !== false){
			return "softbank";
		}else if(strpos($agent,'iPhone') !== false){
			return "iphone";
		}else if(strpos($agent,'KDDI') !== false){
			return "au";
		}else if(strpos($agent,'WILLCOM') !== false){
			return "docomo";
		}
		return null;
	}
	private function is_ketai(){
		return ($this->agent === 'docomo' || $this->agent === 'softbank' || $this->agent === 'au');
	}
	/**
	 * @module
	 * @param unknown_type $src
	 * @param Flow $flow
	 */
	public function before_flow_print_template(&$src,Flow $flow){
		if($this->is_ketai()){
			$code = "Shift_JIS";
	
			if(preg_match("/<\?xml.+encoding=[\"']([\w\-]+)[\"'].*?>/",$src,$match)){
				$src = str_replace($match[0],str_replace($match[1],$code,$match[0]),$src);
			}
			if(Tag::setof($tag,$src,"html")){
				$replace_src = $tag->plain();
	
				foreach($tag->in("meta") as $meta){
					if(strtolower($meta->in_param("http-equiv")) === "content-type"){
						$content = $meta->in_param("content");
						if(preg_match("/^.+charset=([\w\-]+).*$/i",$content,$match)){
							$meta->param("content",str_replace($match[1],$code,$content));
						}
						$replace_src = str_replace($meta->plain(),$meta->get(),$replace_src);
						break;
					}
				}
				foreach($tag->in("form") as $form){
					if(!$form->is_param("action")){
						$form->param("action","");
						$replace_src = str_replace($form->plain(),$form->get(),$replace_src);
					}
				}
				foreach($tag->in(array("textarea","pre")) as $pre){
					$pre->close_empty(false);
					$pre->value(str_replace(array("\r\n","\r","\n"," ","\t"),array("@{E}@","","@{E}@","@{S}@","@{T}@"),$pre->value()));
					$replace_src = str_replace($pre->plain(),$pre->get(),$replace_src);
				}
				$src = str_replace($tag->plain(),$replace_src,$src);
				
				$src = Text::encode($src,"SJIS-win","UTF-8");
				$src = Pictogram::code2bin($src,$this->agent,"sjis");
				
				header('Content-Type: application/xhtml+xml; charset=Shift-JIS');
				header('Content-Length: '.strlen($src));
			}
		}else{
			$src = Pictogram::html_code2bin($src,$flow->is_secure_map());			
		}
		if(!$this->cookie){
			ob_start();
				$rpath = 'ROOT_URL_'.uniqid();
				$rspath = 'ROOT_SURL_'.uniqid();

				ob_start();
					output_add_rewrite_var(session_name(),session_id());
					print(str_replace(array("\t","    ","\r\n","\r","\n",App::url(),App::surl(),"@{E}@","@{S}@","@{T}@"),array("","","","","",$rpath,$rspath,"\n"," ","\t"),trim($src)));
				ob_end_flush();
			$src = str_replace(array($rpath,$rspath),array(App::url(),App::surl()),ob_get_clean());
		}
	}
	/**
	 * @module
	 * @param string $url
	 */
	public function flow_redirect_url(&$url){
		if(!$this->cookie && (strpos($url,App::url()) === 0 || strpos($url,App::surl()) === 0)){
			$url = preg_replace("/[&\?]".session_name()."=[\w]+/","",$url);
			$url .= ((strpos($url,'?') === false) ? '?' : ((substr($url,-1) == '&') ? '' : '&')).session_name().'='.session_id();
		}
	}
	/**
	 * @module
	 * @param string $src
	 * @param Template $template
	 */
	public function before_exec_template(&$src,Template $template){
		$template->vars("mf",new MobileFilterTemplf($this->cookie));
	}
	/**
	 * 
	 * @module
	 * @see http://www.nttdocomo.co.jp/service/imode/make/content/imotion/mp4/distribution/index.html
	 * @see http://www.au.kddi.com/ezfactory/tec/spec/wap_tag5.html
	 * @see http://hrlk.com/tec/mime-dis/
	 * @param string $src
	 * @param Template $template
	 */
	public function after_exec_template(&$src,Template $template){
		if(strpos($src,"mf:no_save_movie") !== false){
			while(Tag::setof($tag,$src,"mf:no_save_movie")){
				$func = null;

				$href = $tag->in_param("href");
				$size = $tag->in_param("size");
				$title = $tag->in_param("title");
				$style = ($tag->is_param("style") ? null : sprintf(' style="%s"',$tag->in_param("style")));

				switch($this->agent){
					case "docomo":
						$id = uniqid("mfm");
						$func = sprintf('<object declare id="%s" data="%s" type="video/3gpp">'
										.'<param name="count" value="0" valuetype="data">'
										.'</object>'
										.'<a href="#%s"%s>%s</a>'
										,$id,$href,$id,$style,$title);
						break;
					case "au":
						$func = sprintf('<object data="%s" type="application/x-mpeg" copyright="yes" standby="%s"%s>'
										.'<param name="disposition" value="devmpzz" valuetype="data" />'
										.'%s'
										.'<param name="title" value="%s" valuetype="data" />'
										.'</object>'
										,$href,$title,$style,(empty($size) ? '' : sprintf('<param name="size" value="%d" valuetype="data" />',$size)),$title);
						break;
					case "softbank":
						// header('x-jphone-copyright: no-transfer'); で行う
						$func = sprintf('<a href="%s"%s>%s</a>',$href,$style,$title);
						break;
					case "iphone":
						$func = sprintf('<a href="%s"%s>%s</a>',$href,$style,$title);
						break;						
				}
				$src = str_replace($tag->plain(),$func,$src);
			}
			$src = $template->parse_vars($src);
		}
	}
}
