<?php
import("org.rhaco.storage.db.Dao");
import('org.rhaco.storage.queue.QueueModel');
/**
 * @var serial $id
 * @var number $lock
 * @var string $type
 * @var text $data
 * @var timestamp $fin;
 * @var integer $priority
 * @var timestamp $create_date @{"auto_now_add":true}
 * @author tokushima
 */
class QueueDao extends Dao{
/*
CREATE TABLE `queue_dao` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(32) NOT NULL,
  `data` varchar(255) DEFAULT NULL,
  `lock` float DEFAULT NULL,
  `fin` timestamp DEFAULT NULL,
  `priority` int(11) NOT NULL DEFAULT '3',
  `create_date` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;
 */
	protected $id;
	protected $type;
	protected $lock;
	protected $fin;
	protected $data;
	protected $priority;
	protected $create_date;
	
	public function set(QueueModel $obj){
		foreach($obj->prop_values() as $k => $v) $this->{$k} = $v;
		return $this;
	}
	public function get(){
		$obj = new QueueModel();
		foreach($this->prop_values() as $k => $v) $obj->{$k}($v);
		return $obj;
	}
}