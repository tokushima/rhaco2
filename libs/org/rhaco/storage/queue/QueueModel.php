<?php
/**
 * @author tokushima
 * @var serial $id
 * @var string $type
 * @var text $data
 * @var number $lock
 * @var timestamp $fin
 * @var integer $priority
 * @var timestamp $create_date
 */
class QueueModel extends Object{
	protected $id;
	protected $type;
	protected $data;
	protected $lock;
	protected $fin;
	protected $priority;
	protected $create_date;
}
