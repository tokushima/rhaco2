<?php
class CrudTools{
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
							return sprintf('<input type="radio" name="%s" value="1"'.(($object->{$name}()) ? ' checked' : '').'>true <input type="radio" name="%s" value="0"'.((!$object->{$name}()) ? ' checked' : '').'>false',$name,$name);
						case "choice":
							$options = array();
							if(!$object->a($name,"require")) $options[] = '<option value=""></option>';
							foreach($object->a($name,"choices") as $choice) $options[] = sprintf('<option value="%s">%s</option>',$choice,$choice);
							return sprintf('<select name="%s">%s</select>',$name,implode("",$options));
						default: return sprintf('<input name="%s" type="text" value="%s" />',$name,$this->format_value($object->a($name,"type"),$object->{$name}()));
					}
				}
				return sprintf('<input name="%s" type="hidden" value="%s" />%s',$name,$object->{$name}(),$object->{$name}());
			}
		}
	}
	private function format_value($type,$value){
		if($value === null) return null;
		switch($type){
			case "timestamp": return date("Y/m/d H:i:s",$value);
			case "date": return date("Y/m/d",$value);
			case "time":
				$h = floor($value / 3600);
				$i = floor(($value - ($h * 3600)) / 60);
				$s = (int)($value - ($h * 3600) - ($i * 60));
				$m = str_replace("0.","",$value - ($h * 3600) - ($i * 60) - $s);
				return (($h == 0) ? "" : $h.":").(($i == 0) ? "" : sprintf("%02d",$i).":").sprintf("%02d",$s).(($m == 0) ? "" : ".".$m);
			default: return $value;
		}
		return $this->{$param->name};
	}
	public function is_primary($object,$name){
		return $object->a($name,"primary");
	}
	public function label($object,$name){
		return trans($object->{"label_".$name}());
	}
	public function value($object,$name){
		return $object->{"fm_".$name}();
	}
	public function column_names($object){
		$columns = array();
		foreach($object->get_columns() as $name){
			if($object->a($name,"cond") === null) $columns[] = $name;
		}
		return $columns;
	}
	public function primary_values($object){
		$result = array();
		$columns = $object->get_columns();

		if(is_string($columns)) $columns = Text::trim(explode(",",$columns));
		foreach($columns as $name){
			if($object->a($name,"primary")) $result[$name] = $object->{$name}();
		}
		return $result;
	}
}
