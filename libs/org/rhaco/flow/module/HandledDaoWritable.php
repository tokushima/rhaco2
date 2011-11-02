<?php
import("org.rhaco.storage.db.Dao");
/**
 * Flowでupdateパラメータによって接続先を切り替える
 * @author tokushima
 *
 */
class HandledDaoWritable extends Object{
	/**
	 * module Flow
	 */
	public function init_flow_handle(Flow $flow){
		$map = $flow->handled_map();
		if(
			$map["update"] == "both"
			|| ($map["update"] == "get" && !$flow->is_post())
			|| ($map["update"] == "post" && $flow->is_post())
		){
			Dao::begin_write();
		}else{
			Dao::end_write();
		}
	}
	/**
	 * @module Flow
	 */
	public function after_flow_handle(Flow $flow){
		Dao::end_write();
	}
	/**
	 * @module Flow
	 * @param Exception $exception
	 * @param Flow $flow
	 */
	public function flow_handle_exception($exception,Flow $flow){
		foreach(Dbc::connections() as $name => $con){
			$con->rollback();
		}
	}
}