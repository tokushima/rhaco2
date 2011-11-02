<?php
import("org.rhaco.storage.db.Dao");
/**
 * @author tokushima
 * @var serial $id
 */
class BlogTag extends Dao{
	protected $_table_ = "tag";
	protected $id;
	protected $name;
}