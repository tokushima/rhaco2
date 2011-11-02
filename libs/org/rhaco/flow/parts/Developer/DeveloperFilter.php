<?php
class DeveloperFilter extends Templf{
	public function type($class){
		if(preg_match("/[A-Z]/",$class[0])){
			switch(substr($class,-2)){
				case "{}":
				case "[]": $class = substr($class,0,-2);
			}
			return Lib::package_path($class);
		}
		return null;
	}
	/**
	 * アクセサ
	 * @param Dao $obj
	 * @param string $prop_name
	 * @param string $ac
	 */
	public function acr(Dao $obj,$prop_name,$ac){
		return $obj->{$ac."_".$prop_name}();
	}
	/**
	 * プロパティ一覧
	 * @param Dao $obj
	 * @param integer $len 表示数
	 */
	public function props(Dao $obj,$len=null){
		$result = array();
		$i = 0;
		foreach($obj->props() as $prop){
			if($obj->a($prop,'extra') !== true && $obj->a($prop,'cond') === null){
				if($len !== null && $len < $i) break;
				$result[] = $prop;
				$i++;
			}
		}
		return $result;
	}
	public function summary($obj,$name,$length,$postfix='..'){
		return $this->trim_width($this->html($obj->{"fm_".$name}(),0,1),20,"..");
	}
	public function primary_query(Dao $obj){
		$result = array();
		foreach($obj->props() as $prop){
			if($obj->a($prop,'primary') === true && $obj->a($prop,'extra') !== true && $obj->a($prop,'cond') === null){
				$result[] = "primary[".$prop."]=".$obj->{$prop}();
			}
		}
		return implode("&",$result);
	}
	public function primary_hidden(Dao $obj){
		$result = array();
		foreach($obj->props() as $prop){
			if($obj->a($prop,'primary') === true && $obj->a($prop,'extra') !== true && $obj->a($prop,'cond') === null){
				$result[] = '<input type="hidden" name="primary['.$prop.']" value="'.$obj->{$prop}().'" />';
			}
		}
		return implode("&",$result);
	}
	public function is_primary($obj,$name){
		return $obj->a($name,"primary");
	}
	public function form($object,$name){
		try{
			return $object->{"form_".$name}();
		}catch(DaoException $e){
			try{
				$options = array();
				if(!$object->a($name,"require")) $options[] = '<option value=""></option>';
				foreach($object->{"master_".$name}() as $id => $dao){
					$options[] = sprintf('<option value="%s">%s</option>',$id,$dao->str());
				}
				return sprintf('<select name="%s">%s</select>',$name,implode("",$options));
			}catch(DaoException $e){
				if($object->a($name,"save",true) && $object->a($name,"type") !== "serial"){
					switch($object->a($name,"type")){
						case "text": return sprintf('<textarea name="%s">%s</textarea>',$name,$object->{$name}());
						case "boolean":
							$options = array();
							if(!$object->a($name,"require")) $options[] = '<option value=""></option>';
							foreach(array('true','false') as $choice) $options[] = sprintf('<option value="%s">%s</option>',$choice,$choice);
							return sprintf('<select name="%s">%s</select>',$name,implode("",$options));
						case "choice":
							$options = array();
							if(!$object->a($name,"require")) $options[] = '<option value=""></option>';
							foreach($object->a($name,"choices") as $choice) $options[] = sprintf('<option value="%s">%s</option>',$choice,$choice);
							return sprintf('<select name="%s">%s</select>',$name,implode("",$options));
						default:
							$value = $object->{$name}();
							switch($object->a($name,"type")){
								case "timestamp":
									$value = empty($value) ? null : date("Y/m/d H:i:s",$value);
									break;
								case "date":
									$value = empty($value) ? null : date("Y/m/d",$value);
									break;
								case "time":
									if(!empty($value)){
										$h = floor($value / 3600);
										$i = floor(($value - ($h * 3600)) / 60);
										$s = (int)($value - ($h * 3600) - ($i * 60));
										$m = str_replace("0.","",$value - ($h * 3600) - ($i * 60) - $s);
										$value = (($h == 0) ? "" : $h.":").(($i == 0) ? "" : sprintf("%02d",$i).":").sprintf("%02d",$s).(($m == 0) ? "" : ".".$m);
									}
									break;
							}
							return sprintf('<input name="%s" type="text" value="%s" />',$name,$value);
					}
				}
				return sprintf('<input name="%s" type="hidden" value="%s" /><spn class="hidden">&nbsp;%s</span>',$name,$object->{$name}(),$object->{$name}());
			}
		}
	}
	public function pre($value){
		$value = str_replace(array("<",">","'","\""),array("&lt;","&gt;","&#039;","&quot;"),$value);
		$value = preg_replace("/!!!(.+?)!!!/ms","<span class=\"notice\">\\1</span>",$value);
		$value = str_replace("\t","&nbsp;&nbsp;",$value);
		return $value;
	}
}
