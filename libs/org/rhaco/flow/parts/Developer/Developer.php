<?php
import('org.rhaco.lang.Sorter');
import('org.rhaco.flow.module.TwitterBootstrapPagination');
module('DeveloperFilter');
/**
 * マップ情報、モデル情報、パッケージ情報を表示
 * @author tokushima
 */
class Developer extends Flow{
	protected function __init__(){
		$this->add_module(new TwitterBootstrapPagination());
		if($this->has_module('login_condition') || $this->has_module('silent_login_condition')) $this->login();
		$this->vars('app_name',__CLASS__);
		$info = App::info();
		foreach($info as $k => $v) $this->vars('app_'.$k,$v);
		$models = $this->search_model();
		$this->vars('models',$models);
		$this->vars('f',new DeveloperFilter());
		$this->vars('is_smtp_blackhole',in_array('SmtpBlackholeDao',$models));
	}
	/**
	 * アプリケーションのマップ一覧
	 */
	public function index(){
		$maps = array();
		$parse = Flow::parse_app($this->in_vars('app_filename'));
		$package = module_package();
		foreach($parse['apps'] as $apps){
			if($apps['type'] == 'handle'){
				foreach($apps['maps'] as $url => $m){
					if($m['class'] !== $package && $this->search_str($m['name'],$m['class'],$m['method'],$m['url'],$m['template'])) $maps[] = $m;
				}
			}
		}
		$this->vars('maps',$maps);
	}
	private function search_str(){
		$query = str_replace('　',' ',trim($this->in_vars('q')));
		if(!empty($query)){
			$args = func_get_args();
			foreach(explode(' ',$query) as $q){
				if(stripos(implode(' ',$args),$q) === false) return false;
			}
		}
		return true;
	}
	/**
	 * libraryの一覧
	 */
	public function classes(){
		$libs = array();
		foreach(Lib::classes() as $package => $class_name){
			$ref = new ReflectionClass($class_name);
			$doc = $ref->getDocComment();
			$document = trim(preg_replace("/@.+/","",preg_replace("/^[\s]*\*[\s]{0,1}/m","",str_replace(array("/"."**","*"."/"),'',$doc))));
			list($summary) = explode("\n",$document);
			if($this->search_str($package,$class_name,$document)){
				$src = file_get_contents($ref->getFileName());

				$c = new Object();
				$c->summary = $summary;
				$c->usemail = (strpos($src,'org.rhaco.net.mail.Mail') !== false);
				$libs[$package] = $c;
			}
		}
		ksort($libs);
		$this->vars('packages',$libs);
	}
	/**
	 * クラスのソース表示
	 * @param string $class
	 */
	public function class_src($class){
		$this->class_info($class);
		
		$class = import($class);
		$ref = new ReflectionClass($class);
		$this->vars('class_src',file_get_contents($ref->getFileName()));
	}
	/**
	 * クラスのドキュメント
	 * @param string $path
	 */
	public function class_info($path){
		$class = import($path);
		$ref = new ReflectionClass($class);
		$src = implode(array_slice(file($ref->getFileName()),$ref->getStartLine(),($ref->getEndLine()-$ref->getStartLine()-1)));
		$document = trim(preg_replace("/^[\s]*\*[\s]{0,1}/m","",str_replace(array("/"."**","*"."/"),"",$ref->getDocComment())));
		$extends = null;

		$parent_class = $ref->getParentClass();
		if($parent_class !== false && $parent_class->getName() !== 'stdClass'){
			try{
				$extends = Lib::package_path($parent_class->getName());
			}catch(Exception $e){
				$extends = $parent_class->getName();
			}
		}
		$methods = $static_methods = array();
		foreach($ref->getMethods() as $method){
			if($method->getDeclaringClass()->getFileName() == $ref->getFileName()){
				if(substr($method->getName(),0,1) != '_' && $method->isPublic()){
					list($line) = explode("\n",trim(preg_replace("/@.+/","",preg_replace("/^[\s]*\*[\s]{0,1}/m","",str_replace(array("/"."**","*"."/"),"",$method->getDocComment())))));
					if($method->isStatic()){
						if($method->getDeclaringClass()->getName() == $ref->getName()){
							$static_methods[$method->getName()] = $line;
						}
					}else{
						$methods[$method->getName()] = $line;
					}
				}
			}
		}
		$tasks = array();
		if(preg_match_all("/TODO[\040\t](.+)/",File::read($ref->getFileName()),$match)){
			foreach($match[1] as $t) $tasks[] = trim($t);
		}
		$properties = array();
		$r = new ReflectionClass($class);
		$d = '';
		while(true){
			$d = $r->getDocComment().$d;
			if(($r = $r->getParentClass()) === false) break;
		}
		$d = preg_replace("/^[\s]*\*[\s]{0,1}/m",'',str_replace(array('/'.'**','*'.'/'),'',$d));
		foreach($ref->getProperties() as $prop){
			if(!$prop->isPrivate()){
				if(substr($prop->getName(),0,1) != '_' && !$prop->isStatic()) $properties[$prop->getName()] = array('mixed',null,null);
			}
		}
		if(preg_match_all("/@var\s([\w_]+[\[\]\{\}]*)\s\\\$([\w_]+)(.*)/",$d,$m)){
			foreach($m[2] as $k => $n){
				if(isset($properties[$n])){
					$dec = preg_replace('/^(.*?)@.*$/','\\1',$m[3][$k]);
					$anon = json_decode(preg_replace('/^.*?@(.*)$/','\\1',$m[3][$k]),true);
					$hash = !(isset($anon['hash']) && $anon['hash'] === false);
					$properties[$n] = array($m[1][$k],$dec,$hash);
				}
			}
		}
		$this->vars('extends',$extends);
		$this->vars('static_methods',$static_methods);
		$this->vars('methods',$methods);
		$this->vars('properties',$properties);
		$this->vars('tasks',$tasks);
		$this->vars('package',$path);
		$this->vars('description',trim(preg_replace("/@.+/",'',$document)));
	}
	
	/**
	 * クラスドメソッドのキュメント
	 * @param string $path
	 * @param string $method_name
	 */
	public function method_info($path,$method_name){
		$class = import($path);
		$ref = new ReflectionMethod($class,$method_name);
		$src = implode(array_slice(file($ref->getDeclaringClass()->getFileName()),$ref->getStartLine(),($ref->getEndLine()-$ref->getStartLine()-1)));

		$document = trim(preg_replace("/^[\s]*\*[\s]{0,1}/m","",str_replace(array("/"."**","*"."/"),"",$ref->getDocComment())));
		$params = array();
		$return = array();
		
		if(preg_match("/@return\s+([^\s]+)(.*)/",$document,$match)){
			// type, summary
			$return = array(trim($match[1]),trim($match[2]));
		}
		foreach($ref->getParameters() as $p){
			$params[$p->getName()] = array(
							// type, is_ref, has_default, default, summary
							'mixed'
							,$p->isPassedByReference()
							,$p->isDefaultValueAvailable()
							,($p->isDefaultValueAvailable() ? $p->getDefaultValue() : null)
							,null
						);
		}
		if(preg_match_all("/@param\s+([^\s]+)\s+\\$(\w+)(.*)/",$document,$match)){
			foreach($match[0] as $k => $v){
				if(isset($params[$match[2][$k]])){
					$params[$match[2][$k]][0] = $match[1][$k];
					$params[$match[2][$k]][4] = (isset($match[3][$k]) ? $match[3][$k] : 'null');
				}
			}
		}
		$request = $context = array();
		if(preg_match_all('/->in_vars\((["\'])(.+?)\\1/',$src,$match)){
			foreach($match[2] as $n) $request[$n] = $context[$n] = array("mixed",null);
		}
		if(preg_match_all('/\$this->rm_vars\((["\'])(.+?)\\1/',$src,$match)){
			foreach($match[2] as $n){
				if(isset($context[$n])) unset($context[$n]);
			}
		}
		if(preg_match_all('/->in_files\((["\'])(.+?)\\1/',$src,$match)){
			foreach($match[2] as $n) $request[$n] = array("mixed",null);
		}
		if(strpos($src,'$this->rm_vars()') !== false){
			$context = array();
		}
		if(preg_match_all('/\$this->vars\((["\'])(.+?)\\1/',$src,$match)){				
			foreach($match[2] as $n) $context[$n] = array("mixed",null);
		}
		if(preg_match_all("/@request\s+([^\s]+)\s+\\$(\w+)(.*)/",$document,$match)){
			foreach($match[0] as $k => $v){
				if(isset($request[$match[2][$k]])){
					$request[$match[2][$k]][0] = $match[1][$k];
					$request[$match[2][$k]][1] = (isset($match[3][$k]) ? $match[3][$k] : 'null');
				}
				if(isset($context[$match[2][$k]])){
					$context[$match[2][$k]][0] = $match[1][$k];
					$context[$match[2][$k]][1] = (isset($match[3][$k]) ? $match[3][$k] : 'null');
				}
			}
		}
		if(preg_match_all("/@context\s+([^\s]+)\s+\\$(\w+)(.*)/",$document,$match)){
			foreach($match[0] as $k => $v){
				if(isset($context[$match[2][$k]])){
					$context[$match[2][$k]][0] = $match[1][$k];
					$context[$match[2][$k]][1] = (isset($match[3][$k]) ? $match[3][$k] : 'null');
				}
			}
		}
		
		$args = array();
		if(preg_match_all('/\$this->(map_arg|redirect_by_map)\((["\'])(.+?)\\2/',$src,$match)){
			foreach($match[3] as $n) $args[$n] = "";
		}
		if(preg_match_all("/@arg\s+([^\s]+)\s+\\$(\w+)(.*)/",$document,$match)){
			foreach($match[0] as $k => $v){
				if(isset($args[$match[2][$k]])){
					$args[$match[2][$k]] = (isset($match[3][$k]) ? $match[3][$k] : 'null');
				}
			}
		}
		$this->vars('package',$path);
		$this->vars('method_name',$method_name);
		$this->vars('params',$params);
		$this->vars('request',$request);
		$this->vars('context',$context);
		$this->vars('args',$args);
		$this->vars('return',$return);
		$this->vars('description',trim(preg_replace("/@.+/",'',$document)));
		$this->vars('is_post',(strpos($src,'$this->is_post()') !== false));
	}
	private function search_model(){
		$models = array();
		foreach(get_classes(true) as $path => $class){
			if(!class_exists($class) && !interface_exists($class)) Lib::import($path);
		}
		foreach(get_declared_classes() as $class){
			if(is_class($class) && is_subclass_of($class,'Dao')) $models[] = $class;
		}
		sort($models);
		return $models;
	}
	private function get_model($name){
		$args = null;
		if(is_array($this->in_vars('primary'))){
			foreach($this->in_vars('primary') as $k => $v) $args .= $k.'='.$v.',';
		}
		return new $name($args);
	}
	/**
	 * 検索
	 * 
	 * @param string $name モデル名
	 * 
	 * @request string $order ソート順
	 * @request int $page ページ番号
	 * @request string $query 検索文字列
	 * @request string $porder 直前のソート順
	 * 
	 * @context array $object_list 結果配列
	 * @context Paginator $paginator ページ情報
	 * @context string $porder 直前のソート順
	 * @context Dao $model 検索対象のモデルオブジェクト
	 * @context string $model_name 検索対象のモデルの名前
	 */
	public function do_find($name){
		$class = $this->get_model($name);
		$order = Sorter::order($this->in_vars('order'),$this->in_vars('porder'));
		$paginator = new Paginator(20,$this->in_vars('page'));
		$this->vars('query',$this->in_vars('q'));
		$this->vars('object_list',C($class)->find_all(Q::match($this->in_vars('q')),$paginator,Q::select_order($order,$this->in_vars('porder'))));
		$this->vars('paginator',$paginator->cp(array('q'=>$this->in_vars('q'),'order'=>$order)));
		$this->vars('model',$class);
		$this->vars('model_name',$name);
	}
	/**
	 * 詳細
	 * @param string $name モデル名
	 */
	public function do_detail($name){
		$class = $this->get_model($name);
		$this->vars('object',$class->sync());
		$this->vars('model',$class);
		$this->vars('model_name',$name);
	}
	/**
	 * 削除
	 * @param string $name モデル名
	 */
	public function do_drop($name){
		if($this->is_post()){
			Dao::begin_write();
				$class = $this->get_model($name);
				$class->sync()->delete();
			Dao::end_write();
			$this->redirect_referer();
		}
		$this->redirect_method('do_find',$name);
	}
	/**
	 * 更新
	 * @param string $name モデル名
	 */
	public function do_update($name){
		$class = $this->get_model($name);
		
		if($this->is_post()){
			try{
				Dao::begin_write();
					$obj = $this->get_model($name);
					$obj = $obj->cp($this->vars());
					$obj->save();
				Dao::end_write();

				if($this->is_vars('save_and_add_another')){
					$this->redirect_method('do_create',$name);
				}else{
					$this->redirect_method('do_find',$name);
				}
			}catch(Exception $e){
				Exceptions::add($e);
			}
		}else{
			$obj = $class->sync();
			$this->cp($obj);
		}
		$this->vars('model',$class);
		$this->vars('model_name',$name);
	}
	/**
	 * 作成
	 * @param string $name モデル名
	 */
	public function do_create($name){
		$class = $this->get_model($name);
		if($this->is_post()){
			try{
				Dao::begin_write();
				$class->cp($this->vars())->save();
				Dao::end_write();

				if($this->is_vars('save_and_add_another')){
					$this->redirect_method('do_create',$name);
				}else{
					$this->redirect_method('do_find',$name);
				}
			}catch(Exception $e){
				Exceptions::add($e);
			}
		}else{
			$this->cp($class);
		}
		$this->vars('model',$class);
		$this->vars('model_name',$name);
	}
	public function conf_list(){
		$consts = array();
		foreach(array_keys(Lib::classes(true,true)) as $package){
			$package_name = preg_replace("/^.+\.(\w+)$/","\\1",$package);
			$php = File::absolute(Lib::path(),str_replace('.','/',$package)).'.php';
			if(!is_file($php)) $php = File::absolute(Lib::path(),str_replace('.','/',$package).'/'.$package_name).'.php';
			if(!is_file($php)) $php = File::absolute(Lib::vendors_path(),str_replace('.','/',$package)).'.php';
			if(!is_file($php)) $php = File::absolute(Lib::vendors_path(),str_replace('.','/',$package).'/'.$package_name).'.php';

			$files = array();
			if(is_file($php)){
				if(strpos($php,$package_name.'/'.$package_name.'.php') !== false){
					foreach(File::ls($php) as $file){
						if($file->is_ext('php')) $files[] = $file->fullname();
					}
				}else{
					$files[] = $php;					
				}
			}
			$docs = array();
			foreach($files as $file){
				$class_src = str_replace(array("\r\n","\r"),"\n",File::read($file));

				if(preg_match_all('/module_const\((["\'])(.+?)\\1/',$class_src,$match)){
					foreach($match[2] as $name) $consts[$package.'@'.trim($name)] = array('string',null);
				}
				if(preg_match_all('/module_const_array\((["\'])(.+?)\\1/',$class_src,$match)){
					foreach($match[2] as $name) $consts[$package.'@'.trim($name)] = array('mixed[]',null);
				}
				if(preg_match_all("/@const\s+([^\s]+)\s+\\$(\w+)(.*)/",$class_src,$match)){
					foreach($match[0] as $k => $v) $docs[$package.'@'.trim($match[2][$k])] = array($match[1][$k],trim($match[3][$k]));
				}
				if(preg_match_all("/@const\s+\\$(\w+)(.*)/",$class_src,$match)){
					foreach($match[0] as $k => $v) $docs[$package.'@'.trim($match[1][$k])] = array('string',trim($match[2][$k]));
				}
			}
			foreach($docs as $k => $v){
				if(isset($consts[$k])) $consts[$k] = $docs[$k];
			}
		}
		$list = array();
		foreach($consts as $n => $conf){
			$obj = new Object();
			list($obj->class,$obj->name) = explode('@',$n);
			$obj->type = $conf[0];
			$obj->summary = $conf[1];
			$obj->exists = App::defined($n);
			if($this->search_str($obj->class,$obj->name,$obj->summary)) $list[$n] = $obj;
		}
		$this->vars('object_list',$list);
	}
	/**
	 * メールの一覧
	 */
	public function mail_list(){
		$paginator = new Paginator(20,$this->in_vars('page'));
		$order = $this->in_vars('order','-id');
		$this->vars('query',$this->in_vars('query'));
		$this->vars('object_list',C(SmtpBlackholeDao)->find_all(Q::match($this->in_vars('query')),$paginator,Q::select_order($order,$this->in_vars('porder'))));
		$this->vars('paginator',$paginator->cp(array('query'=>$this->in_vars('query'),'order'=>$order)));
	}
	/**
	 * メールの詳細
	 * @param integer $id
	 */
	public function mail_detail($id){
		$model = C(SmtpBlackholeDao)->find_get(Q::eq('id',$id));
		$this->vars('obj',$model);
	}
}
